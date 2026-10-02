<?php

namespace App\Services\Approvals;

use App\Contracts\ApprovalSubject;
use App\Models\ActivityLog;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalRuntimeService
{
    public function subject(string $type): ApprovalSubject
    {
        return match ($type) {
            'purchase_request' => app(PurchaseRequestApprovalSubject::class),
            'site_expense' => app(SiteExpenseApprovalSubject::class),
            default => abort(404),
        };
    }

    public function history(ApprovalSubject $subject, Model $document)
    {
        return ApprovalInstance::where('source_type', $subject->type())->where('source_id', $document->id)
            ->with('steps')->orderByDesc('attempt');
    }

    /** Source lock serializes enrolment, editing, resubmission and decisions. */
    public function start(ApprovalSubject $subject, int $documentId, int $workflowId, User $actor, ?int $previousId = null): ApprovalInstance
    {
        return DB::transaction(function () use ($subject, $documentId, $workflowId, $actor, $previousId) {
            // Acquire the mutex before ordinary reads establish a MySQL snapshot.
            // Nothing is exposed or mutated until current authorization below succeeds.
            $document = $subject->lockForApproval($documentId);
            $actor = $this->activeActor($actor);
            abort_unless($actor->hasPermission($subject->module(), 'view'), 403);
            $subject->queryFor($actor)->whereKey($documentId)->firstOrFail();
            $previous = $this->history($subject, $document)->lockForUpdate()->first();
            // A repeated submit form cannot silently create a second attempt.
            if ($previous?->status === 'pending' && $previous->submitted_by == $actor->id
                && $previous->approval_workflow_id == $workflowId
                && ($previous->snapshot['previous_instance_id'] ?? null) === $previousId) {
                abort_unless($actor->hasPermission($subject->module(), 'edit')
                    || ($document->requested_by == $actor->id && $actor->hasPermission($subject->module(), 'create')), 403);

                return $previous->setRelation('steps', $previous->steps()->lockForUpdate()->get());
            }
            abort_unless($subject->canSubmit($document, $actor), 403, 'This document cannot be submitted by your account in its current state.');
            if ($previous && ($previous->status !== 'rejected' || $previousId !== $previous->id)) {
                $this->invalid('Use the current rejected attempt to resubmit. Active or approved history cannot be reset.');
            }
            if (! $previous && $previousId !== null) {
                $this->invalid('There is no rejected instance to resubmit.');
            }

            $workflow = ApprovalWorkflow::whereKey($workflowId)->lockForUpdate()->firstOrFail();
            $workflow->setRelation('steps', $workflow->steps()->with('approverRole', 'approverUser')->lockForUpdate()->get());
            $requester = User::find($document->requested_by);
            if (! $requester) {
                $this->invalid('The original requester must exist before approval can be requested.');
            }
            if ($workflow->status !== 'active' || $workflow->module !== $subject->workflowModule()
                || $workflow->trigger_action !== $subject->trigger()) {
                $this->invalid('Choose an active workflow for this module and trigger.');
            }
            if ($workflow->department_id && $workflow->department_id != $requester->department_id) {
                $this->invalid('The workflow department does not match the original requester.');
            }
            if (! in_array($workflow->scope, ['Assigned Project/Site', 'All Projects', 'All Company'], true)
                || ($workflow->scope !== 'All Company' && ! $document->project_id)) {
                $this->invalid('This workflow scope is unsupported or requires a project on the document.');
            }
            $postingMode = $subject instanceof SiteExpenseApprovalSubject ? 'Create Accounting Entry' : 'No Auto Posting';
            if ($workflow->auto_posting !== $postingMode) {
                if ($subject instanceof SiteExpenseApprovalSubject) {
                    $this->invalid('Site Expenses requires Create Accounting Entry. Finance posting rules still control ledger review mode.');
                }
                $this->invalid('This integration does not support automatic accounting posting.');
            }
            $required = $workflow->steps->where('is_required', true);
            if ($required->isEmpty() || $workflow->steps->pluck('step_no')->unique()->count() !== $workflow->steps->count()) {
                $this->invalid('Configure at least one required step and unique sequential step numbers.');
            }
            $rows = [];
            foreach ($workflow->steps as $step) {
                if ($step->amount_limit !== null) {
                    $this->invalid('Amount-limit routing is not supported yet. Review this workflow and leave amount limits blank; no required step is skipped automatically.');
                }
                $eligible = [];
                if ($step->is_required) {
                    if (! $step->approverRole || $step->approverRole->status !== 'active') {
                        $this->invalid('Every required step must reference an active role.');
                    }
                    $candidates = $step->approverRole->users()->where('users.status', 'active')
                        ->when($step->approver_user_id, fn ($q) => $q->where('users.id', $step->approver_user_id))->get();
                    foreach ($candidates as $candidate) {
                        if (in_array($candidate->id, [$requester->id, $actor->id], true)) {
                            continue;
                        }
                        if (! $candidate->hasEffectiveRole((int) $step->approver_role_id)
                            || ! $candidate->hasPermission($subject->module(), 'view')
                            || ! $candidate->hasPermission($subject->module(), 'approve')) {
                            continue;
                        }
                        if ($subject->queryFor($candidate)->whereKey($document->id)->exists()) {
                            $eligible[] = $candidate->id;
                        }
                    }
                    if ($eligible === []) {
                        $this->invalid('Required step '.$step->step_no.' has no eligible non-requester approver with current role, permission and document scope.');
                    }
                }
                $rows[] = [
                    'step_no' => $step->step_no, 'approver_role_id' => $step->approver_role_id,
                    'approver_user_id' => $step->approver_user_id, 'is_required' => $step->is_required,
                    'can_reject' => $step->can_reject, 'eligible_user_ids' => array_values(array_unique($eligible)),
                    'status' => $step->is_required ? 'pending' : 'skipped',
                    'snapshot' => $step->attributesToArray() + ['role_name' => $step->approverRole?->name, 'user_name' => $step->approverUser?->name],
                ];
            }
            $instance = ApprovalInstance::create([
                'source_type' => $subject->type(), 'source_id' => $document->id, 'module' => $subject->module(),
                'attempt' => ($previous?->attempt ?? 0) + 1, 'approval_workflow_id' => $workflow->id,
                'status' => 'pending', 'requested_by' => $requester->id, 'submitted_by' => $actor->id, 'requested_at' => now(),
                'snapshot' => ['version' => 1, 'mode' => 'sequential', 'previous_instance_id' => $previous?->id,
                    'requester_name' => $requester->name, 'submitter_name' => $actor->name,
                    'workflow' => $workflow->attributesToArray(), 'subject' => $subject->snapshot($document)],
            ]);
            $instance->steps()->createMany($rows);
            $subject->submitted($document);
            $this->log($actor, $instance, $previous ? 'Resubmitted' : 'Approval requested');

            return $instance->load('steps');
        }, 3);
    }

    /** Public for the document UI/queue; mutations repeat all checks under lock. */
    public function eligible(ApprovalSubject $subject, Model $document, ApprovalInstance $instance, ApprovalInstanceStep $step, User $actor, string $action): bool
    {
        return in_array($action, ['approve', 'reject'], true) && $actor->status === 'active'
            && $actor->id != $instance->requested_by && $actor->id != $instance->submitted_by
            && in_array($actor->id, $step->eligible_user_ids, true)
            && $actor->hasEffectiveRole((int) $step->approver_role_id)
            && (! $step->approver_user_id || $actor->id == $step->approver_user_id)
            && $actor->hasPermission($subject->module(), 'view') && $actor->hasPermission($subject->module(), $action)
            && ($action !== 'reject' || $step->can_reject)
            && $subject->queryFor($actor)->whereKey($document->id)->exists();
    }

    public function decide(ApprovalSubject $subject, int $documentId, int $instanceId, int $stepId, User $actor, string $action, ?string $comment = null): ApprovalInstance
    {
        if (! in_array($action, ['approve', 'reject'], true)) {
            $this->invalid('Unsupported decision.');
        }
        $comment = trim($comment ?? '');
        if (mb_strlen($comment) > 1000 || ($action === 'reject' && $comment === '')) {
            $this->invalid('A rejection requires a reason; comments must be at most 1000 characters.');
        }

        return DB::transaction(function () use ($subject, $documentId, $instanceId, $stepId, $actor, $action, $comment) {
            $document = $subject->lockForApproval($documentId);
            $actor = $this->activeActor($actor);
            abort_unless($actor->hasPermission($subject->module(), 'view') && $actor->hasPermission($subject->module(), $action), 403);
            $subject->queryFor($actor)->whereKey($documentId)->firstOrFail();
            $instance = ApprovalInstance::whereKey($instanceId)->where('source_type', $subject->type())
                ->where('source_id', $document->id)->lockForUpdate()->firstOrFail();
            $step = $instance->steps()->whereKey($stepId)->lockForUpdate()->firstOrFail();
            // Use current locking reads, including under MySQL REPEATABLE READ.
            // A contender may have opened a read snapshot before waiting on the source lock.
            $instance->setRelation('steps', $instance->steps()->lockForUpdate()->get());
            abort_unless($this->eligible($subject, $document, $instance, $step, $actor, $action), 403, 'You are not an eligible approver for this step.');
            $decision = $action === 'approve' ? 'approved' : 'rejected';
            if ($step->status !== 'pending') {
                if ($step->status === $decision && $step->decided_by == $actor->id && ($step->comment ?? '') === $comment) {
                    return $instance;
                }
                $this->invalid('This step already has a decision. Conflicting retries cannot rewrite history.');
            }
            if ($instance->status !== 'pending' || $document->status !== 'pending'
                || $instance->currentStep()?->id !== $step->id) {
                $this->invalid('This step is not currently actionable.');
            }
            $step->update(['status' => $decision, 'decided_by' => $actor->id, 'decided_by_name' => $actor->name, 'decided_at' => now(), 'comment' => $comment === '' ? null : $comment]);
            if ($action === 'reject') {
                $instance->update(['status' => 'rejected', 'rejected_at' => now()]);
                $subject->rejected($document, $comment);
                $this->log($actor, $instance, 'Rejected', $step);
            } else {
                $this->log($actor, $instance, 'Step approved', $step);
                if ($instance->steps()->where('is_required', true)->where('status', '!=', 'approved')->lockForUpdate()->get()->isEmpty()) {
                    $instance->update(['status' => 'approved', 'completed_at' => now()]);
                    $subject->completed($document, $actor);
                    $this->log($actor, $instance, 'Fully approved');
                }
            }

            return $instance->setRelation('steps', $instance->steps()->lockForUpdate()->get());
        }, 3);
    }

    private function activeActor(User $actor): User
    {
        $actor = User::findOrFail($actor->id); // Never trust cached permissions across decisions.
        abort_unless($actor->status === 'active', 403);

        return $actor;
    }

    private function log(User $actor, ApprovalInstance $instance, string $action, ?ApprovalInstanceStep $step = null): void
    {
        ActivityLog::create(['user_id' => $actor->id, 'user_name' => $actor->name, 'module' => 'Approvals', 'action' => $action,
            'description' => ($instance->snapshot['subject']['reference'] ?? $instance->source_type.' #'.$instance->source_id)
                .' | approval #'.$instance->id.' attempt '.$instance->attempt.($step ? ' step '.$step->step_no : ''),
            'status' => 'success', 'ip_address' => request()->ip()]);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['approval' => $message]);
    }
}
