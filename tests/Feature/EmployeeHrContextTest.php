<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRecord;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Support\SaveAction;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Wave 2 Batch D: the Employee View is a read-only HR context page. Attendance,
 * leave, overtime, salary and payroll sections appear only with their own
 * module permission, show only this employee's scoped records, paginate, and
 * link to the registers with a safe way back; the registers and the Edit
 * workspace panels carry the same context. No HR calculation is changed.
 */
class EmployeeHrContextTest extends TestCase
{
    use RefreshDatabase;

    protected Project $mine;

    protected Project $theirs;

    protected Employee $employee;

    protected Employee $other;

    protected LeaveType $annual;

    private int $userSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->mine = Project::create(['name' => 'Riyadh Commercial Tower', 'code' => 'PRJ-HR-A', 'status' => 'active']);
        $this->theirs = Project::create(['name' => 'Jeddah Warehouse', 'code' => 'PRJ-HR-B', 'status' => 'active']);
        $this->employee = Employee::create([
            'employee_code' => 'EMP-HR-A', 'first_name' => 'Ahmed', 'last_name' => 'Hassan', 'email' => 'ahmed.hr@example.test', 'status' => 'active',
            'basic_salary' => 6500, 'housing_allowance' => 1500, 'transport_allowance' => 500, 'employee_classification' => 'Sponsorship',
            'joining_date' => '2024-03-01', 'contract_type' => 'Full Time', 'contract_start_date' => '2024-03-01', 'contract_end_date' => now()->addDays(20)->toDateString(),
            'iqama_number' => '2412345678', 'iqama_expiry_date' => now()->addDays(30)->toDateString(), 'annual_leave_entitlement' => 21,
            'project_id' => $this->mine->id,
        ]);
        $this->other = Employee::create([
            'employee_code' => 'EMP-HR-B', 'first_name' => 'Khalid', 'last_name' => 'Otaibi', 'email' => 'khalid.hr@example.test', 'status' => 'active',
            'basic_salary' => 9000, 'employee_classification' => 'Sponsorship', 'joining_date' => '2024-01-01', 'project_id' => $this->theirs->id,
        ]);
        $this->annual = LeaveType::firstOrCreate(['code' => 'ANNUAL'], ['name' => 'Annual Leave', 'max_days_per_year' => 21, 'is_paid' => true, 'status' => 'active']);
    }

    // ---------------------------------------------------------------- helpers

    protected function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /** @param array<string, array<int, string>> $grants module => actions */
    protected function user(array $grants, ?Project $project = null, string $suffix = 'u'): User
    {
        $suffix .= '-'.++$this->userSeq;
        $role = Role::create(['name' => 'HRC role '.$suffix, 'code' => 'HRC_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'HRC '.$suffix, 'email' => 'hrc-'.$suffix.'@example.test', 'username' => 'hrc.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    protected function hrViewer(?Project $project = null): User
    {
        return $this->user(['HR' => ['view']], $project, 'hr');
    }

    protected function fullReader(?Project $project = null): User
    {
        return $this->user(['HR' => ['view'], 'Attendance' => ['view'], 'Payroll' => ['view'], 'Activity Logs' => ['view']], $project, 'full');
    }

    protected function attendance(Employee $employee, string $date, array $extra = []): AttendanceRecord
    {
        return AttendanceRecord::create($extra + [
            'employee_id' => $employee->id, 'project_id' => $employee->project_id, 'attendance_date' => $date, 'check_in' => '07:05', 'check_out' => '16:10',
            'late_minutes' => 5, 'overtime_minutes' => 0, 'status' => 'present', 'source' => 'manual', 'geofence_status' => 'unknown',
        ]);
    }

    protected function leave(Employee $employee, string $start, string $end, string $status = 'pending', float $days = 5): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $this->annual->id, 'start_date' => $start, 'end_date' => $end, 'total_days' => $days,
            'reason' => 'Family visit', 'status' => $status,
        ]);
    }

    protected function overtime(Employee $employee, string $date, float $hours = 2, float $rate = 50, string $status = 'approved'): OvertimeRecord
    {
        return OvertimeRecord::create(['employee_id' => $employee->id, 'overtime_date' => $date, 'hours' => $hours, 'rate' => $rate, 'amount' => $hours * $rate, 'status' => $status, 'reason' => 'Night pour']);
    }

    protected function payroll(Employee $employee, int $month, float $net = 8200): PayrollRunItem
    {
        $run = PayrollRun::create(['code' => 'PAY-2026-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-'.$employee->id, 'payroll_month' => $month, 'payroll_year' => 2026, 'period_start' => "2026-0{$month}-01", 'period_end' => "2026-0{$month}-28", 'status' => 'approved', 'total_employees' => 1, 'gross_amount' => 8500, 'total_deductions' => 300, 'net_amount' => $net]);

        return PayrollRunItem::create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'basic_salary' => 6500, 'total_allowances' => 2000, 'overtime_amount' => 100, 'total_deductions' => 300, 'gross_amount' => 8600, 'net_amount' => $net, 'present_days' => 22, 'leave_days' => 0]);
    }

    protected function open(User $user, ?Employee $employee = null, array $query = []): TestResponse
    {
        return $this->actingAs($user)->get(route('admin.hr.employees.show', ['employee' => $employee ?? $this->employee] + $query));
    }

    // ------------------------------------------------------------------ tests

    public function test_view_shows_every_hr_section_for_a_full_reader_and_writes_nothing(): void
    {
        $this->attendance($this->employee, now()->toDateString());
        $this->leave($this->employee, now()->addDays(10)->toDateString(), now()->addDays(14)->toDateString());
        $this->overtime($this->employee, now()->toDateString());
        $this->payroll($this->employee, 9);
        $reader = $this->fullReader();

        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $page = $this->open($reader)->assertOk();
        $this->assertSame([], $writes, 'a GET of the employee view must not write to the database');

        $page->assertSee('Read-only employee view');
        foreach (['overview', 'documents', 'attendance', 'leaves', 'overtime', 'salary', 'payroll', 'activity'] as $anchor) {
            $page->assertSee('id="'.$anchor.'"', false);
        }
        $page->assertSee('EMP-HR-A — Ahmed Hassan')->assertSee('Contract &amp; IQAMA', false)->assertSee('2412345678')
            ->assertDontSee('name="'.SaveAction::FIELD.'"', false)->assertDontSee('Edit Employee')->assertDontSee('+ Attach Document');
        preg_match_all('/<form[^>]+action="([^"]*)"/', $page->getContent(), $forms);
        foreach ($forms[1] as $action) {
            $this->assertStringNotContainsString('/admin/hr/', $action, 'the view page must carry no HR data form');
        }
    }

    public function test_attendance_section_needs_attendance_view_and_shows_only_this_employee(): void
    {
        $shift = Shift::create(['name' => 'Tower Day Shift', 'code' => 'HRC-DAY', 'start_time' => '07:00', 'end_time' => '16:00', 'status' => 'active']);
        $site = Site::create(['name' => 'Riyadh Tower - Main Site', 'code' => 'SITE-HR-A', 'project_id' => $this->mine->id, 'status' => 'active']);
        $this->attendance($this->employee, now()->toDateString(), ['shift_id' => $shift->id, 'site_id' => $site->id, 'overtime_minutes' => 30, 'status' => 'late']);
        $this->attendance($this->other, now()->toDateString(), ['check_in' => '09:59']);

        $page = $this->open($this->fullReader())->assertOk();
        $page->assertSee('id="attendance"', false)->assertSeeInOrder(['07:05', '16:10', 'Tower Day Shift', '5 min', '30 min', 'Riyadh Commercial Tower', 'Riyadh Tower - Main Site'])
            ->assertSee('Geo-Fence')->assertSee('offline attendance is not yet operational')
            ->assertDontSee('09:59')->assertSee(route('admin.hr.attendance.index', ['employee' => $this->employee->id]));

        $this->open($this->hrViewer())->assertOk()->assertDontSee('id="attendance"', false)->assertDontSee('07:05')->assertDontSee('Attendance this month');
    }

    public function test_leave_section_uses_the_existing_balance_and_shows_only_this_employee(): void
    {
        $this->leave($this->employee, '2026-02-02', '2026-02-06', 'approved', 5);
        $this->leave($this->employee, now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString(), 'pending', 3);
        $this->leave($this->other, '2026-03-02', '2026-03-03', 'approved', 2);
        $expected = $this->employee->leaveBalance();

        $page = $this->open($this->hrViewer())->assertOk();
        $page->assertSee('id="leaves"', false)->assertSee('Annual Leave')->assertSee('Family visit')
            ->assertSee('<strong>'.$expected['entitlement'].'</strong> days', false)
            ->assertSee(rtrim(rtrim(number_format($expected['used'], 1), '0'), '.').' days')
            ->assertSee(rtrim(rtrim(number_format($expected['pending'], 1), '0'), '.').' days')
            ->assertSee(route('admin.hr.leaves.index', ['employee' => $this->employee->id]))
            ->assertDontSee('2026-03-02')->assertDontSee('Create leave');
        $this->assertSame(5.0, $expected['used']);
        $this->assertSame(3.0, $expected['pending']);
        $this->assertSame(16.0, $expected['remaining']);

        $creator = $this->user(['HR' => ['view', 'create', 'edit']], suffix: 'hrc');
        $html = html_entity_decode($this->open($creator)->assertOk()->getContent());
        $this->assertStringContainsString(route('admin.hr.leaves.create', ['employee' => $this->employee->id, 'return_to' => '/admin/hr/employees/'.$this->employee->id.'#leaves']), $html);
    }

    public function test_overtime_section_needs_payroll_view_and_uses_stored_amounts(): void
    {
        $this->overtime($this->employee, now()->toDateString(), 2, 50, 'approved');
        $this->overtime($this->employee, now()->subDay()->toDateString(), 1.5, 50, 'pending');
        $this->overtime($this->other, now()->toDateString(), 8, 100, 'approved');

        $page = $this->open($this->fullReader())->assertOk();
        $page->assertSee('id="overtime"', false)->assertSee('SAR 100.00')->assertSee('SAR 75.00')->assertSee('Night pour')
            ->assertSee('Approved hours: 2')->assertDontSee('SAR 800.00')->assertSee(route('admin.hr.overtime.index', ['employee' => $this->employee->id]));

        $this->open($this->hrViewer())->assertOk()->assertDontSee('id="overtime"', false)->assertDontSee('Night pour');
    }

    public function test_payroll_section_is_hidden_without_payroll_permission_and_shows_this_employees_rows(): void
    {
        $this->payroll($this->employee, 9, 8200);
        $this->payroll($this->other, 9, 12345.67);

        $viewer = $this->hrViewer();
        $page = $this->open($viewer)->assertOk();
        foreach (['id="payroll"', 'id="salary"'] as $anchor) {
            $page->assertDontSee($anchor, false);
        }
        $page->assertDontSee('Payroll Information')->assertDontSee('SAR 6,500.00')->assertDontSee('SAR 8,200.00')->assertDontSee('Basic Salary');

        $page = $this->open($this->fullReader())->assertOk();
        $page->assertSee('id="payroll"', false)->assertSee('id="salary"', false)->assertSee('Payroll Information')->assertSee('SAR 6,500.00')
            ->assertSee('SAR 8,200.00')->assertSee('September 2026')->assertSee('22 / 0')->assertSee('Open payroll run')
            ->assertDontSee('12,345.67')->assertSee('no bank / WPS file or GOSI');
    }

    public function test_contract_and_iqama_summary_comes_from_existing_fields_and_documents(): void
    {
        EmployeeDocument::create(['employee_id' => $this->employee->id, 'document_type' => 'Passport', 'document_number' => 'P123', 'expiry_date' => now()->subDay()->toDateString(), 'status' => 'active']);
        EmployeeDocument::create(['employee_id' => $this->employee->id, 'document_type' => 'Contract', 'document_number' => 'C1', 'expiry_date' => now()->addYears(2)->toDateString(), 'status' => 'active']);

        $page = $this->open($this->hrViewer())->assertOk();
        $page->assertSee('IQAMA expires within 60 days.')->assertSee('Contract ends on '.$this->employee->contract_end_date->toDateString().'.')
            ->assertSee('2 on file · 0 expiring within 60 days · 1 expired')->assertSee('Full Time')->assertSee('Expiring soon')
            ->assertSee(route('admin.hr.documents.index', ['employee' => $this->employee->id]));
    }

    public function test_activity_section_follows_the_activity_permission_and_visibility(): void
    {
        $this->actingAs($this->admin())->post(route('admin.hr.overtime.store'), [
            'employee_id' => $this->employee->id, 'overtime_date' => now()->toDateString(), 'hours' => 1, 'rate' => 40, 'status' => 'pending',
        ])->assertSessionHasNoErrors();
        $this->flushSession();

        $this->open($this->admin())->assertOk()->assertSee('id="activity"', false)->assertSee('Created overtime record');
        $this->open($this->fullReader())->assertOk()->assertSee('id="activity"', false)->assertDontSee('Created overtime record');
        $this->open($this->hrViewer())->assertOk()->assertDontSee('id="activity"', false);
    }

    public function test_scope_denies_employees_outside_the_users_project_and_registers_hide_foreign_rows(): void
    {
        $this->attendance($this->employee, now()->toDateString());
        $this->attendance($this->other, now()->toDateString(), ['check_in' => '09:59']);
        $scoped = $this->fullReader($this->mine);

        $this->open($scoped, $this->other)->assertNotFound();
        $this->open($scoped)->assertOk()->assertSee('07:05')->assertDontSee('09:59');
        // The register with a foreign employee filter returns nothing it may not show.
        $this->actingAs($scoped)->get(route('admin.hr.attendance.index', ['employee' => $this->other->id]))->assertOk()->assertDontSee('09:59')->assertDontSee('EMP-HR-B');
        $this->actingAs($scoped)->get(route('admin.hr.attendance.index', ['employee' => $this->employee->id]))->assertOk()->assertSee('Showing records of EMP-HR-A - Ahmed Hassan only.')->assertSee('07:05');
    }

    public function test_sections_paginate_ten_rows_and_link_to_the_filtered_registers(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->attendance($this->employee, now()->subDays($i)->toDateString(), ['check_in' => '0'.($i % 7 + 1).':1'.($i % 10)]);
        }
        $page = $this->open($this->fullReader())->assertOk();
        $page->assertSee('Showing the latest 10 of 11')->assertSee('page_attendance=2');
        $this->open($this->fullReader(), null, ['page_attendance' => 2])->assertOk()->assertSee('Showing the latest 1 of 11');

        $this->actingAs($this->admin())->get(route('admin.hr.leaves.index', ['employee' => $this->employee->id]))->assertOk()->assertSee('Showing records of EMP-HR-A');
        $this->actingAs($this->admin())->get(route('admin.hr.overtime.index', ['employee' => $this->employee->id]))->assertOk()->assertSee('Showing records of EMP-HR-A');
    }

    public function test_register_forms_return_to_the_employee_and_prefill_the_employee(): void
    {
        $origin = '/admin/hr/employees/'.$this->employee->id.'#attendance';

        $this->actingAs($this->admin())->get(route('admin.hr.attendance.create', ['employee' => $this->employee->id, 'return_to' => $origin]))
            ->assertOk()->assertSee('name="_return_to" value="'.$origin.'"', false)->assertSee('value="'.$this->employee->id.'" selected', false)->assertSee('Save &amp; close', false);

        $payload = ['employee_id' => $this->employee->id, 'attendance_date' => now()->toDateString(), 'check_in' => '08:00', 'check_out' => '17:00', 'late_minutes' => 0, 'overtime_minutes' => 0, 'status' => 'present', 'source' => 'manual', 'geofence_status' => 'unknown'];
        $this->actingAs($this->admin())->post(route('admin.hr.attendance.store'), $payload + ['_save_action' => 'close', '_return_to' => $origin])->assertSessionHasNoErrors()->assertRedirect($origin);
        $record = AttendanceRecord::where('employee_id', $this->employee->id)->firstOrFail();
        $this->actingAs($this->admin())->put(route('admin.hr.attendance.update', $record), $payload + ['_save_action' => 'stay', '_return_to' => $origin])
            ->assertRedirect(route('admin.hr.attendance.edit', [$record, 'return_to' => $origin]));
        $this->actingAs($this->admin())->get(route('admin.hr.attendance.edit', [$record, 'return_to' => $origin]))->assertOk()->assertSee('href="'.$origin.'"', false)->assertSee('Back to origin');
        // Off-site origins are ignored; without an origin the register is the destination.
        $this->actingAs($this->admin())->put(route('admin.hr.attendance.update', $record), $payload + ['_return_to' => 'https://evil.example/'])->assertRedirect(route('admin.hr.attendance.index'));

        $leaveOrigin = '/admin/hr/employees/'.$this->employee->id.'#leaves';
        $this->actingAs($this->admin())->post(route('admin.hr.leaves.store'), [
            'employee_id' => $this->employee->id, 'leave_type_id' => $this->annual->id, 'start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(4)->toDateString(),
            'status' => 'pending', 'reason' => 'Trip', '_save_action' => 'stay', '_return_to' => $leaveOrigin,
        ])->assertSessionHasNoErrors();
        $leave = LeaveRequest::where('employee_id', $this->employee->id)->latest('id')->firstOrFail();
        $this->actingAs($this->admin())->get(route('admin.hr.leaves.show', [$leave, 'return_to' => $leaveOrigin]))->assertOk()->assertSee('href="'.$leaveOrigin.'"', false)->assertSee('Back to origin');

        $otOrigin = '/admin/hr/employees/'.$this->employee->id.'#overtime';
        $this->actingAs($this->admin())->post(route('admin.hr.overtime.store'), [
            'employee_id' => $this->employee->id, 'overtime_date' => now()->toDateString(), 'hours' => 2, 'rate' => 30, 'status' => 'pending', '_save_action' => 'close', '_return_to' => $otOrigin,
        ])->assertSessionHasNoErrors()->assertRedirect($otOrigin);
        $this->assertSame(60.0, (float) OvertimeRecord::where('employee_id', $this->employee->id)->value('amount'));
    }

    public function test_edit_workspace_panels_show_context_columns_and_register_links(): void
    {
        $shift = Shift::create(['name' => 'Tower Night Shift', 'code' => 'HRC-NIGHT', 'start_time' => '19:00', 'end_time' => '04:00', 'status' => 'active']);
        $this->attendance($this->employee, now()->toDateString(), ['shift_id' => $shift->id, 'late_minutes' => 12]);
        $this->leave($this->employee, now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString(), 'pending', 3);
        $this->payroll($this->employee, 8);

        $panel = fn (string $key) => $this->actingAs($this->admin())->getJson(route('admin.hr.employees.workspace.panel', [$this->employee, $key]))->assertOk()->json('html');
        $attendance = $panel('attendance');
        $this->assertStringContainsString('Tower Night Shift', $attendance);
        $this->assertStringContainsString('Riyadh Commercial Tower', $attendance);
        $this->assertStringContainsString('Late (min)', $attendance);
        $this->assertStringContainsString(route('admin.hr.attendance.index', ['employee' => $this->employee->id]), html_entity_decode($attendance));
        $this->assertStringContainsString('Annual Leave', $panel('leaves'));
        $payroll = $panel('payroll-history');
        $this->assertStringContainsString('August 2026', $payroll);
        $this->assertStringContainsString('Run Status', $payroll);

        // Panels still refuse the modules the viewer lacks.
        $this->actingAs($this->hrViewer())->getJson(route('admin.hr.employees.workspace.panel', [$this->employee, 'payroll-history']))->assertForbidden();
        $this->actingAs($this->hrViewer())->getJson(route('admin.hr.employees.workspace.panel', [$this->employee, 'attendance']))->assertForbidden();
    }
}
