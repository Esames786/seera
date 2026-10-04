<?php

namespace App\Services\Approvals;

use App\Contracts\ApprovalSubject;
use App\Contracts\SeparateApprovalState;
use App\Models\ApprovalInstance;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\Accounting\GrnMatchingService;
use App\Services\Accounting\SupplierBillPostingService;
use App\Services\UserAccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierBillApprovalSubject implements ApprovalSubject, SeparateApprovalState
{
    public function type(): string
    {
        return 'supplier_bill';
    }

    public function module(): string
    {
        return 'Accounts Payable';
    }

    public function workflowModule(): string
    {
        return 'Supplier Bill';
    }

    public function trigger(): string
    {
        return 'Bill Submitted';
    }

    public function queryFor(User $actor): Builder
    {
        $query = SupplierBill::withoutGlobalScope('user_access');
        (new UserAccessScopeService)->apply($query, $query->getModel(), $actor);

        return $query;
    }

    public function lockForApproval(int $id): Model
    {
        return SupplierBill::withoutGlobalScope('user_access')->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function canSubmit(Model $document, User $actor): bool
    {
        return $document->status === 'draft' && $document->isEditable() && ! $document->journal_entry_id
            && ($actor->hasPermission($this->module(), 'edit')
                || ((int) $document->requested_by === $actor->id && $actor->hasPermission($this->module(), 'create')));
    }

    public function requesterId(Model $document, User $actor): int
    {
        if (! $document->requested_by) {
            $document->update(['requested_by' => $actor->id]); // Explicit legacy enrolment only.
        }

        return (int) $document->requested_by;
    }

    public function excludedApproverIds(Model $document): array
    {
        return array_values(array_filter([(int) $document->last_edited_by]));
    }

    public function isPending(Model $document): bool
    {
        return $document->status === 'draft' && $document->approval_status === 'pending';
    }

    public function canResubmit(Model $document, ApprovalInstance $previous): bool
    {
        return $previous->status === 'rejected' || ($previous->status === 'approved'
            && $document->approval_status === 'correction' && $document->status === 'draft' && ! $document->journal_entry_id);
    }

    public function snapshot(Model $document): array
    {
        $lines = $document->lines()->get();
        if ($lines->isEmpty() || (float) $document->total_amount <= 0
            || abs(round((float) $lines->sum('total_amount'), 2) - (float) $document->total_amount) > 0.001
            || abs(round((float) $lines->sum('taxable_amount'), 2) - (float) $document->taxable_amount) > 0.001
            || abs(round((float) $lines->sum('vat_amount'), 2) - (float) $document->vat_amount) > 0.001
            || $document->bill_date->isFuture() || ! $document->supplier) {
            throw ValidationException::withMessages(['bill' => 'Review the supplier, bill date, lines and calculated totals before submission.']);
        }
        app(GrnMatchingService::class)->reserve($document);

        return ['reference' => $document->bill_number, 'document' => $document->attributesToArray(),
            'lines' => $lines->toArray(), 'grn_matches' => $document->grnMatches()->get()->toArray()];
    }

    public function submitted(Model $document): void
    {
        $document->update(['approval_mode' => 'runtime', 'approval_status' => 'pending', 'rejection_reason' => null, 'posting_error' => null]);
    }

    public function completed(Model $document, User $actor): void
    {
        $document->update(['approval_status' => 'approved', 'rejection_reason' => null]);
        DB::afterCommit(fn () => app(SupplierBillPostingService::class)->attempt($document->id, $actor->id));
    }

    public function rejected(Model $document, string $reason): void
    {
        $document->update(['approval_status' => 'rejected', 'rejection_reason' => $reason]);
    }

    public function url(Model $document): string
    {
        return route('admin.accounting.accounts-payable.show', $document).'#approvals';
    }
}
