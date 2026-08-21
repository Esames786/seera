<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\OrganizationHierarchySeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The exact sequence a production deploy runs against an empty database.
 */
class ProductionBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private function bootstrapProduction(): void
    {
        config([
            'seera.admin.name' => 'Admin User',
            'seera.admin.email' => 'admin@seera.com',
            'seera.admin.username' => 'admin',
            'seera.admin.password' => 'Seera2026!Seera2026!',
            'seera.organization.email_domain' => 'seera.com',
        ]);

        $this->seed(ProductionSeeder::class);
        $this->seed(OrganizationHierarchySeeder::class);
    }

    public function test_production_bootstrap_creates_the_admin_and_the_org_chart(): void
    {
        $this->bootstrapProduction();

        // Bootstrap administrator plus the twelve chart accounts.
        $this->assertSame(13, User::count());

        $admin = User::where('email', 'admin@seera.com')->firstOrFail();
        $this->assertTrue(Hash::check('Seera2026!Seera2026!', $admin->password));
        $this->assertSame('Super Admin', $admin->roles->first()?->name);
        $this->assertFalse($admin->must_change_password);

        foreach (['omar', 'nabeel', 'zubair', 'zulfiqar', 'waleed', 'abdullah', 'zafar', 'shahmeer', 'ayaz', 'kamran', 'shaban', 'rizwan'] as $username) {
            $user = User::where('email', $username.'@seera.com')->first();

            $this->assertNotNull($user, "Missing {$username}@seera.com");
            $this->assertTrue(Hash::check('123456', $user->password));
            $this->assertTrue($user->must_change_password);
        }
    }

    public function test_the_admin_account_is_not_disturbed_by_the_org_seeder(): void
    {
        $this->bootstrapProduction();

        // Running the org seeder again must not touch the bootstrap admin.
        $this->seed(OrganizationHierarchySeeder::class);

        $admin = User::where('email', 'admin@seera.com')->firstOrFail();
        $this->assertSame('admin', $admin->username);
        $this->assertTrue(Hash::check('Seera2026!Seera2026!', $admin->password));
        $this->assertSame(13, User::count());
    }

    public function test_no_demo_records_are_created_by_the_production_path(): void
    {
        $this->bootstrapProduction();

        foreach (['employees', 'items', 'journal_entries', 'customer_invoices', 'supplier_bills', 'projects', 'warehouses'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_both_super_admins_can_open_the_panel(): void
    {
        $this->bootstrapProduction();

        $admin = User::where('email', 'admin@seera.com')->firstOrFail();
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $omar = User::where('email', 'omar@seera.com')->firstOrFail();
        // Forced to the password screen first.
        $this->actingAs($omar)->get('/admin/dashboard')->assertRedirect(route('admin.password.change'));

        $omar->update(['must_change_password' => false]);
        $this->actingAs($omar)->get('/admin/dashboard')->assertOk();

        $this->assertSame(2, Role::where('code', 'SUPER_ADMIN')->firstOrFail()->users()->count());
    }
}
