<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
    }

    private function payload(array $extra = []): array
    {
        return $extra + ['first_name' => 'Workspace', 'last_name' => 'Employee', 'contract_type' => 'Full Time',
            'employee_classification' => 'Sponsorship', 'basic_salary' => 3500, 'housing_allowance' => 1000,
            'payment_method' => 'Bank Transfer', 'status' => 'active'];
    }

    private function employee(): Employee
    {
        $this->post(route('admin.hr.employees.store'), $this->payload())->assertSessionHasNoErrors();

        return Employee::where('first_name', 'Workspace')->firstOrFail();
    }

    public function test_create_can_save_and_move_into_existing_employee_context(): void
    {
        $response = $this->post(route('admin.hr.employees.store'), $this->payload(['_save_action' => 'next']));
        $employee = Employee::where('first_name', 'Workspace')->firstOrFail();
        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.hr.employees.edit', $employee).'#employment');
        $this->assertSame(1, $employee->salaryStructures()->count());
        $this->get(route('admin.hr.employees.edit', $employee))->assertOk()
            ->assertSee('data-employee-workspace="edit"', false)->assertSee('data-employee-section="documents"', false)
            ->assertSee('Save &amp; next', false)->assertSee('data-salary-context', false);
    }

    public function test_save_stay_next_and_close_preserve_existing_salary_history(): void
    {
        $employee = $this->employee();
        $structure = $employee->salaryStructures()->firstOrFail();
        $this->put(route('admin.hr.employees.update', $employee), $this->payload([
            'employee_code' => $employee->employee_code, '_save_action' => 'stay', '_workspace_section' => 'payroll', 'basic_salary' => 4200,
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.hr.employees.edit', $employee).'#payroll');
        $this->assertSame('3500.00', $structure->fresh()->basic_salary);
        $this->assertSame(1, $employee->salaryStructures()->count());
        $this->get(route('admin.hr.employees.edit', $employee))->assertSee('Profile pay and the current salary structure differ.');
        $this->put(route('admin.hr.employees.update', $employee), $this->payload([
            'employee_code' => $employee->employee_code, '_save_action' => 'next', '_workspace_section' => 'payroll',
        ]))->assertRedirect(route('admin.hr.employees.edit', $employee).'#documents');
        $this->put(route('admin.hr.employees.update', $employee), $this->payload(['employee_code' => $employee->employee_code]))
            ->assertRedirect(route('admin.hr.employees.index'));
    }

    public function test_unknown_section_cannot_redirect_outside_the_employee_workspace(): void
    {
        $employee = $this->employee();
        $this->put(route('admin.hr.employees.update', $employee), $this->payload([
            'employee_code' => $employee->employee_code, '_save_action' => 'stay', '_workspace_section' => 'https://outside.example',
        ]))->assertRedirect(route('admin.hr.employees.edit', $employee).'#personal');
    }

    public function test_salary_structure_form_prefills_and_returns_to_the_same_employee(): void
    {
        $employee = $this->employee();
        $this->get(route('admin.hr.salary-structures.create', ['employee' => $employee->id, 'workspace_employee' => $employee->id]))
            ->assertOk()->assertSee('name="_return_employee" value="'.$employee->id.'"', false);
        $this->post(route('admin.hr.salary-structures.store'), $employee->payrollDefaults() + [
            'employee_id' => (string) $employee->id, '_return_employee' => $employee->id,
            'fixed_deduction' => 0, 'effective_from' => today()->toDateString(), 'status' => 'active',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.hr.employees.edit', $employee).'#payroll');
        $this->assertSame(2, $employee->salaryStructures()->count());
    }

    public function test_hr_edit_does_not_grant_salary_structure_visibility_or_creation(): void
    {
        $employee = $this->employee();
        $actor = User::factory()->create(['status' => 'active']);
        $role = Role::create(['name' => 'HR editor only', 'code' => 'HR_WORKSPACE_ONLY', 'level' => 2, 'access_scope' => 'Company Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::where('module', 'HR')->pluck('id'));
        $actor->roles()->attach($role, ['is_primary' => true]);
        $this->actingAs($actor)->get(route('admin.hr.employees.edit', $employee))->assertOk()->assertDontSee('data-salary-context', false);
        $this->get(route('admin.hr.salary-structures.create', ['employee' => $employee->id]))->assertForbidden();
    }
}
