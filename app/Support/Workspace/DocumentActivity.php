<?php

namespace App\Support\Workspace;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Activity of one business document (purchase request, goods receipt, supplier
 * bill). The activity log has no subject columns, so a document's history is
 * the entries whose description names the document number, restricted to the
 * modules that write it and to the users the viewer may see (NR-32).
 */
final class DocumentActivity
{
    /**
     * @param  array<int, string|null>  $references  document numbers to look for
     * @param  array<int, string>  $modules  activity modules that write those documents
     */
    public static function query(User $user, array $references, array $modules): Builder
    {
        $references = collect($references)->filter()->unique()->values();

        return ActivityLog::query()->visibleTo($user)
            ->whereIn('module', $modules)
            ->where(function ($q) use ($references) {
                if ($references->isEmpty()) {
                    $q->whereRaw('1 = 0');
                }
                foreach ($references as $reference) {
                    $q->orWhere('description', 'like', '%'.addcslashes($reference, '%_').'%');
                }
            })
            ->latest('created_at')->latest('id');
    }

    /** Latest entries for a read-only document page, or null when the viewer may not read activity. */
    public static function latest(User $user, array $references, array $modules, int $limit = 10)
    {
        if (! $user->hasPermission('Activity Logs', 'view')) {
            return null;
        }

        return self::query($user, $references, $modules)->limit($limit)->get();
    }
}
