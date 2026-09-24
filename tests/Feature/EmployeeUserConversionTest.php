<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeUserConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function employee(array $extra = []): Employee
    {
        return Employee::create($extra + [
            'employee_code' => 'SP-SEARCH-22', 'first_name' => 'Search', 'last_name' => 'Person',
            'email' => 'employee-search@example.test', 'status' => 'active',
            'basic_salary' => 7000, 'bank_name' => 'Private bank', 'iban' => 'PRIVATE-IBAN',
            'employee_classification' => 'Sponsorship', 'joining_date' => '2026-01-01',
        ]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function payload(Employee $employee): array
    {
        return [
            'source_employee_id' => $employee->id, 'name' => $employee->name, 'email' => $employee->email,
            'employee_id' => 'FORGED-CODE', 'role_id' => Role::where('code', '!=', 'SUPER_ADMIN')->firstOrFail()->id,
            'status' => 'active', 'language' => 'English',
        ];
    }

    public function test_search_returns_eligible_scoped_identity_without_payroll_or_security_data(): void
    {
        $employee = $this->employee();
        $this->actingAs($this->admin())->getJson(route('admin.users.employee-search', ['q' => 'Search Person']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $employee->id)
            ->assertJsonPath('data.0.fields.employee_id', $employee->employee_code)
            ->assertJsonMissingPath('data.0.fields.basic_salary')->assertJsonMissingPath('data.0.fields.iban')
            ->assertJsonMissingPath('data.0.fields.role_id')->assertDontSee('Private bank');
        $employee->update(['status' => 'inactive']);
        $this->getJson(route('admin.users.employee-search', ['q' => 'SP-SEARCH']))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('admin.users.employee-search', ['q' => 'S']))->assertUnprocessable();
    }

    public function test_conversion_links_existing_employee_and_does_not_duplicate_or_copy_payroll(): void
    {
        $employee = $this->employee();
        $count = Employee::count();
        $this->actingAs($this->admin())->post(route('admin.users.store'), $this->payload($employee) + ['_save_action' => 'stay'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $user = User::where('email', $employee->email)->firstOrFail();
        $this->assertSame($user->id, $employee->fresh()->user_id);
        $this->assertSame($employee->employee_code, $user->employee_id);
        $this->assertSame($count, Employee::count());
        $this->assertSame('7000.00', $employee->fresh()->basic_salary);
        $this->assertTrue($user->must_change_password);
        $this->assertCount(1, $user->roles);
        $this->getJson(route('admin.users.employee-search', ['q' => 'SP-SEARCH']))->assertJsonCount(0, 'data');
        $this->post(route('admin.users.store'), array_replace($this->payload($employee), ['email' => 'second@example.test']))
            ->assertSessionHasErrors();
        $this->assertDatabaseMissing('users', ['email' => 'second@example.test']);
    }

    public function test_unavailable_matches_explain_why_without_exposing_private_fields(): void
    {
        $employee = $this->employee(['user_id' => $this->admin()->id]);
        $url = route('admin.users.employee-search', ['q' => 'SP-SEARCH']);
        $this->actingAs($this->admin())->getJson($url)->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('unavailable.0.reason', __('ui.employee_already_linked'))
            ->assertJsonMissingPath('unavailable.0.user_id')->assertJsonMissingPath('unavailable.0.fields')
            ->assertDontSee('PRIVATE-IBAN')->assertDontSee('admin@example.com');
        $employee->update(['user_id' => null, 'status' => 'inactive']);
        $this->getJson($url)->assertJsonPath('unavailable.0.reason', __('ui.employee_inactive'));
        $employee->update(['status' => 'active']);
        User::factory()->create(['employee_id' => $employee->employee_code]);
        $this->getJson($url)->assertJsonCount(0, 'data')->assertJsonPath('unavailable.0.reason', __('ui.employee_code_used'));
    }

    public function test_inactive_employee_and_invalid_role_do_not_partially_create_or_link(): void
    {
        $employee = $this->employee(['status' => 'inactive']);
        $this->actingAs($this->admin())->post(route('admin.users.store'), $this->payload($employee))
            ->assertSessionHasErrors('source_employee_id');
        $this->assertDatabaseMissing('users', ['email' => $employee->email]);
        $employee->update(['status' => 'active']);
        $this->post(route('admin.users.store'), array_replace($this->payload($employee), ['role_id' => 999999]))
            ->assertSessionHasErrors('role_id');
        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_users_permission_alone_cannot_search_or_link_hr_records(): void
    {
        $employee = $this->employee();
        $actor = User::factory()->create(['status' => 'active']);
        $role = Role::create(['name' => 'Accounts administrator', 'code' => 'USER_ONLY_22', 'level' => 2, 'access_scope' => 'Company Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::where('module', 'Users')->pluck('id'));
        $actor->roles()->attach($role, ['is_primary' => true]);
        $this->actingAs($actor)->getJson(route('admin.users.employee-search', ['q' => 'Search']))->assertForbidden();
        $this->post(route('admin.users.store'), $this->payload($employee))->assertForbidden();
        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_out_of_scope_employee_is_not_exposed_or_linked(): void
    {
        $employee = $this->employee();
        $actor = User::factory()->create(['status' => 'active']);
        $role = Role::create(['name' => 'Site administrator', 'code' => 'SITE_ADMIN_22', 'level' => 2, 'access_scope' => 'Site Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::whereIn('module', ['Users', 'HR'])->pluck('id'));
        $actor->roles()->attach($role, ['is_primary' => true]);
        $employee->update(['user_id' => $this->admin()->id]);
        $this->actingAs($actor)->getJson(route('admin.users.employee-search', ['q' => 'Search']))->assertOk()->assertJsonCount(0, 'data')->assertJsonCount(0, 'unavailable');
        $employee->update(['user_id' => null]);
        // Existing write-side scope middleware denies the request before the
        // controller lookup; this must remain forbidden, not become a link.
        $this->post(route('admin.users.store'), $this->payload($employee))->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => $employee->email]);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'user_id' => null]);
    }

    public function test_site_administrator_can_link_an_employee_in_their_own_site_using_employee_code(): void
    {
        $site = Site::firstOrFail();
        $employee = $this->employee(['project_id' => $site->project_id, 'site_id' => $site->id]);
        $actor = User::factory()->create(['status' => 'active', 'project_id' => $site->project_id, 'site_id' => $site->id]);
        $role = Role::create(['name' => 'Permitted site administrator', 'code' => 'SITE_ALLOWED_22', 'level' => 2, 'access_scope' => 'Site Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::whereIn('module', ['Users', 'HR'])->pluck('id'));
        $actor->roles()->attach($role, ['is_primary' => true]);
        $this->actingAs($actor)->getJson(route('admin.users.employee-search', ['q' => 'SP-SEARCH']))
            ->assertOk()->assertJsonPath('data.0.id', $employee->id);
        $this->post(route('admin.users.store'), array_replace($this->payload($employee), [
            'employee_id' => $employee->employee_code, 'role_id' => $role->id,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $user = User::where('email', $employee->email)->firstOrFail();
        $this->assertSame($employee->employee_code, $user->employee_id);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'user_id' => $user->id]);
    }
}
