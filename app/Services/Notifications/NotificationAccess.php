<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\ApprovalActionNotification;
use App\Services\Approvals\ApprovalRuntimeService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class NotificationAccess
{
    public const HISTORY_LIMIT = 300;

    /**
     * Never trust a persisted notification as current permission to see a record.
     * Resolve the source through the same document-scoped query used by approvals.
     */
    public function visible(DatabaseNotification $notification, User $actor): bool
    {
        if ($notification->type !== ApprovalActionNotification::class || $actor->status !== 'active'
            || $notification->notifiable_type !== $actor->getMorphClass()
            || (string) $notification->notifiable_id !== (string) $actor->id) {
            return false;
        }

        $data = $notification->data;
        if (! is_array($data)
            || ! in_array($data['source_type'] ?? null, ['purchase_request', 'site_expense', 'supplier_bill'], true)
            || ! in_array($data['event'] ?? null, ['requested', 'approved', 'rejected'], true)
            || ! is_numeric($data['source_id'] ?? null)) {
            return false;
        }

        $subject = app(ApprovalRuntimeService::class)->subject($data['source_type']);

        return ($data['module'] ?? null) === $subject->module()
            && $actor->hasPermission($subject->module(), 'view')
            && $subject->queryFor($actor)->whereKey((int) $data['source_id'])->exists();
    }

    public function recent(User $actor, int $limit = 5): Collection
    {
        return $actor->notifications()
            ->where('type', ApprovalActionNotification::class)
            ->latest()->orderByDesc('id')->limit(self::HISTORY_LIMIT)->get()
            ->filter(fn ($n) => $this->visible($n, $actor))
            ->take($limit);
    }

    public function unreadCount(User $actor): int
    {
        return $actor->unreadNotifications()
            ->where('type', ApprovalActionNotification::class)
            ->cursor()
            ->filter(fn ($n) => $this->visible($n, $actor))
            ->count();
    }
}
