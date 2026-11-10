<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\ApprovalActionNotification;
use App\Notifications\CustomerOverdueNotification;
use App\Services\Approvals\ApprovalRuntimeService;
use App\Services\UserAccessScopeService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class NotificationAccess
{
    public const HISTORY_LIMIT = 300;

    public const TYPES = [ApprovalActionNotification::class, CustomerOverdueNotification::class];

    /**
     * Never trust a persisted notification as current permission to see a record.
     * Resolve the source through the same document-scoped query used by approvals.
     */
    public function visible(DatabaseNotification $notification, User $actor): bool
    {
        return $this->filterVisible(collect([$notification]), $actor)->isNotEmpty();
    }

    /** Check each source type in one scoped query, rather than a DB query per notification. */
    public function filterVisible(Collection $rows, User $actor): Collection
    {
        if ($actor->status !== 'active') {
            return collect();
        }
        $valid = $rows->filter(function ($notice) use ($actor) {
            if (! in_array($notice->type, self::TYPES, true)
                || $notice->notifiable_type !== $actor->getMorphClass()
                || (string) $notice->notifiable_id !== (string) $actor->id) {
                return false;
            }
            $data = $notice->data;
            if (! is_array($data) || filter_var($data['source_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                return false;
            }

            return $notice->type === CustomerOverdueNotification::class
                ? ($data['source_type'] ?? null) === 'customer_invoice' && ($data['event'] ?? null) === 'overdue'
                : in_array($data['source_type'] ?? null, ['purchase_request', 'site_expense', 'supplier_bill'], true)
                    && in_array($data['event'] ?? null, ['requested', 'approved', 'rejected'], true);
        });
        $allowed = [];
        $modules = [];
        foreach ($valid->groupBy(fn ($notice) => $notice->data['source_type']) as $type => $notices) {
            if ($type === 'customer_invoice') {
                $reminders = app(CustomerOverdueReminders::class);
                if (! $reminders->canReceive($actor)) {
                    continue;
                }
                $modules[$type] = 'Accounts Receivable';
                $query = $reminders->due();
                (new UserAccessScopeService)->apply($query, $query->getModel(), $actor);
            } else {
                $subject = app(ApprovalRuntimeService::class)->subject($type);
                $modules[$type] = $subject->module();
                if (! $actor->hasPermission($subject->module(), 'view')) {
                    continue;
                }
                $query = $subject->queryFor($actor);
            }
            $ids = $notices->map(fn ($notice) => (int) $notice->data['source_id'])->unique()->all();
            $allowed[$type] = array_fill_keys($query->whereKey($ids)->pluck('id')->all(), true);
        }

        return $valid->filter(function ($notice) use ($allowed, $modules) {
            $data = $notice->data;

            return ($data['module'] ?? null) === ($modules[$data['source_type']] ?? null)
                && isset($allowed[$data['source_type']][(int) $data['source_id']]);
        });
    }

    public function recent(User $actor, int $limit = 5): Collection
    {
        $rows = $actor->notifications()
            ->whereIn('type', self::TYPES)
            ->latest()->orderByDesc('id')->limit(self::HISTORY_LIMIT)->get();

        return $this->filterVisible($rows, $actor)->take($limit);
    }

    public function unreadCount(User $actor): int
    {
        $count = 0;
        $actor->unreadNotifications()
            ->whereIn('type', self::TYPES)
            ->chunkById(self::HISTORY_LIMIT, function ($rows) use ($actor, &$count) {
                $count += $this->filterVisible($rows, $actor)->count();
            });

        return $count;
    }
}
