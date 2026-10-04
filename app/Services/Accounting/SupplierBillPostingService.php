<?php

namespace App\Services\Accounting;

use App\Models\ActivityLog;
use App\Models\JournalEntry;
use App\Models\SupplierBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/** One posting path for explicitly legacy approval and completed runtime approval. */
class SupplierBillPostingService
{
    public function __construct(private readonly PostingService $posting, private readonly GrnMatchingService $matching) {}

    public function approveLegacy(int $id, int $actorId): JournalEntry
    {
        return DB::transaction(function () use ($id, $actorId) {
            $bill = SupplierBill::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($bill->approval_mode === 'runtime' || $bill->approvals()->exists()) {
                throw ValidationException::withMessages(['bill' => 'This bill requires its configured approval workflow. Legacy approval cannot bypass it.']);
            }

            return $this->postLocked($bill, $actorId);
        }, 3);
    }

    /** Called after the approval commit, or by authorized Finance retry. */
    public function attempt(int $id, int $actorId): void
    {
        try {
            DB::transaction(function () use ($id, $actorId) {
                $bill = SupplierBill::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->firstOrFail();
                $latest = $bill->approvals()->latest('attempt')->lockForUpdate()->first();
                if ($bill->approval_mode !== 'runtime' || $bill->approval_status !== 'approved' || $latest?->status !== 'approved') {
                    return; // In particular, an old approval cannot authorize a reopened bill.
                }
                if ($bill->journal_entry_id) {
                    return; // Existing review-mode journals must be posted, never recreated.
                }
                $entry = $this->postLocked($bill, $actorId);
                $this->log($bill, $actorId, 'Posting completed', $entry->journal_number.' ('.$entry->status.')');
            }, 3);
        } catch (Throwable $error) {
            $message = $error instanceof ValidationException ? implode(' ', array_merge(...array_values($error->errors())))
                : 'Bill posting could not complete. Finance can review configuration and retry; approval history is retained.';
            Log::warning('Supplier Bill posting failed', ['bill_id' => $id, 'exception_class' => $error::class]);
            DB::transaction(function () use ($id, $actorId, $message) {
                $bill = SupplierBill::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->firstOrFail();
                if ($bill->approval_status === 'approved' && ! $bill->journal_entry_id && $bill->status === 'draft') {
                    $bill->update(['posting_error' => mb_substr($message, 0, 2000)]);
                    $this->log($bill, $actorId, 'Posting failed', 'Approval retained; Finance retry required.');
                }
            }, 3);
        }
    }

    private function postLocked(SupplierBill $bill, int $actorId): JournalEntry
    {
        if ($bill->status !== 'draft' || $bill->journal_entry_id) {
            throw ValidationException::withMessages(['bill' => 'Only a draft supplier bill can be approved.']);
        }
        // Extracted verbatim business order from AccountsPayableController. F04
        // quantity consumption, VAT and accounting still roll back together.
        $this->matching->commit($bill);
        $entry = $this->posting->postSupplierBill($bill, $actorId);
        $bill->update(['status' => 'unpaid', 'paid_amount' => 0, 'balance_amount' => $bill->total_amount,
            'journal_entry_id' => $entry->id, 'posting_error' => null]);

        return $entry;
    }

    public function log(SupplierBill $bill, int $actorId, string $action, string $detail): void
    {
        ActivityLog::create(['user_id' => $actorId, 'user_name' => 'Finance', 'module' => 'Accounting', 'action' => $action,
            'description' => $bill->bill_number.' | '.$detail, 'status' => 'success']);
    }
}
