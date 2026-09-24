<?php

namespace Tests\Feature;

use App\Models\LeaveType;
use App\Models\Shift;
use Database\Seeders\ProductionEmployeeMastersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionEmployeeMastersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_fill_empty_catalogues_and_reruns_do_not_create_transactions(): void
    {
        $this->seed(ProductionEmployeeMastersSeeder::class);
        $this->seed(ProductionEmployeeMastersSeeder::class);
        $this->assertSame(3, Shift::count());
        $this->assertSame(4, LeaveType::count());
        $this->assertDatabaseHas('shifts', ['code' => 'NIGHT', 'end_time' => '05:00', 'status' => 'active']);
        foreach (['users', 'employees', 'employee_shift_assignments', 'attendance_records', 'leave_requests', 'payroll_runs', 'salary_structures'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table.' must remain untouched');
        }
    }

    public function test_customized_and_inactive_defaults_are_not_overwritten_or_reactivated(): void
    {
        $shift = Shift::create(ProductionEmployeeMastersSeeder::SHIFTS[0] + ['status' => 'inactive']);
        $shift->update(['name' => 'Our Day Shift', 'start_time' => '09:00', 'grace_minutes' => 0]);
        $leave = LeaveType::create(['code' => 'ANNUAL', 'name' => 'Our annual policy', 'max_days_per_year' => 28, 'is_paid' => false, 'status' => 'inactive']);
        $beforeShift = $shift->fresh()->getAttributes();
        $beforeLeave = $leave->fresh()->getAttributes();
        $this->seed(ProductionEmployeeMastersSeeder::class);
        $this->seed(ProductionEmployeeMastersSeeder::class);
        $this->assertSame($beforeShift, $shift->fresh()->getAttributes());
        $this->assertSame($beforeLeave, $leave->fresh()->getAttributes());
        $this->assertSame(3, Shift::count());
        $this->assertSame(4, LeaveType::count());
    }
}
