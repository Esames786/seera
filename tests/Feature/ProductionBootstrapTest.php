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

    public function test_the_single_bootstrap_seeder_matches_the_two_step_sequence(): void
    {
        config([
            'seera.admin.name' => 'Admin User',
            'seera.admin.email' => 'admin@seera.com',
            'seera.admin.username' => 'admin',
            'seera.admin.password' => 'Seera2026!Seera2026!',
            'seera.organization.email_domain' => 'seera.com',
        ]);

        $this->seed(\Database\Seeders\ProductionBootstrapSeeder::class);

        $this->assertSame(13, User::count());
        $this->assertSame(0, User::where('email', 'like', '%@example.com')->count(), 'no demo accounts');
        $this->assertTrue(Hash::check('Seera2026!Seera2026!', User::where('email', 'admin@seera.com')->firstOrFail()->password));
        $this->assertTrue(User::where('email', 'omar@seera.com')->firstOrFail()->must_change_password);

        // Re-running is safe: nothing duplicated, no password reset.
        $this->seed(\Database\Seeders\ProductionBootstrapSeeder::class);
        $this->assertSame(13, User::count());
    }

    public function test_the_bootstrap_installs_a_working_chart_of_accounts_without_demo_data(): void
    {
        config([
            'seera.admin.name' => 'Admin User',
            'seera.admin.email' => 'admin@seera.com',
            'seera.admin.username' => 'admin',
            'seera.admin.password' => 'Seera2026!Seera2026!',
            'seera.organization.email_domain' => 'seera.com',
        ]);

        $this->seed(\Database\Seeders\ProductionBootstrapSeeder::class);

        $payable = \App\Models\ChartOfAccount::where('account_code', '2100')->firstOrFail();
        $this->assertSame('Accounts Payable', $payable->account_name);
        $this->assertSame('2000', $payable->parent->account_code);
        $this->assertSame(0.0, (float) \App\Models\ChartOfAccount::sum('opening_balance'), 'no balances are invented');
        $this->assertSame(9, \App\Models\AutomaticPostingRule::count());
        $this->assertSame(1, \App\Models\VatPeriod::count());
        $this->assertSame(['ANNUAL', 'SICK', 'UNPAID', 'URGENT'], \App\Models\LeaveType::orderBy('code')->pluck('code')->all(), 'leave types are ready for the first request (NR-17)');
        $this->assertSame(0, \App\Models\JournalEntry::count());
        $this->assertSame(0, \App\Models\SupplierBill::count());

        // Re-running adds nothing and changes nothing.
        $accounts = \App\Models\ChartOfAccount::count();
        $this->seed(\Database\Seeders\ProductionChartOfAccountsSeeder::class);
        $this->assertSame($accounts, \App\Models\ChartOfAccount::count());
        $this->assertSame(9, \App\Models\AutomaticPostingRule::count());
        $this->assertSame(1, \App\Models\VatPeriod::count());

        // An accountant's own change to an account survives a re-run.
        $payable->update(['account_name' => 'Trade Creditors']);
        $this->seed(\Database\Seeders\ProductionChartOfAccountsSeeder::class);
        $this->assertSame('Trade Creditors', $payable->fresh()->account_name);

        // The very first supplier bill on a fresh production database posts straight to the ledger.
        $supplier = \App\Models\Supplier::create(['name' => 'First Supplier', 'code' => 'SUP-001', 'status' => 'active']);
        $this->assertSame($payable->id, $supplier->linked_account_id, 'new suppliers link to Accounts Payable by default');

        $admin = User::where('email', 'admin@seera.com')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.accounting.accounts-payable.store'), [
                'supplier_id' => $supplier->id,
                'bill_number' => 'BILL-0001',
                'bill_date' => now()->toDateString(),
                'vat_rate' => 15,
                'lines' => [['description' => 'Cement', 'quantity' => 10, 'unit_price' => 100]],
            ])
            ->assertRedirect();

        $bill = \App\Models\SupplierBill::firstOrFail();
        $this->actingAs($admin)->post(route('admin.accounting.accounts-payable.approve', $bill))->assertRedirect();

        $entry = $bill->refresh()->journalEntry;
        $this->assertSame('posted', $entry->status);
        $this->assertSame(1150.0, (float) $entry->lines->firstWhere('chart_of_account_id', $payable->id)->credit);
        $this->assertDatabaseHas('vat_transactions', ['source_reference' => 'BILL-0001', 'vat_period_id' => \App\Models\VatPeriod::first()->id]);
    }

    public function test_the_bootstrap_seeder_refuses_the_placeholder_domain(): void
    {
        config([
            'seera.admin.name' => 'Admin User',
            'seera.admin.email' => 'admin@seera.com',
            'seera.admin.username' => 'admin',
            'seera.admin.password' => 'Seera2026!Seera2026!',
            'seera.organization.email_domain' => 'seera.local',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->seed(\Database\Seeders\ProductionBootstrapSeeder::class);
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
