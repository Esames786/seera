<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The application's current ageing rule, shared by the accounting dashboard
 * and the Customer / Supplier workspaces: days late = due date → today;
 * 0 or earlier = Current, 1–30, 31–60, over 60. Rows without a due date
 * count as Current. This is a current-state ageing (as of today), not an
 * as-of-date historical report.
 */
final class AgeingBuckets
{
    public const BUCKETS = ['Current', '1-30 days', '31-60 days', '60+ days'];

    /**
     * @param  iterable<object>  $rows  objects with the due-date attribute and `balance_amount`
     * @return array<string, float>
     */
    public static function fromRows(iterable $rows, string $dateColumn = 'due_date'): array
    {
        $today = Carbon::today();
        $buckets = array_fill_keys(self::BUCKETS, 0.0);

        foreach ($rows as $row) {
            $due = $row->{$dateColumn};
            $daysLate = $due ? Carbon::parse($due)->diffInDays($today, false) : 0;

            $bucket = match (true) {
                $daysLate <= 0 => 'Current',
                $daysLate <= 30 => '1-30 days',
                $daysLate <= 60 => '31-60 days',
                default => '60+ days',
            };

            $buckets[$bucket] += (float) $row->balance_amount;
        }

        return array_map(fn ($value) => round($value, 2), $buckets);
    }
}
