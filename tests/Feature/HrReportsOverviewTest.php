<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrReportsOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_super_admin_sees_real_scoped_workforce_and_monthly_counts(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $month = now()->format('Y-m');
        $expectedActive = Employee::where('status', 'active')->count();
        $expectedPending = LeaveRequest::whereBetween('start_date', [
            now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(),
        ])->where('status', 'pending')->count();

        $this->actingAs($admin)->get(route('admin.reports.hr.index', ['month' => $month]))
            ->assertOk()
            ->assertSee('HR Reports')
            ->assertSee('Active employees in scope')
            ->assertSee((string) $expectedActive)
            ->assertSee((string) $expectedPending)
            ->assertSee('Attendance records by status')
            ->assertSee('name="month"', false);
    }

    public function test_arabic_report_uses_translated_labels_and_same_month_input(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);

        $this->actingAs($admin)->get(route('admin.reports.hr.index'))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('التقارير / تقارير الموارد البشرية')
            ->assertSee('طلبات الإجازة حسب شهر البدء')
            ->assertSee('سجلات الحضور حسب الحالة')
            ->assertSee('name="month"', false);
    }

    public function test_hr_manager_without_attendance_permission_cannot_see_attendance_figures(): void
    {
        $role = Role::where('code', 'HR_MANAGER')->firstOrFail();
        $user = $role->users()->firstOrFail();
        $role->permissions()->detach(Permission::where('module', 'Attendance')->pluck('id'));

        $this->actingAs($user->fresh())->get(route('admin.reports.hr.index'))->assertOk()
            ->assertSee('Attendance figures are hidden because you do not have Attendance view permission.')
            ->assertDontSee('Attendance records by status')
            ->assertDontSee(route('admin.hr.attendance.index'));
    }

    public function test_non_hr_user_is_denied_and_bad_month_is_rejected(): void
    {
        $actor = Role::where('code', 'PURCHASE_MANAGER')->firstOrFail()->users()->firstOrFail();
        $this->actingAs($actor)->get(route('admin.reports.hr.index'))->assertForbidden();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.reports.hr.index', ['month' => '2026-13']))
            ->assertSessionHasErrors('month');
    }

    public function test_report_does_not_emit_payroll_values_or_sensitive_identifiers(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $response = $this->actingAs($admin)->get(route('admin.reports.hr.index'))->assertOk();
        $response->assertDontSee('IBAN')
            ->assertDontSee('Basic Salary')
            ->assertDontSee('Payroll Amount')
            ->assertSee('Official GOSI, WPS/Mudad and payroll financial reports are not provided');
    }
}
