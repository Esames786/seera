<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EndOfServiceRecord;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Support\EmployeeWorkspacePanels;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeRelatedWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $this->employee = Employee::create([
            'employee_code' => 'SP-WORKSPACE', 'first_name' => 'Workspace', 'last_name' => 'Person',
            'email' => 'workspace-person@example.test', 'status' => 'active', 'basic_salary' => 3500,
            'housing_allowance' => 1000, 'employee_classification' => 'Sponsorship', 'joining_date' => '2025-01-01',
        ]);
    }

    private function url(string $panel, string $method = 'save', array $extra = []): string
    {
        return route('admin.hr.employees.workspace.'.$method, [$this->employee, $panel, ...$extra]);
    }

    private function attendance(array $extra = []): array
    {
        return $extra + ['attendance_date' => today()->toDateString(), 'check_in' => '08:00', 'check_out' => '17:00',
            'late_minutes' => 0, 'overtime_minutes' => 0, 'status' => 'present', 'geofence_status' => 'unknown'];
    }

    public function test_all_panels_render_inside_employee_context_and_sidebar_keeps_registers(): void
    {
        foreach (array_keys(EmployeeWorkspacePanels::PANELS) as $panel) {
            $response = $this->getJson($this->url($panel, 'panel'))->assertOk();
            $this->assertStringContainsString('SP-WORKSPACE', $response->json('html'));
            if ($panel !== 'payroll-history') {
                $this->assertStringContainsString('data-related-save', $response->json('html'));
                $this->assertStringContainsString('name="employee_id" value="'.$this->employee->id.'"', $response->json('html'));
            } else {
                $this->assertStringNotContainsString('<form', $response->json('html'));
            }
        }
        $this->get(route('admin.hr.employees.index'))->assertOk()->assertSee('HR Registers &amp; Approvals', false)->assertSee('>Open</a>', false);
        $this->get(route('admin.hr.employees.edit', $this->employee))->assertOk()->assertSee('data-employee-related="salary"', false);
        $this->getJson($this->url('unknown', 'panel'))->assertNotFound();
    }

    public function test_salary_saves_here_without_rewriting_history(): void
    {
        $original = $this->employee->ensureSalaryStructure();
        $data = $this->employee->payrollDefaults() + ['fixed_deduction' => 0, 'effective_from' => today()->toDateString(), 'status' => 'active'];
        $data['items'] = [['item_type' => 'allowance', 'name' => 'Travel', 'amount' => 250, 'is_taxable' => 0]];
        $this->postJson($this->url('salary'), $data)->assertOk();
        $this->assertSame(2, $this->employee->salaryStructures()->count());
        $this->assertSame('3500.00', $original->fresh()->basic_salary);
        $this->assertDatabaseHas('salary_structure_items', ['name' => 'Travel', 'amount' => 250]);
        $this->postJson($this->url('salary'), ['record_id' => $original->id] + $data)->assertForbidden();
        $this->postJson($this->url('payroll-history'), [])->assertForbidden();
    }

    public function test_attendance_validation_update_and_foreign_record_rejection(): void
    {
        $this->postJson($this->url('attendance'), $this->attendance())->assertOk();
        $record = $this->employee->attendanceRecords()->firstOrFail();
        $this->assertSame('manual', $record->source);
        $this->postJson($this->url('attendance'), $this->attendance())->assertUnprocessable()->assertJsonValidationErrors('attendance_date');
        $this->postJson($this->url('attendance'), $this->attendance(['record_id' => $record->id, 'remarks' => 'Corrected']))->assertOk();
        $this->assertSame('Corrected', $record->fresh()->remarks);
        $this->getJson($this->url('attendance', 'panel').'?record='.$record->id)->assertOk()->assertSee('08:00');
        $foreign = AttendanceRecord::where('employee_id', '!=', $this->employee->id)->firstOrFail();
        $this->postJson($this->url('attendance'), $this->attendance(['record_id' => $foreign->id]))->assertNotFound();
        $this->getJson($this->url('attendance', 'panel').'?record='.$foreign->id)->assertNotFound();
        $this->postJson($this->url('attendance'), $this->attendance(['employee_id' => $foreign->employee_id]))->assertForbidden();
    }

    public function test_leave_attachment_days_and_approval_are_separate(): void
    {
        Storage::fake('local');
        $data = ['leave_type_id' => LeaveType::firstOrFail()->id, 'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'reason' => 'Family'];
        $this->postJson($this->url('leaves'), $data + ['status' => 'approved'])->assertForbidden();
        $this->postJson($this->url('leaves'), $data + ['total_days' => 99, 'attachment' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')])->assertOk();
        $leave = LeaveRequest::where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame('pending', $leave->status);
        $this->assertSame('3.0', $leave->total_days);
        Storage::disk('local')->assertExists($leave->attachment_path);
        $this->postJson($this->url('leaves'), ['record_id' => $leave->id, 'end_date' => '2026-08-01'] + $data)->assertUnprocessable();
        Storage::disk('local')->assertExists($leave->fresh()->attachment_path);
        $this->postJson($this->url('leaves'), ['record_id' => $leave->id, 'total_days_override' => 1, 'total_days' => 0.5] + $data)->assertOk();
        $this->assertSame('0.5', $leave->fresh()->total_days);
        $this->getJson($this->url('leaves', 'panel').'?record='.$leave->id)->assertOk()->assertSee('selected');
        $this->postJson($this->url('leaves', 'action', [$leave->id, 'approve']))->assertOk();
        $this->assertSame('approved', $leave->fresh()->status);
        $this->postJson($this->url('leaves'), ['record_id' => $leave->id] + $data)->assertForbidden();
        $this->postJson($this->url('leaves', 'action', [$leave->id, 'reject']), ['rejection_reason' => 'Late'])->assertForbidden();
    }

    public function test_overtime_only_links_own_attendance_and_calculates_amount(): void
    {
        $foreign = AttendanceRecord::where('employee_id', '!=', $this->employee->id)->firstOrFail();
        $data = ['overtime_date' => today()->toDateString(), 'hours' => 2.5, 'rate' => 30, 'amount' => 9999];
        $this->postJson($this->url('overtime'), $data + ['attendance_record_id' => $foreign->id])->assertForbidden();
        $this->postJson($this->url('overtime'), $data)->assertOk();
        $row = OvertimeRecord::where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame('75.00', $row->amount);
        $this->assertSame('pending', $row->status);
        $this->postJson($this->url('overtime', 'action', [$row->id, 'approve']))->assertOk();
        $this->postJson($this->url('overtime'), ['record_id' => $row->id] + $data)->assertForbidden();
    }

    public function test_shifts_reject_overlaps_and_allow_closing_previous_assignment(): void
    {
        $data = ['shift_id' => Shift::firstOrFail()->id, 'effective_from' => '2026-01-01', 'status' => 'active'];
        $this->postJson($this->url('shifts'), $data)->assertOk();
        $row = $this->employee->shiftAssignments()->firstOrFail();
        $this->postJson($this->url('shifts'), ['effective_from' => '2026-02-01'] + $data)->assertUnprocessable()->assertJsonValidationErrors('effective_from');
        $this->postJson($this->url('shifts'), ['record_id' => $row->id, 'effective_to' => '2026-01-31'] + $data)->assertOk();
        $this->postJson($this->url('shifts'), ['effective_from' => '2026-02-01'] + $data)->assertOk();
        $this->assertSame(2, $this->employee->shiftAssignments()->count());
    }

    public function test_eosb_keeps_existing_calculator_and_approved_record_is_immutable(): void
    {
        $data = ['termination_date' => today()->toDateString(), 'termination_reason' => 'termination', 'service_years' => 2, 'last_basic_salary' => 4500];
        $this->postJson($this->url('eosb'), $data)->assertOk();
        $row = EndOfServiceRecord::where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame('draft', $row->status);
        $this->assertEquals(4500, $row->eosb_amount);
        $this->postJson($this->url('eosb', 'action', [$row->id, 'approve']))->assertOk();
        $this->postJson($this->url('eosb'), ['record_id' => $row->id] + $data)->assertForbidden();
        $this->postJson($this->url('eosb', 'action', [$row->id, 'delete']))->assertForbidden();
    }

    public function test_account_create_links_employee_and_profile_save_preserves_link(): void
    {
        $data = ['name' => $this->employee->name, 'email' => $this->employee->email, 'role_id' => Role::where('code', '!=', 'SUPER_ADMIN')->firstOrFail()->id, 'language' => 'English', 'status' => 'active'];
        $this->postJson($this->url('account'), $data)->assertOk();
        $user = User::where('email', $this->employee->email)->firstOrFail();
        $this->assertSame($user->id, $this->employee->fresh()->user_id);
        $this->assertSame($this->employee->employee_code, $user->employee_id);
        $this->assertTrue($user->must_change_password);
        $this->postJson($this->url('account'), $data)->assertConflict();
        $this->put(route('admin.hr.employees.update', $this->employee), [
            'employee_code' => $this->employee->employee_code, 'first_name' => 'Updated', 'contract_type' => 'Full Time',
            'employee_classification' => 'Sponsorship', 'basic_salary' => 3500, 'payment_method' => 'Bank Transfer', 'status' => 'active', '_save_action' => 'stay',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($user->id, $this->employee->fresh()->user_id);
        $this->postJson($this->url('account'), ['record_id' => $user->id, 'password' => 'tiny'] + $data)->assertUnprocessable();
    }

    public function test_hr_editor_cannot_read_or_mutate_payroll_attendance_or_users(): void
    {
        $role = Role::create(['name' => 'Workspace HR only', 'code' => 'WORKSPACE_HR_ONLY', 'level' => 2, 'access_scope' => 'Company Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::where('module', 'HR')->whereIn('action', ['view', 'edit', 'create'])->pluck('id'));
        $actor = User::factory()->create(['status' => 'active']);
        $actor->roles()->attach($role, ['is_primary' => true]);
        $this->actingAs($actor);
        foreach (['salary', 'attendance', 'overtime', 'eosb', 'payroll-history', 'account'] as $panel) {
            $this->getJson($this->url($panel, 'panel'))->assertForbidden();
            $this->postJson($this->url($panel), [])->assertForbidden();
        }
        $leave = LeaveRequest::create(['employee_id' => $this->employee->id, 'leave_type_id' => LeaveType::firstOrFail()->id, 'start_date' => '2026-09-01', 'end_date' => '2026-09-01', 'total_days' => 1, 'status' => 'pending']);
        $this->postJson($this->url('leaves', 'action', [$leave->id, 'approve']))->assertForbidden();
        $this->postJson($this->url('leaves', 'action', [$leave->id, 'delete']))->assertForbidden();
        $role->update(['access_scope' => 'Site Level']);
        // A new request receives a fresh model; effective roles are cached on
        // the user instance, so do not reuse a company-scope instance here.
        $this->actingAs($actor->fresh());
        $this->getJson($this->url('leaves', 'panel'))->assertNotFound();
    }
}
