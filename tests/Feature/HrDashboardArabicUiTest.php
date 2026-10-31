<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HR-03: the HR dashboard renders fully in Arabic without changing its English copy,
 * its figures or the Attendance / Payroll visibility rules.
 */
class HrDashboardArabicUiTest extends TestCase
{
    use RefreshDatabase;

    /** Metric-card labels, in page order. */
    private const METRIC_LABELS = [
        'Total Employees', 'Active Employees', 'Present Today', 'Late Today',
        'On Leave Today', 'Pending Leaves', 'Pending Overtime', 'Current Month Payroll',
        'Expiring IQAMAs (60 days)', 'Expiring Documents (60 days)', 'Pending EOSB', 'Absent Today',
    ];

    private const TABLE_TITLES = ["Today's Attendance Summary", 'Pending HR Approvals', 'IQAMA Expiring Soon', 'Latest Leave Requests'];

    private const HEADERS = ['Status', 'Employees', 'Queue', 'Count', 'Employee', 'Department', 'Expiry', 'Leave Type', 'Start', 'End', 'Days'];

    private const QUEUES = ['Leave Requests', 'Overtime Claims', 'Document Expiry', 'Payroll Approval', 'End of Service'];

    private const ATTENDANCE_LABELS = ['Present Today', 'Late Today', 'Absent Today', 'Open Attendance'];

    private const PAYROLL_LABELS = ['Pending Overtime', 'Current Month Payroll', 'Pending EOSB', 'Overtime Claims', 'Payroll Approval', 'End of Service'];

    private array $ar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->ar = json_decode(file_get_contents(lang_path('ar.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    private function ar(string $key): string
    {
        $this->assertArrayHasKey($key, $this->ar, "Arabic dictionary entry missing for [$key]");
        $this->assertNotSame($key, $this->ar[$key], "Arabic entry for [$key] is still English");

        return $this->ar[$key];
    }

    public function test_english_dashboard_copy_is_unchanged(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.hr.dashboard'))->assertOk()
            ->assertSee('<title>HR Dashboard', false)
            ->assertSee('HR &amp; Payroll / HR Dashboard', false)
            ->assertSee('Employees, attendance, leaves, overtime, documents and payroll at a glance')
            ->assertSee('+ Manual Attendance')
            ->assertSee('+ Add Employee')
            ->assertSee('Open Attendance')
            ->assertSee('View All')
            ->assertSee('Recorded attendance and approved leave counts for today');

        foreach (array_merge(self::METRIC_LABELS, self::TABLE_TITLES, self::QUEUES) as $label) {
            $response->assertSee($label);
        }
        foreach (self::HEADERS as $header) {
            $response->assertSee('<th>'.$header.'</th>', false);
        }
    }

    public function test_super_admin_sees_the_dashboard_fully_in_arabic(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);

        $response = $this->actingAs($admin->fresh())->get(route('admin.hr.dashboard'))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('<title>'.$this->ar('HR Dashboard'), false)
            ->assertSee(e($this->ar('HR & Payroll / HR Dashboard')), false)
            ->assertSee($this->ar('Employees, attendance, leaves, overtime, documents and payroll at a glance'))
            ->assertSee($this->ar('+ Manual Attendance'))
            ->assertSee($this->ar('+ Add Employee'))
            ->assertSee($this->ar('Open Attendance'))
            ->assertSee($this->ar('View All'))
            ->assertSee($this->ar('Recorded attendance and approved leave counts for today; not a historical trend or payroll calculation.'));

        foreach (array_merge(self::METRIC_LABELS, self::TABLE_TITLES, self::QUEUES) as $label) {
            $response->assertSee($this->ar($label))->assertDontSee('>'.$label.'<', false);
        }
        foreach (self::HEADERS as $header) {
            $response->assertSee('<th>'.$this->ar($header).'</th>', false)->assertDontSee('<th>'.$header.'</th>', false);
        }
        // Status badges translate through the shared component.
        foreach (['Present', 'Late', 'Absent', 'On leave'] as $status) {
            $response->assertSee($this->ar($status));
        }
        // Figures are locale independent: the current payroll run total is still rendered as a SAR amount.
        $response->assertSee('SAR ');
    }

    public function test_restricted_hr_manager_sees_no_attendance_or_payroll_copy_in_either_language(): void
    {
        $role = Role::where('code', 'HR_MANAGER')->firstOrFail();
        $user = $role->users()->firstOrFail();
        $user->update(['language' => 'Arabic']);
        $role->permissions()->detach(Permission::whereIn('module', ['Attendance', 'Payroll'])->pluck('id'));

        $response = $this->actingAs($user->fresh())->get(route('admin.hr.dashboard'))->assertOk()
            ->assertSee($this->ar('Attendance figures are hidden without Attendance view permission.'))
            ->assertDontSee('SAR ')
            ->assertSee($this->ar('Total Employees'))
            ->assertSee($this->ar('Pending Leaves'))
            ->assertSee($this->ar('Leave Requests'))
            ->assertSee($this->ar('Document Expiry'));

        foreach (array_merge(self::ATTENDANCE_LABELS, self::PAYROLL_LABELS) as $label) {
            $response->assertDontSee($this->ar($label))->assertDontSee('>'.$label.'<', false);
        }
    }

    public function test_empty_states_render_in_arabic(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        LeaveRequest::query()->delete();
        Employee::query()->update(['iqama_expiry_date' => null]);

        $this->actingAs($admin->fresh())->get(route('admin.hr.dashboard'))->assertOk()
            ->assertSee($this->ar('No IQAMA expiring in the next 60 days.'))
            ->assertSee($this->ar('No leave requests yet.'))
            ->assertDontSee('No IQAMA expiring')
            ->assertDontSee('No leave requests yet');
    }
}
