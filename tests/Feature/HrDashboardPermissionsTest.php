<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrDashboardPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_super_admin_sees_real_attendance_summary_and_no_fake_chart(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.hr.dashboard'))->assertOk()
            ->assertSee('Present Today')
            ->assertSee('Current Month Payroll')
            ->assertSee('Recorded attendance and approved leave counts for today')
            ->assertDontSee('Chart Placeholder: Present / Late / Absent / Leave trend');
    }

    public function test_hr_user_without_attendance_or_payroll_view_cannot_see_sensitive_cards_or_links(): void
    {
        $role = Role::where('code', 'HR_MANAGER')->firstOrFail();
        $user = $role->users()->firstOrFail();
        $this->assertTrue($user->hasPermission('HR', 'view'));
        $role->permissions()->detach(Permission::whereIn('module', ['Attendance', 'Payroll'])->pluck('id'));

        $this->actingAs($user->fresh())->get(route('admin.hr.dashboard'))->assertOk()
            ->assertSee('Attendance figures are hidden without Attendance view permission.')
            ->assertDontSee('Present Today')
            ->assertDontSee('Late Today')
            ->assertDontSee('Absent Today')
            ->assertDontSee('Current Month Payroll')
            ->assertDontSee('Payroll Approval')
            ->assertDontSee('Pending EOSB')
            ->assertDontSee('Open Attendance')
            ->assertDontSee('Chart Placeholder');
    }

    public function test_arabic_attendance_permission_notice_and_existing_dashboard_labels(): void
    {
        $role = Role::where('code', 'HR_MANAGER')->firstOrFail();
        $user = $role->users()->firstOrFail();
        $user->update(['language' => 'Arabic']);
        $role->permissions()->detach(Permission::where('module', 'Attendance')->pluck('id'));

        $this->actingAs($user->fresh())->get(route('admin.hr.dashboard'))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('بيانات الحضور مخفية دون صلاحية العرض.')
            ->assertDontSee('Chart Placeholder');
    }
}
