<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LookupValue;
use App\Models\SalaryStructure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The client's 21 September feedback, FR-01 to FR-06 and the OBS-01 label fix.
 * See docs/client-change-register-2026-09-21-status.md.
 */
class ClientFeedbackSeptember21Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** The minimum an employee form needs, so each test states only what it is about. */
    private function employeePayload(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Test', 'last_name' => 'Person',
            'contract_type' => 'Full Time', 'employee_classification' => 'Sponsorship',
            'basic_salary' => 3500, 'payment_method' => 'Bank Transfer', 'status' => 'active',
        ];
    }

    // FR-01 -----------------------------------------------------------------

    public function test_contract_start_cannot_be_in_the_future_but_contract_end_still_can(): void
    {
        $admin = $this->user('admin@example.com');

        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $this->employeePayload([
            'first_name' => 'Future Contract',
            'contract_start_date' => now()->addDay()->toDateString(),
        ]))->assertSessionHasErrors('contract_start_date');

        $this->assertDatabaseMissing('employees', ['first_name' => 'Future Contract']);

        // Today, the past, and a future contract END are all accepted.
        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $this->employeePayload([
            'first_name' => 'Valid Contract',
            'joining_date' => now()->subYear()->toDateString(),
            'contract_start_date' => now()->subYear()->toDateString(),
            'contract_end_date' => now()->addYear()->toDateString(),
        ]))->assertSessionHasNoErrors();

        $employee = Employee::where('first_name', 'Valid Contract')->firstOrFail();
        $this->assertSame(now()->addYear()->toDateString(), $employee->contract_end_date->toDateString());

        // The picker carries the same limit as Joining Date.
        $this->actingAs($admin)->get(route('admin.hr.employees.create'))
            ->assertOk()
            ->assertSee('id="contract_start_date" name="contract_start_date" type="date" class="input" max="'.now()->toDateString().'"', false);

        // Editing an existing employee follows the same rule.
        $this->actingAs($admin)->put(route('admin.hr.employees.update', $employee), $this->employeePayload([
            'employee_code' => $employee->employee_code,
            'first_name' => 'Valid Contract',
            'contract_start_date' => now()->addWeek()->toDateString(),
        ]))->assertSessionHasErrors('contract_start_date');
    }

    // FR-02 -----------------------------------------------------------------

    public function test_a_document_type_can_be_added_and_is_then_reusable(): void
    {
        $admin = $this->user('admin@example.com');

        $this->actingAs($admin)->get(route('admin.hr.employees.create'))
            ->assertOk()
            ->assertSee('data-quick-create="qc-document-type"', false)
            ->assertDontSee('Muqeem Permit');

        $this->actingAs($admin)
            ->postJson(route('admin.master.lookup-values.store'), ['type' => 'document_type', 'value' => 'Muqeem Permit'])
            ->assertCreated()
            ->assertJson(['id' => 'Muqeem Permit', 'label' => 'Muqeem Permit']);

        // Offered on the form, on later employees, and as a filter in the register.
        $this->actingAs($admin)->get(route('admin.hr.employees.create'))->assertOk()->assertSee('Muqeem Permit');
        $this->actingAs($admin)->get(route('admin.hr.documents.index'))->assertOk()->assertSee('Muqeem Permit');

        // The standard names are still there, and still first.
        $types = EmployeeDocument::types();
        $this->assertSame('IQAMA', $types->first());
        $this->assertTrue($types->contains('Muqeem Permit'));
        foreach (EmployeeDocument::TYPES as $standard) {
            $this->assertTrue($types->contains($standard), $standard.' must remain available');
        }

        // A document saves and reads back under the custom type.
        Storage::fake('local');
        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $this->employeePayload([
            'first_name' => 'Muqeem Holder',
            'documents' => [[
                'document_type' => 'Muqeem Permit',
                'document_number' => 'MQ-9001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ]))->assertSessionHasNoErrors();

        $employee = Employee::where('first_name', 'Muqeem Holder')->firstOrFail();
        $this->assertSame('Muqeem Permit', $employee->documents()->value('document_type'));
        $this->actingAs($admin)->get(route('admin.hr.employees.show', $employee))->assertOk()->assertSee('Muqeem Permit');

        // Only HR users may extend the list; duplicates are refused.
        $this->actingAs($this->user('shaban@example.com'))
            ->postJson(route('admin.master.lookup-values.store'), ['type' => 'document_type', 'value' => 'Anything'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->postJson(route('admin.master.lookup-values.store'), ['type' => 'document_type', 'value' => 'Muqeem Permit'])
            ->assertStatus(422);
    }

    public function test_the_standard_document_types_are_seeded_as_catalogue_values(): void
    {
        foreach (EmployeeDocument::TYPES as $type) {
            $this->assertDatabaseHas('lookup_values', ['type' => 'document_type', 'value' => $type, 'status' => 'active']);
        }

        $this->assertSame(EmployeeDocument::TYPES, LookupValue::options('document_type')->all());
    }

    // FR-03 -----------------------------------------------------------------

    public function test_an_attached_document_can_be_viewed_as_well_as_downloaded(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin@example.com');

        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $this->employeePayload([
            'first_name' => 'Document Owner',
            'documents' => [[
                'document_type' => 'IQAMA',
                'document_number' => '2345678901',
                'expiry_date' => now()->addYear()->toDateString(),
                'file' => UploadedFile::fake()->create('iqama.pdf', 40, 'application/pdf'),
            ]],
        ]))->assertSessionHasNoErrors();

        $employee = Employee::where('first_name', 'Document Owner')->firstOrFail();
        $document = $employee->documents()->firstOrFail();
        $this->assertNotNull($document->file_path);

        // View shows the file in the browser; download still sends it as a file.
        $view = $this->actingAs($admin)->get(route('admin.hr.documents.view', $document));
        $view->assertOk();
        $this->assertStringStartsWith('inline', $view->headers->get('content-disposition'));

        $download = $this->actingAs($admin)->get(route('admin.hr.documents.download', $document));
        $download->assertOk();
        $this->assertStringStartsWith('attachment', $download->headers->get('content-disposition'));

        // The link is offered on the employee page, the edit form and the register.
        $this->actingAs($admin)->get(route('admin.hr.employees.show', $employee))
            ->assertOk()->assertSee(route('admin.hr.documents.view', $document), false)->assertDontSee('>Uploaded<', false);
        $this->actingAs($admin)->get(route('admin.hr.employees.edit', $employee))
            ->assertOk()->assertSee(route('admin.hr.documents.view', $document), false)->assertSee('js-file-preview', false);
        $this->actingAs($admin)->get(route('admin.hr.documents.index', ['employee' => $employee->id]))
            ->assertOk()->assertSee(route('admin.hr.documents.view', $document), false);

        // A document without a file cannot be opened, and signing in is required.
        $document->update(['file_path' => null]);
        $this->actingAs($admin)->get(route('admin.hr.documents.view', $document))->assertNotFound();
        $this->post(route('logout'));
        $this->get(route('admin.hr.documents.view', $document))->assertRedirect(route('login'));
    }

    // FR-04 -----------------------------------------------------------------

    public function test_saving_an_employee_creates_their_salary_structure_from_the_pay_entered(): void
    {
        $admin = $this->user('admin@example.com');

        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $this->employeePayload([
            'first_name' => 'Paid', 'last_name' => 'Once',
            'joining_date' => '2026-01-15',
            'contract_start_date' => '2026-02-01',
            'basic_salary' => 3500,
            'housing_allowance' => 500,
            'transport_allowance' => 300,
            'food_allowance' => 300,
            'fuel_allowance' => 100,
            'other_allowance' => 50,
        ]))->assertSessionHasNoErrors();

        $employee = Employee::where('first_name', 'Paid')->firstOrFail();
        $structure = $employee->salaryStructures()->firstOrFail();

        // The client's own figures: basic 3,500 and allowances 1,250, never retyped.
        $this->assertSame(3500.0, (float) $structure->basic_salary);
        $this->assertSame(1250.0, $structure->totalAllowances());
        $this->assertSame(4750.0, $structure->netSalary());
        $this->assertSame('2026-02-01', $structure->effective_from->toDateString(), 'the contract start date is used');
        $this->assertSame('active', $structure->status);

        $this->actingAs($admin)->get(route('admin.hr.employees.show', $employee))
            ->assertOk()->assertSee('4,750.00')->assertDontSee('No salary structure yet');
    }

    public function test_an_existing_structure_is_never_rewritten_and_a_mismatch_is_reported(): void
    {
        $admin = $this->user('admin@example.com');

        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $this->employeePayload([
            'first_name' => 'Raise', 'basic_salary' => 3000,
        ]))->assertSessionHasNoErrors();

        $employee = Employee::where('first_name', 'Raise')->firstOrFail();
        $structure = $employee->salaryStructures()->firstOrFail();

        $this->actingAs($admin)->put(route('admin.hr.employees.update', $employee), $this->employeePayload([
            'employee_code' => $employee->employee_code, 'first_name' => 'Raise', 'basic_salary' => 4000,
        ]))->assertSessionHasNoErrors();

        // The old structure is untouched, so payroll already run does not change.
        $this->assertSame(3000.0, (float) $structure->fresh()->basic_salary);
        $this->assertSame(1, $employee->salaryStructures()->count());
        $this->assertTrue($employee->fresh()->load('activeSalaryStructure')->salaryStructureOutOfDate());

        $this->actingAs($admin)->get(route('admin.hr.employees.show', $employee))
            ->assertOk()->assertSee('Payroll information and salary structure differ');

        // The replacement structure opens prefilled with the new figures.
        $this->actingAs($admin)->get(route('admin.hr.salary-structures.create', ['employee' => $employee->id]))
            ->assertOk()
            ->assertSee('value="4000"', false)
            ->assertSee('filled from the employee');
    }

    public function test_an_employee_saved_before_this_release_gets_a_structure_when_next_edited(): void
    {
        $admin = $this->user('admin@example.com');
        $employee = Employee::whereDoesntHave('salaryStructures')->where('basic_salary', '>', 0)->first()
            ?? tap(Employee::firstOrFail(), fn (Employee $e) => $e->salaryStructures()->delete());

        $this->assertSame(0, $employee->salaryStructures()->count());

        $this->actingAs($admin)->put(route('admin.hr.employees.update', $employee), $this->employeePayload([
            'employee_code' => $employee->employee_code,
            'first_name' => $employee->first_name,
            'employee_classification' => $employee->employee_classification,
            'basic_salary' => $employee->basic_salary,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, $employee->fresh()->salaryStructures()->count());
    }

    public function test_payroll_without_a_structure_uses_the_profile_allowances_not_basic_alone(): void
    {
        $admin = $this->user('admin@example.com');

        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $this->employeePayload([
            'first_name' => 'No', 'last_name' => 'Structure',
            'basic_salary' => 3500, 'housing_allowance' => 500, 'transport_allowance' => 300,
            'food_allowance' => 300, 'fuel_allowance' => 100, 'other_allowance' => 50,
        ]))->assertSessionHasNoErrors();

        $employee = Employee::where('first_name', 'No')->firstOrFail();
        $this->assertSame(1250.0, $employee->defaultAllowanceTotal());

        // Even with the structure removed the allowances still reach the payroll item.
        SalaryStructure::where('employee_id', $employee->id)->delete();

        $this->actingAs($admin)->post(route('admin.hr.payroll.store'), [
            'payroll_month' => now()->month, 'payroll_year' => now()->year, 'notes' => 'Allowance fallback check',
        ])->assertSessionHasNoErrors();

        $run = \App\Models\PayrollRun::latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.hr.payroll.process', $run))->assertSessionHasNoErrors();

        $item = $employee->payrollItems()->latest('id')->firstOrFail();
        $this->assertSame(3500.0, (float) $item->basic_salary);
        $this->assertSame(1250.0, (float) $item->total_allowances, 'allowances must not be dropped when no structure exists');
        $this->assertStringContainsString('allowances were used', $item->remarks);
    }

    // FR-05 -----------------------------------------------------------------

    public function test_leave_total_days_are_calculated_from_the_dates_on_every_save(): void
    {
        $admin = $this->user('admin@example.com');
        $employee = Employee::firstOrFail();
        $type = LeaveType::where('code', 'ANNUAL')->firstOrFail();

        $payload = [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'status' => 'pending',
            'start_date' => '2026-09-02', 'end_date' => '2026-09-15',
        ];

        // The client's own range: 2 to 15 September counts as 14 days.
        $this->actingAs($admin)->post(route('admin.hr.leaves.store'), $payload)->assertSessionHasNoErrors();
        $leave = LeaveRequest::latest('id')->firstOrFail();
        $this->assertSame(14.0, (float) $leave->total_days);

        // A stale total from the previous dates is ignored, not saved.
        $this->actingAs($admin)->put(route('admin.hr.leaves.update', $leave), array_merge($payload, ['total_days' => 14, 'end_date' => '2026-09-03']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2.0, (float) $leave->fresh()->total_days);

        // One day is one day.
        $this->actingAs($admin)->put(route('admin.hr.leaves.update', $leave), array_merge($payload, [
            'start_date' => '2026-09-04', 'end_date' => '2026-09-04',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1.0, (float) $leave->fresh()->total_days);

        // The override allows a half day but never more than the dates cover.
        $this->actingAs($admin)->put(route('admin.hr.leaves.update', $leave), array_merge($payload, [
            'start_date' => '2026-09-04', 'end_date' => '2026-09-04', 'total_days' => 0.5, 'total_days_override' => 1,
        ]))->assertSessionHasNoErrors();
        $this->assertSame(0.5, (float) $leave->fresh()->total_days);

        $this->actingAs($admin)->put(route('admin.hr.leaves.update', $leave), array_merge($payload, [
            'start_date' => '2026-09-04', 'end_date' => '2026-09-04', 'total_days' => 9, 'total_days_override' => 1,
        ]))->assertSessionHasErrors('total_days');

        // The form carries the live calculation and the override box.
        $this->actingAs($admin)->get(route('admin.hr.leaves.create'))
            ->assertOk()
            ->assertSee('id="total_days_override"', false)
            ->assertSee('Total Days fills in as soon as both dates are chosen');
    }

    // FR-06 -----------------------------------------------------------------

    public function test_the_next_employee_code_is_shown_when_the_form_opens(): void
    {
        $admin = $this->user('admin@example.com');

        $sponsorship = \App\Support\CodeGenerator::sequential('employees', 'employee_code', 'SP-');
        $freelancer = \App\Support\CodeGenerator::sequential('employees', 'employee_code', 'FL-');

        $this->actingAs($admin)->get(route('admin.hr.employees.create'))
            ->assertOk()
            ->assertSee('value="'.$sponsorship.'"', false)
            ->assertSee('Assigned when you save')
            ->assertSee('Enter my own code instead')
            ->assertSee($freelancer, false);

        // Saving without typing takes exactly the code that was shown.
        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $this->employeePayload(['first_name' => 'Auto Coded']))
            ->assertSessionHasNoErrors();
        $employee = Employee::where('first_name', 'Auto Coded')->firstOrFail();
        $this->assertSame($sponsorship, $employee->employee_code);

        // The next form offers the following number, and the two sequences stay apart.
        $this->actingAs($admin)->get(route('admin.hr.employees.create'))->assertOk()->assertDontSee('value="'.$sponsorship.'"', false);

        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $this->employeePayload([
            'first_name' => 'Auto Freelancer', 'employee_classification' => 'Freelancer',
        ]))->assertSessionHasNoErrors();
        $this->assertSame($freelancer, Employee::where('first_name', 'Auto Freelancer')->value('employee_code'));

        // A typed code is still honoured, and a duplicate is still refused.
        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $this->employeePayload([
            'first_name' => 'Manual', 'employee_code' => 'OWN-123',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('OWN-123', Employee::where('first_name', 'Manual')->value('employee_code'));

        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $this->employeePayload([
            'first_name' => 'Clash', 'employee_code' => 'OWN-123',
        ]))->assertSessionHasErrors('employee_code');
    }

    // OBS-01 ----------------------------------------------------------------

    public function test_hr_screens_show_a_readable_ampersand(): void
    {
        $admin = $this->user('admin@example.com');
        $employee = Employee::firstOrFail();

        foreach ([
            route('admin.hr.employees.index'),
            route('admin.hr.employees.create'),
            route('admin.hr.employees.show', $employee),
            route('admin.hr.documents.index'),
            route('admin.hr.leaves.index'),
            route('admin.hr.payroll.index'),
        ] as $url) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertOk();
            $this->assertStringNotContainsString('&amp;amp;', $response->getContent(), $url.' still double-escapes an ampersand');
        }

        $this->actingAs($admin)->get(route('admin.hr.employees.create'))->assertOk()->assertSee('Documents &amp; Attachments', false);
    }
}
