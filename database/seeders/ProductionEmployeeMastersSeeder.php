<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Opt-in master defaults only. Never run the demo DatabaseSeeder in production. */
class ProductionEmployeeMastersSeeder extends Seeder
{
    // Existing Phase 3 defaults, not newly inferred company policy.
    public const SHIFTS = [
        ['code' => 'DAY', 'name' => 'Day Shift', 'start_time' => '08:00', 'end_time' => '17:00', 'break_minutes' => 60, 'grace_minutes' => 10, 'overtime_after_minutes' => 540],
        ['code' => 'NIGHT', 'name' => 'Night Shift', 'start_time' => '20:00', 'end_time' => '05:00', 'break_minutes' => 60, 'grace_minutes' => 15, 'overtime_after_minutes' => 540],
        ['code' => 'SPLIT', 'name' => 'Split Shift', 'start_time' => '07:00', 'end_time' => '19:00', 'break_minutes' => 180, 'grace_minutes' => 10, 'overtime_after_minutes' => 540],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->call(ProductionHrDefaultsSeeder::class);
            $created = 0;
            foreach (self::SHIFTS as $defaults) {
                $shift = Shift::firstOrCreate(['code' => $defaults['code']], $defaults + ['status' => 'active']);
                $created += (int) $shift->wasRecentlyCreated;
            }
            $this->command?->info("Shift defaults: {$created} added; existing records unchanged.");
        });
        $this->command?->warn('Review shift times, breaks, overtime thresholds and leave policy before use. No employees were assigned and no transactions were created.');
    }
}
