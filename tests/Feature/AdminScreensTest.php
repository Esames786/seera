<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Designation;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScreensTest extends TestCase
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

    public function test_guest_screens_load(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in to access your admin workspace');
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password')->assertOk();
    }

    public function test_root_redirects_guest_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_admin_routes_require_authentication(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/users')->assertRedirect(route('login'));
    }

    public function test_default_admin_can_login_and_reaches_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_invalid_login_is_rejected(): void
    {
        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_every_phase1_and_phase2_screen_returns_ok(): void
    {
        $user = User::where('email', 'nabeel@example.com')->firstOrFail();
        $role = Role::where('code', 'SITE_SUPERVISOR')->firstOrFail();
        $branch = Branch::firstOrFail();
        $department = Department::firstOrFail();
        $designation = Designation::firstOrFail();
        $project = Project::firstOrFail();
        $site = Site::firstOrFail();
        $warehouse = Warehouse::firstOrFail();
        $expenseCategory = ExpenseCategory::firstOrFail();
        $supplier = Supplier::firstOrFail();
        $customer = Customer::firstOrFail();

        $urls = [
            '/admin/dashboard',
            '/admin/users', '/admin/users/create', "/admin/users/{$user->id}", "/admin/users/{$user->id}/edit",
            '/admin/roles', '/admin/roles/create', "/admin/roles/{$role->id}", "/admin/roles/{$role->id}/edit",
            '/admin/roles/permission-matrix', '/admin/roles/hierarchy',
            '/admin/roles/assign-users', '/admin/roles/approval-workflows',
            '/admin/activity-logs',
            '/admin/master/company-profile',
            '/admin/master/branches', '/admin/master/branches/create', "/admin/master/branches/{$branch->id}", "/admin/master/branches/{$branch->id}/edit",
            '/admin/master/departments', '/admin/master/departments/create', "/admin/master/departments/{$department->id}", "/admin/master/departments/{$department->id}/edit",
            '/admin/master/designations', '/admin/master/designations/create', "/admin/master/designations/{$designation->id}", "/admin/master/designations/{$designation->id}/edit",
            '/admin/master/projects', '/admin/master/projects/create', "/admin/master/projects/{$project->id}", "/admin/master/projects/{$project->id}/edit",
            '/admin/master/sites', '/admin/master/sites/create', "/admin/master/sites/{$site->id}", "/admin/master/sites/{$site->id}/edit",
            '/admin/master/warehouses', '/admin/master/warehouses/create', "/admin/master/warehouses/{$warehouse->id}", "/admin/master/warehouses/{$warehouse->id}/edit",
            '/admin/master/expense-categories', '/admin/master/expense-categories/create', "/admin/master/expense-categories/{$expenseCategory->id}", "/admin/master/expense-categories/{$expenseCategory->id}/edit",
            '/admin/master/suppliers', '/admin/master/suppliers/create', "/admin/master/suppliers/{$supplier->id}", "/admin/master/suppliers/{$supplier->id}/edit",
            '/admin/master/customers', '/admin/master/customers/create', "/admin/master/customers/{$customer->id}", "/admin/master/customers/{$customer->id}/edit",
            '/admin/coming-soon/employees', '/admin/coming-soon/payroll', '/admin/coming-soon/zatca-invoices',
        ];

        $admin = $this->admin();

        foreach ($urls as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk();
        }
    }

    public function test_master_crud_store_works(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/master/branches', [
                'name' => 'Test Branch',
                'code' => 'BR-TEST',
                'city' => 'Makkah',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.branches.index'));

        $this->assertDatabaseHas('branches', ['code' => 'BR-TEST']);
    }

    public function test_user_deactivation_keeps_record(): void
    {
        $user = User::where('email', 'kamran@example.com')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete("/admin/users/{$user->id}")
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
    }
}
