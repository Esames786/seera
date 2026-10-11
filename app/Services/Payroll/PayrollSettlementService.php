<?php

namespace App\Services\Payroll;

use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PayrollSettlement;
use App\Models\User;
use App\Services\Accounting\PostingService;
use App\Support\SettlementReplay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payroll Phase 2 — salary settlement. A posted payroll run is paid once, in
 * full, from an authorized bank or cash account: Dr payroll payable (the
 * account credited by the run's own posted journal), Cr bank / cash. The
 * expense is never touched again, no VAT is involved and project cost does
 * not move. The run row is the mutex; a one-time key makes a replayed request
 * answer with the settlement already recorded. A settlement is corrected by
 * a reversal that keeps the original and makes the run unpaid again.
 */
class PayrollSettlementService
{
    public const MODULE = 'Payroll Settlement';

    public function __construct(private readonly PostingService $posting) {}

    /** The Finance payment-account catalogue: the active Cash in Hand and Bank accounts only. */
    public static function paymentAccounts()
    {
        return ChartOfAccount::where('status', 'active')->where('account_type', 'asset')
            ->whereIn('account_code', [PostingService::CASH, PostingService::BANK])->orderBy('account_code');
    }

    /**
     * A run may be paid or exported only by a user who can see every employee
     * row in it: a project-scoped viewer never settles or exports a subset.
     */
    public static function coversWholeRun(User $user, PayrollRun $run): bool
    {
        if ($user->effectiveAccessScope() === 'company') {
            return true;
        }
        $all = PayrollRunItem::withoutGlobalScopes()->where('payroll_run_id', $run->id)->count();

        return $all === 0 || $run->items()->count() === $all;
    }

    /** Null when the run can be paid now; otherwise the reason Finance sees. */
    public function eligibilityError(PayrollRun $run): ?string
    {
        if (! $run->isApproved()) {
            return 'Only an approved payroll run can be paid.';
        }
        if ($run->reversal_journal_id || $run->accounting_status === PayrollRun::ACCOUNTING_REVERSED) {
            return 'The payroll accounting of this run was reversed; there is no payable to settle.';
        }
        if ($run->accounting_status !== PayrollRun::ACCOUNTING_POSTED || ! $run->journal_entry_id) {
            return 'Salary payment needs the payroll journal to be posted first. A review-mode draft journal is not enough: post it in Finance → Journal Entries.';
        }
        $journal = JournalEntry::withoutGlobalScopes()->find($run->journal_entry_id);
        if (! $journal || $journal->status !== 'posted') {
            return 'Salary payment needs the payroll journal to be posted first; it is currently '.($journal?->status ?? 'missing').'.';
        }
        if ((float) $run->net_amount <= 0) {
            return 'This payroll run has no payable amount.';
        }
        if ($run->isPaid()) {
            return 'This payroll run is already paid ('.($run->settled_at?->format('Y-m-d H:i') ?? '').'). Reverse the salary payment first if it was wrong.';
        }

        return null;
    }

    /**
     * Record the full salary payment. The same request repeated (same key, same
     * details) answers with the existing settlement; a reused key with other
     * details is refused; a second payment is refused.
     *
     * @param  array{payment_date: string, payment_account_id: int, reference_number?: ?string, notes?: ?string, idempotency_key?: ?string}  $data
     * @return array{0: PayrollSettlement, 1: bool} settlement and whether it was a replay
     */
    public function settle(int $runId, array $data, int $actorId): array
    {
        return DB::transaction(function () use ($runId, $data, $actorId) {
            $run = PayrollRun::whereKey($runId)->lockForUpdate()->firstOrFail();
            $key = $data['idempotency_key'] ?? null;
            if ($key) {
                $existing = PayrollSettlement::where('idempotency_key', $key)->first();
                if ($existing) {
                    SettlementReplay::assertMatches($existing, [
                        'payroll_run_id' => $run->id,
                        'payment_account_id' => (int) $data['payment_account_id'],
                        'payment_date' => $data['payment_date'],
                        'amount' => (float) $run->net_amount,
                        'reference_number' => $data['reference_number'] ?? null,
                    ], 'payment_date');

                    return [$existing, true];
                }
            }
            if ($error = $this->eligibilityError($run)) {
                $this->invalid($error);
            }
            if (Carbon::parse($data['payment_date'])->startOfDay()->gt(today())) {
                $this->invalid('The payment date cannot be in the future.');
            }
            $account = self::paymentAccounts()->whereKey((int) $data['payment_account_id'])->first();
            if (! $account) {
                $this->invalid('Choose an active Cash or Bank account from the Finance payment catalogue.');
            }

            $settlement = PayrollSettlement::create([
                'payroll_run_id' => $run->id,
                'payment_date' => $data['payment_date'],
                'payment_account_id' => $account->id,
                'payment_method' => $account->account_code === PostingService::CASH ? 'Cash' : 'Bank Transfer',
                'amount' => round((float) $run->net_amount, 2),
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $key,
                'created_by' => $actorId,
            ]);
            // A settlement without its journal is refused and rolled back together (F01).
            $entry = $this->posting->settlePayrollRun($run, $settlement, $account, $actorId);
            $settlement->update(['journal_entry_id' => $entry->id]);
            $run->update(['payment_status' => PayrollRun::PAYMENT_PAID, 'paid_amount' => $settlement->amount, 'settled_at' => now()]);
            $this->log($run, $actorId, 'Salary payment recorded', $entry->journal_number.' · SAR '.number_format((float) $settlement->amount, 2).' from '.$account->label().' on '.$settlement->payment_date->toDateString());

            return [$settlement, false];
        }, 3);
    }

    /** Finance-only: post the opposite journal; the settlement row stays, the run becomes unpaid again. */
    public function reverse(int $runId, string $reason, int $actorId): void
    {
        DB::transaction(function () use ($runId, $reason, $actorId) {
            $run = PayrollRun::whereKey($runId)->lockForUpdate()->firstOrFail();
            $settlement = PayrollSettlement::where('payroll_run_id', $run->id)->whereNull('reversal_journal_id')->lockForUpdate()->latest('id')->first();
            if (! $settlement) {
                $last = PayrollSettlement::where('payroll_run_id', $run->id)->latest('id')->first();
                if ($last && $last->reversal_reason === $reason) {
                    return; // same request repeated
                }
                $this->invalid('No salary payment is recorded against this payroll run; nothing to reverse.');
            }
            $entry = JournalEntry::withoutGlobalScopes()->whereKey($settlement->journal_entry_id)->lockForUpdate()->first();
            if (! $entry || $entry->status !== 'posted') {
                $this->invalid('The salary payment journal is not posted; Finance must review it before reversing.');
            }
            $reversal = $this->posting->reverseEntry($entry, 'Reverse salary payment '.$run->code.': '.$reason, $actorId);
            $settlement->update(['reversal_journal_id' => $reversal->id, 'reversed_at' => now(), 'reversal_reason' => $reason, 'reversed_by' => $actorId]);
            $run->update(['payment_status' => PayrollRun::PAYMENT_NOT_PAID, 'paid_amount' => 0, 'settled_at' => null]);
            $this->log($run, $actorId, 'Salary payment reversed', $reversal->journal_number.': '.$reason);
        }, 3);
    }

    private function log(PayrollRun $run, ?int $actorId, string $action, string $detail): void
    {
        ActivityLog::create(['user_id' => $actorId, 'user_name' => User::find($actorId)?->name ?? 'Accounting', 'module' => 'Payroll',
            'action' => $action, 'description' => $run->code.' - '.$detail, 'status' => 'success']);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }
}
