<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EndOfServiceRecord;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRecord;
use App\Models\PayrollRun;
use App\Models\SalaryStructure;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_hr_screens_return_ok(): void
    {
        $employee = Employee::firstOrFail();
        $document = EmployeeDocument::firstOrFail();
        $shift = Shift::firstOrFail();
        $attendance = AttendanceRecord::firstOrFail();
        $leave = LeaveRequest::firstOrFail();
        $overtime = OvertimeRecord::firstOrFail();
        $structure = SalaryStructure::firstOrFail();
        $run = PayrollRun::firstOrFail();
        $eosb = EndOfServiceRecord::firstOrFail();

        $urls = [
            '/admin/hr/dashboard',
            '/admin/hr/employees', '/admin/hr/employees/create', "/admin/hr/employees/{$employee->id}", "/admin/hr/employees/{$employee->id}/edit",
            '/admin/hr/documents', '/admin/hr/documents/create', "/admin/hr/documents/{$document->id}/edit",
            '/admin/hr/shifts', '/admin/hr/shifts/create', "/admin/hr/shifts/{$shift->id}/edit",
            '/admin/hr/attendance', '/admin/hr/attendance/create', "/admin/hr/attendance/{$attendance->id}/edit",
            '/admin/hr/leaves', '/admin/hr/leaves/create', "/admin/hr/leaves/{$leave->id}", "/admin/hr/leaves/{$leave->id}/edit",
            '/admin/hr/overtime', '/admin/hr/overtime/create', "/admin/hr/overtime/{$overtime->id}/edit",
            '/admin/hr/salary-structures', '/admin/hr/salary-structures/create', "/admin/hr/salary-structures/{$structure->id}", "/admin/hr/salary-structures/{$structure->id}/edit",
            '/admin/hr/payroll', '/admin/hr/payroll/create', "/admin/hr/payroll/{$run->id}", "/admin/hr/payroll/{$run->id}/edit",
            '/admin/hr/eosb', '/admin/hr/eosb/create', "/admin/hr/eosb/{$eosb->id}", "/admin/hr/eosb/{$eosb->id}/edit",
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->admin())->get($url)->assertOk();
        }
    }

    public function test_hr_routes_require_authentication(): void
    {
        $this->get('/admin/hr/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/hr/employees')->assertRedirect(route('login'));
    }

    public function test_hr_dashboard_shows_employee_metrics(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.hr.dashboard'))
            ->assertOk()
            ->assertSee('Total Employees')
            ->assertSee('Expiring IQAMAs (60 days)');
    }

    public function test_employee_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.hr.employees.store'), [
                'employee_code' => 'EMP-900',
                'first_name' => 'Test',
                'last_name' => 'Worker',
                'contract_type' => 'Full Time',
                'basic_salary' => 5000,
                'payment_method' => 'Bank Transfer',
                'status' => 'active',
                'mobile_access' => 1,
            ])
            ->assertRedirect(route('admin.hr.employees.index'));

        $this->assertDatabaseHas('employees', ['employee_code' => 'EMP-900', 'first_name' => 'Test']);
        $this->assertDatabaseHas('activity_logs', ['module' => 'HR', 'action' => 'Created employee']);
    }

    public function test_employee_code_must_be_unique(): void
    {
        $existing = Employee::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.hr.employees.store'), [
                'employee_code' => $existing->employee_code,
                'first_name' => 'Duplicate',
                'contract_type' => 'Full Time',
                'basic_salary' => 1000,
                'payment_method' => 'Cash',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('employee_code');
    }

    public function test_employee_can_be_updated(): void
    {
        $employee = Employee::firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.hr.employees.update', $employee), [
                'employee_code' => $employee->employee_code,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'contract_type' => 'Contract',
                'basic_salary' => 12345,
                'payment_method' => 'Cash',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.hr.employees.index'));

        $this->assertSame('12345.00', (string) $employee->refresh()->basic_salary);
    }

    public function test_employee_delete_deactivates_instead_of_removing(): void
    {
        $employee = Employee::where('status', 'active')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.hr.employees.destroy', $employee))
            ->assertRedirect(route('admin.hr.employees.index'));

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'status' => 'inactive']);
    }

    public function test_document_can_be_created_and_expiry_status_is_visible(): void
    {
        $employee = Employee::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.hr.documents.store'), [
                'employee_id' => $employee->id,
                'document_type' => 'IQAMA',
                'document_number' => '2451234567',
                'issue_date' => now()->subYear()->toDateString(),
                'expiry_date' => now()->addDays(20)->toDateString(),
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.hr.documents.index'));

        $this->assertDatabaseHas('employee_documents', ['document_number' => '2451234567']);

        $this->actingAs($this->admin())
            ->get(route('admin.hr.documents.index', ['validity' => 'expiring']))
            ->assertOk()
            ->assertSee('Expiring soon');
    }

    public function test_shift_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.hr.shifts.store'), [
                'name' => 'Evening Shift',
                'code' => 'EVE',
                'start_time' => '14:00',
                'end_time' => '23:00',
                'break_minutes' => 45,
                'grace_minutes' => 10,
                'overtime_after_minutes' => 540,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.hr.shifts.index'));

        $this->assertDatabaseHas('shifts', ['code' => 'EVE']);
    }

    public function test_attendance_can_be_created_with_source_and_geofence(): void
    {
        $employee = Employee::firstOrFail();
        $shift = Shift::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.hr.attendance.store'), [
                'employee_id' => $employee->id,
                'shift_id' => $shift->id,
                'attendance_date' => now()->addDays(30)->toDateString(),
                'check_in' => '08:05',
                'check_out' => '17:20',
                'late_minutes' => 0,
                'overtime_minutes' => 20,
                'status' => 'present',
                'source' => 'mobile',
                'geofence_status' => 'outside',
            ])
            ->assertRedirect(route('admin.hr.attendance.index'));

        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $employee->id,
            'source' => 'mobile',
            'geofence_status' => 'outside',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.hr.attendance.index'))
            ->assertOk()
            ->assertSee('Mobile')
            ->assertSee('Outside');
    }

    public function test_attendance_rejects_duplicate_date_for_same_employee(): void
    {
        $record = AttendanceRecord::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.hr.attendance.store'), [
                'employee_id' => $record->employee_id,
                'attendance_date' => $record->attendance_date->toDateString(),
                'late_minutes' => 0,
                'overtime_minutes' => 0,
                'status' => 'present',
                'source' => 'manual',
                'geofence_status' => 'inside',
            ])
            ->assertSessionHasErrors('attendance_date');
    }

    public function test_leave_request_can_be_created_with_auto_day_count(): void
    {
        $employee = Employee::firstOrFail();
        $leaveType = LeaveType::where('code', 'ANNUAL')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.hr.leaves.store'), [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-03',
                'reason' => 'Family',
                'status' => 'pending',
            ])
            ->assertRedirect(route('admin.hr.leaves.index'));

        $leave = LeaveRequest::whereDate('start_date', '2026-09-01')->firstOrFail();
        $this->assertSame('3.0', (string) $leave->total_days);
    }

    public function test_leave_request_can_be_approved_and_rejected(): void
    {
        $pending = LeaveRequest::where('status', 'pending')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.hr.leaves.approve', $pending))
            ->assertRedirect();

        $this->assertSame('approved', $pending->refresh()->status);
        $this->assertSame($this->admin()->id, $pending->approved_by);

        $other = LeaveRequest::create([
            'employee_id' => Employee::firstOrFail()->id,
            'leave_type_id' => LeaveType::firstOrFail()->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
            'total_days' => 2,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.hr.leaves.reject', $other), ['rejection_reason' => 'Site coverage'])
            ->assertRedirect();

        $this->assertSame('rejected', $other->refresh()->status);
        $this->assertSame('Site coverage', $other->rejection_reason);
    }

    public function test_overtime_can_be_created_and_amount_is_calculated(): void
    {
        $employee = Employee::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.hr.overtime.store'), [
                'employee_id' => $employee->id,
                'overtime_date' => now()->toDateString(),
                'hours' => 2.5,
                'rate' => 35,
                'reason' => 'Emergency repair',
                'status' => 'pending',
            ])
            ->assertRedirect(route('admin.hr.overtime.index'));

        $record = OvertimeRecord::where('reason', 'Emergency repair')->firstOrFail();
        $this->assertSame('87.50', (string) $record->amount);

        $this->actingAs($this->admin())
            ->post(route('admin.hr.overtime.approve', $record))
            ->assertRedirect();

        $this->assertSame('approved', $record->refresh()->status);
    }

    public function test_salary_structure_can_be_created_with_items(): void
    {
        $employee = Employee::orderByDesc('id')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.hr.salary-structures.store'), [
                'employee_id' => $employee->id,
                'basic_salary' => 8500,
                'housing_allowance' => 1500,
                'transport_allowance' => 600,
                'food_allowance' => 300,
                'other_allowance' => 0,
                'fixed_deduction' => 100,
                'effective_from' => '2026-01-01',
                'status' => 'active',
                'items' => [
                    ['item_type' => 'allowance', 'name' => 'Site Allowance', 'amount' => 400, 'is_taxable' => 0],
                    ['item_type' => 'deduction', 'name' => '', 'amount' => 0, 'is_taxable' => 0],
                ],
            ])
            ->assertRedirect(route('admin.hr.salary-structures.index'));

        $structure = SalaryStructure::where('employee_id', $employee->id)->latest('id')->firstOrFail();

        // Blank rows are dropped, so only the named item is stored.
        $this->assertCount(1, $structure->items);
        $this->assertSame(2800.0, $structure->totalAllowances());
        $this->assertSame(11200.0, $structure->netSalary());
    }

    public function test_payroll_run_can_be_created_processed_and_approved(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.hr.payroll.store'), [
                'payroll_month' => 7,
                'payroll_year' => 2026,
            ])
            ->assertRedirect();

        $run = PayrollRun::where('payroll_month', 7)->where('payroll_year', 2026)->firstOrFail();
        $this->assertSame('draft', $run->status);
        $this->assertSame('2026-07-01', $run->period_start->toDateString());
        $this->assertSame('2026-07-31', $run->period_end->toDateString());

        $this->actingAs($this->admin())
            ->post(route('admin.hr.payroll.process', $run))
            ->assertRedirect(route('admin.hr.payroll.show', $run));

        $run->refresh();
        $this->assertSame('processed', $run->status);
        $this->assertGreaterThan(0, $run->items()->count());
        $this->assertSame($run->items()->count(), $run->total_employees);

        // net = basic + allowances + approved overtime - deductions
        $item = $run->items()->firstOrFail();
        $this->assertEqualsWithDelta(
            (float) $item->basic_salary + (float) $item->total_allowances + (float) $item->overtime_amount - (float) $item->total_deductions,
            (float) $item->net_amount,
            0.01
        );

        $this->actingAs($this->admin())
            ->post(route('admin.hr.payroll.approve', $run))
            ->assertRedirect(route('admin.hr.payroll.show', $run));

        $run->refresh();
        $this->assertSame('approved', $run->status);
        $this->assertSame($this->admin()->id, $run->approved_by);
    }

    public function test_approved_payroll_run_cannot_be_reprocessed(): void
    {
        $run = PayrollRun::firstOrFail();
        $run->update(['status' => 'approved']);

        $this->actingAs($this->admin())
            ->post(route('admin.hr.payroll.process', $run))
            ->assertSessionHasErrors('payroll');
    }

    public function test_eosb_record_can_be_created_and_approved(): void
    {
        $employee = Employee::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.hr.eosb.store'), [
                'employee_id' => $employee->id,
                'termination_date' => '2026-08-31',
                'service_years' => 3.5,
                'last_basic_salary' => 6500,
                'eosb_amount' => 12000,
                'leave_salary' => 2000,
                'other_dues' => 500,
                'deductions' => 500,
                'reason' => 'Contract completed',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.hr.eosb.index'));

        $record = EndOfServiceRecord::where('reason', 'Contract completed')->firstOrFail();
        $this->assertSame('14000.00', (string) $record->final_amount);

        $this->actingAs($this->admin())
            ->post(route('admin.hr.eosb.approve', $record))
            ->assertRedirect();

        $this->assertSame('approved', $record->refresh()->status);
    }

    public function test_hr_sidebar_links_to_live_screens_instead_of_coming_soon(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.hr.employees.index'))
            ->assertOk()
            ->assertSee(route('admin.hr.payroll.index'))
            ->assertSee(route('admin.hr.eosb.index'))
            ->assertDontSee(route('admin.coming-soon', 'employees'));
    }

    public function test_seed_data_covers_every_phase3_table(): void
    {
        $this->assertSame(20, Employee::count());
        $this->assertSame(3, Shift::count());
        $this->assertSame(4, LeaveType::count());
        $this->assertGreaterThan(0, EmployeeDocument::count());
        $this->assertGreaterThan(0, AttendanceRecord::count());
        $this->assertGreaterThan(0, LeaveRequest::count());
        $this->assertGreaterThan(0, OvertimeRecord::count());
        $this->assertSame(20, SalaryStructure::count());
        $this->assertSame(1, PayrollRun::count());
        $this->assertGreaterThan(0, PayrollRun::firstOrFail()->items()->count());
        $this->assertSame(1, EndOfServiceRecord::count());
        $this->assertDatabaseCount('employee_shift_assignments', 20);
    }
}
