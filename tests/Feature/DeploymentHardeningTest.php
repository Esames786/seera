<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeDocument;
use App\Models\EndOfServiceRecord;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DeploymentHardeningTest extends TestCase
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

    public function test_non_active_accounts_cannot_log_in_or_keep_a_session(): void
    {
        $this->post(route('login.attempt'), [
            'email' => 'kamran@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $operator = User::where('email', 'shaban@example.com')->firstOrFail();
        $operator->update(['status' => 'locked']);
        $this->actingAs($operator)->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_operator_is_denied_sensitive_modules(): void
    {
        $operator = User::where('email', 'shaban@example.com')->firstOrFail();

        $this->actingAs($operator)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($operator)->get(route('admin.hr.attendance.index'))->assertOk();
        $this->actingAs($operator)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.roles.permission-matrix'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.accounting.accounts-payable.create'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.hr.payroll.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.inventory.stock-adjustments.index'))->assertForbidden();
    }

    public function test_permission_matrix_preserves_permissions_hidden_by_filter(): void
    {
        $role = Role::where('code', 'OPERATOR')->firstOrFail();
        $hidden = Permission::where('module', 'Users')->where('action', 'view')->firstOrFail();
        $visible = Permission::where('module', 'Dashboard')->where('action', 'view')->firstOrFail();
        $role->permissions()->syncWithoutDetaching([$hidden->id, $visible->id]);

        $this->actingAs($this->admin())->put(route('admin.roles.permission-matrix.update'), [
            'role_id' => $role->id,
            'search' => 'Dashboard',
            'visible_permission_ids' => [$visible->id],
            'permissions' => [$visible->id],
        ])->assertRedirect();

        $this->assertTrue($role->permissions()->whereKey($hidden->id)->exists());
    }

    public function test_project_scope_limits_lists_and_route_model_binding(): void
    {
        $manager = User::where('email', 'nabeel@example.com')->firstOrFail();
        $assigned = Project::withoutGlobalScopes()->findOrFail($manager->project_id);
        $other = Project::withoutGlobalScopes()->whereKeyNot($manager->project_id)->firstOrFail();

        $this->actingAs($manager)
            ->get(route('admin.master.projects.index'))
            ->assertOk()
            ->assertSee($assigned->name)
            ->assertDontSee($other->name);

        $this->actingAs($manager)->get(route('admin.master.projects.show', $other->id))->assertNotFound();

        $outsideSite = Site::withoutGlobalScopes()->where('project_id', $other->id)->firstOrFail();
        $this->actingAs($manager)->post(route('admin.inventory.purchase-requests.store'), [
            'request_date' => now()->toDateString(),
            'project_id' => $manager->project_id,
            'site_id' => $outsideSite->id,
        ])->assertForbidden();
    }

    public function test_employee_relations_are_validated_and_failed_upload_is_atomic(): void
    {
        $department = Department::firstOrFail();
        $otherDepartment = Department::whereKeyNot($department->id)->firstOrFail();
        $designation = Designation::where('department_id', $otherDepartment->id)->firstOrFail();
        $project = Project::firstOrFail();
        $otherProject = Project::whereKeyNot($project->id)->firstOrFail();
        $site = Site::where('project_id', $otherProject->id)->firstOrFail();

        $payload = [
            'employee_code' => 'EMP-HARDENED',
            'first_name' => 'Security',
            'contract_type' => 'Full Time',
            'employee_classification' => 'Sponsorship',
            'basic_salary' => 5000,
            'payment_method' => 'Bank Transfer',
            'status' => 'active',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'project_id' => $project->id,
            'site_id' => $site->id,
        ];

        $this->actingAs($this->admin())
            ->post(route('admin.hr.employees.store'), $payload)
            ->assertSessionHasErrors(['designation_id', 'site_id']);
        $this->assertDatabaseMissing('employees', ['employee_code' => 'EMP-HARDENED']);

        unset($payload['department_id'], $payload['designation_id'], $payload['project_id'], $payload['site_id']);
        $payload['documents'] = [[
            'document_type' => 'Other',
            'file' => UploadedFile::fake()->create('active-content.svg', 10, 'image/svg+xml'),
        ]];

        $this->actingAs($this->admin())
            ->post(route('admin.hr.employees.store'), $payload)
            ->assertSessionHasErrors('documents.0.file');
        $this->assertDatabaseMissing('employees', ['employee_code' => 'EMP-HARDENED']);
    }

    public function test_documents_register_has_no_write_routes_and_eosb_is_immutable_after_approval(): void
    {
        $document = EmployeeDocument::firstOrFail();
        $this->actingAs($this->admin())->get("/admin/hr/documents/{$document->id}/edit")->assertNotFound();
        $this->actingAs($this->admin())->delete("/admin/hr/documents/{$document->id}")->assertNotFound();

        $record = EndOfServiceRecord::where('status', 'approved')->firstOrFail();
        $this->actingAs($this->admin())->get(route('admin.hr.eosb.edit', $record))->assertForbidden();
        $this->actingAs($this->admin())->delete(route('admin.hr.eosb.destroy', $record))->assertSessionHasErrors('eosb');
        $this->assertDatabaseHas('end_of_service_records', ['id' => $record->id, 'status' => 'approved']);
    }

    public function test_opening_balances_appear_in_financial_reports(): void
    {
        ChartOfAccount::create([
            'account_code' => '199901', 'account_name' => 'Opening Test Asset',
            'account_type' => 'asset', 'normal_balance' => 'debit',
            'opening_balance' => 1234.56, 'status' => 'active',
        ]);
        ChartOfAccount::create([
            'account_code' => '399901', 'account_name' => 'Opening Test Equity',
            'account_type' => 'equity', 'normal_balance' => 'credit',
            'opening_balance' => 1234.56, 'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.accounting.reports.trial-balance'))
            ->assertOk();

        $rows = collect($response->viewData('rows'))->keyBy('account_code');
        $this->assertSame(1234.56, $rows['199901']['debit_balance']);
        $this->assertSame(1234.56, $rows['399901']['credit_balance']);
        $this->assertEqualsWithDelta($response->viewData('totalDebit'), $response->viewData('totalCredit'), 0.01);
    }
}
