<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

/**
 * HR configuration a production company needs before the first leave request
 * (client change request NR-17: the Leave Type dropdown was empty). Creates the
 * standard leave types only when missing and never changes an existing one.
 *
 *   php artisan db:seed --class=ProductionHrDefaultsSeeder --force
 */
class ProductionHrDefaultsSeeder extends Seeder
{
    /** [code, name, max days per year, paid] */
    public const LEAVE_TYPES = [
        ['ANNUAL', 'Annual Leave', 21, true],
        ['SICK', 'Sick Leave', 30, true],
        ['URGENT', 'Urgent / Personal Leave', 5, true],
        ['UNPAID', 'Unpaid Leave', 30, false],
    ];

    public function run(): void
    {
        $created = 0;

        foreach (self::LEAVE_TYPES as [$code, $name, $days, $paid]) {
            $type = LeaveType::firstOrNew(['code' => $code]);

            if (! $type->exists) {
                $type->fill(['name' => $name, 'max_days_per_year' => $days, 'is_paid' => $paid, 'status' => 'active'])->save();
                $created++;
            }
        }

        $this->command?->info(sprintf('Leave types ready: %d defined (%d added).', LeaveType::count(), $created));
    }
}
