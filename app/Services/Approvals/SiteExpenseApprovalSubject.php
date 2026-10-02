<?php

namespace App\Services\Approvals;

use App\Contracts\ApprovalSubject;
use App\Models\SiteExpense;
use App\Models\User;
use App\Services\SiteExpenses\SiteExpenseAccountingService;
use App\Services\UserAccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SiteExpenseApprovalSubject implements ApprovalSubject
{
    public function type(): string
    {
        return 'site_expense';
    }

    public function module(): string
    {
        return 'Site Expenses';
    }

    public function workflowModule(): string
    {
        return 'Site Expenses';
    }

    public function trigger(): string
    {
        return 'Expense Submitted';
    }

    public function queryFor(User $actor): Builder
    {
        $query = SiteExpense::withoutGlobalScope('user_access');
        (new UserAccessScopeService)->apply($query, $query->getModel(), $actor);

        return $query;
    }

    public function lockForApproval(int $id): Model
    {
        return SiteExpense::withoutGlobalScope('user_access')->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function canSubmit(Model $document, User $actor): bool
    {
        return $document->isEditable() && ($actor->hasPermission($this->module(), 'edit')
            || ($document->requested_by === $actor->id && $actor->hasPermission($this->module(), 'create')));
    }

    public function snapshot(Model $document): array
    {
        if ($document->category?->status !== 'active' || ($document->category->invoice_photo_required && ! $document->receipts()->exists())) {
            throw ValidationException::withMessages(['receipt' => 'Choose an active category and attach its required receipt before submitting.']);
        }

        return ['reference' => $document->expense_number, 'project_id' => $document->project_id,
            'site_id' => $document->site_id, 'document' => $document->attributesToArray(),
            'receipts' => $document->receipts()->get(['id', 'original_filename', 'mime_type', 'size'])->toArray()];
    }

    public function submitted(Model $document): void
    {
        $document->update(['status' => 'pending', 'submitted_at' => now(), 'rejection_reason' => null]);
    }

    public function completed(Model $document, User $actor): void
    {
        $document->update(['status' => 'approved_pending_posting', 'approved_at' => now(), 'rejection_reason' => null]);
        // Approval commits first. A failed accounting attempt cannot undo its
        // decisions. A crash between commit and this callback remains retryable.
        DB::afterCommit(fn () => app(SiteExpenseAccountingService::class)->attempt($document->id, $actor->id));
    }

    public function rejected(Model $document, string $reason): void
    {
        $document->update(['status' => 'rejected', 'rejected_at' => now(), 'rejection_reason' => $reason]);
    }

    public function url(Model $document): string
    {
        return route('admin.site-expenses.show', $document).'#approvals';
    }
}
