<?php

namespace App\Services\Approvals;

use App\Contracts\ApprovalSubject;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\UserAccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PurchaseRequestApprovalSubject implements ApprovalSubject
{
    public function type(): string
    {
        return 'purchase_request';
    }

    public function module(): string
    {
        return 'Purchase Requests';
    }

    public function workflowModule(): string
    {
        return 'Purchase Request';
    }

    public function trigger(): string
    {
        return 'Request Created';
    }

    public function queryFor(User $actor): Builder
    {
        $query = PurchaseRequest::withoutGlobalScope('user_access');
        // A new scope helper avoids stale project-manager assignments in long-lived workers.
        (new UserAccessScopeService)->apply($query, $query->getModel(), $actor);

        return $query;
    }

    public function canSubmit(Model $document, User $actor): bool
    {
        return $document->isEditable() && ($actor->hasPermission($this->module(), 'edit')
            || ((int) $document->requested_by === $actor->id && $actor->hasPermission($this->module(), 'create')));
    }

    public function lockForApproval(int $id): Model
    {
        return PurchaseRequest::withoutGlobalScope('user_access')->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function snapshot(Model $document): array
    {
        if (! $document->lines()->where('quantity', '>', 0)->exists()) {
            throw ValidationException::withMessages(['approval' => 'Add at least one requested item before submitting.']);
        }

        return [
            'reference' => $document->pr_number, 'project_id' => $document->project_id,
            'site_id' => $document->site_id, 'warehouse_id' => $document->warehouse_id,
            'document' => $document->attributesToArray(),
            'lines' => $document->lines()->get()->toArray(),
        ];
    }

    public function submitted(Model $document): void
    {
        $document->forceFill(['approval_mode' => 'runtime', 'status' => 'pending', 'approved_by' => null, 'approved_at' => null, 'rejection_reason' => null])->save();
    }

    public function completed(Model $document, User $actor): void
    {
        $document->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'rejection_reason' => null])->save();
    }

    public function rejected(Model $document, string $reason): void
    {
        $document->forceFill(['status' => 'rejected', 'approved_by' => null, 'approved_at' => null, 'rejection_reason' => $reason])->save();
    }

    public function url(Model $document): string
    {
        return route('admin.inventory.purchase-requests.show', $document).'#approvals';
    }
}
