<?php

namespace App\Services\Notifications;

use App\Models\ApprovalInstance;
use App\Models\User;
use App\Notifications\ApprovalActionNotification;

class ApprovalNotificationPublisher
{
    public function notifyStep(ApprovalInstance $instance): void
    {
        if ($instance->status !== 'pending') {
            return;
        }

        $step = $instance->currentStep();
        if (! $step) {
            return;
        }

        $subject = app(\App\Services\Approvals\ApprovalRuntimeService::class)->subject($instance->source_type);
        foreach (User::whereIn('id', $step->eligible_user_ids ?? [])->where('status', 'active')->get() as $user) {
            // Candidate IDs are snapshotted on submit but permissions may have changed.
            if (! $user->hasPermission($subject->module(), 'view')
                || ! $user->hasPermission($subject->module(), 'approve')
                || ! $user->hasEffectiveRole((int) $step->approver_role_id)
                || ! $subject->queryFor($user)->whereKey($instance->source_id)->exists()) {
                continue;
            }

            $this->send($user, $instance, 'requested');
        }
    }

    public function notifyRequester(ApprovalInstance $instance, string $event): void
    {
        if (! in_array($event, ['approved', 'rejected'], true)) {
            return;
        }
        $requester = User::whereKey($instance->requested_by)->where('status', 'active')->first();
        if (! $requester) {
            return;
        }
        $subject = app(\App\Services\Approvals\ApprovalRuntimeService::class)->subject($instance->source_type);
        if ($requester->hasPermission($subject->module(), 'view')
            && $subject->queryFor($requester)->whereKey($instance->source_id)->exists()) {
            $this->send($requester, $instance, $event);
        }
    }

    private function send(User $user, ApprovalInstance $instance, string $event): void
    {
        $user->notify(new ApprovalActionNotification(
            $event,
            $instance->module,
            (string) ($instance->snapshot['subject']['reference'] ?? $instance->source_type.' #'.$instance->source_id),
            $instance->source_type,
            (int) $instance->source_id,
            (int) $instance->id,
        ));
    }
}
