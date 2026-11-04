<?php

namespace Tests\Feature;

use App\Console\Commands\GoLiveReset;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoLiveResetCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PROTECTED_SAMPLE = ['users', 'user_roles', 'roles', 'permissions', 'role_permissions', 'departments', 'designations', 'branches',
        'company_profiles', 'chart_of_accounts', 'cost_centers', 'automatic_posting_rules', 'vat_periods', 'lookup_values', 'payment_terms', 'units',
        'item_categories', 'expense_categories', 'leave_types', 'shifts', 'approval_workflows', 'approval_workflow_steps', 'document_sequences'];

    private const BUSINESS_SAMPLE = ['journal_entries', 'journal_entry_lines', 'customer_invoices', 'customer_receipts', 'supplier_bills',
        'purchase_orders', 'goods_receipts', 'stock_ledger_entries', 'warehouse_stocks', 'attendance_records', 'leave_requests',
        'payroll_runs', 'payroll_run_items', 'activity_logs', 'vat_transactions', 'zatca_invoice_records'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @return array<string, int> */
    private function counts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : -1;
        }

        return $counts;
    }

    public function test_every_table_in_the_schema_is_classified(): void
    {
        $classified = array_merge(GoLiveReset::PROTECTED, GoLiveReset::TRANSACTIONS, ...array_values(GoLiveReset::ENTRY_GROUPS));
        $unclassified = array_diff(Schema::getTableListing(schemaQualified: false), $classified);

        $this->assertSame([], array_values($unclassified), 'Classify new tables in GoLiveReset before the next go-live reset.');
    }

    public function test_dry_run_changes_nothing(): void
    {
        $before = $this->counts(array_merge(self::PROTECTED_SAMPLE, self::BUSINESS_SAMPLE, ['employees', 'customers', 'projects']));
        $this->assertGreaterThan(0, $before['journal_entries']);

        $this->artisan('seera:go-live-reset')->expectsOutputToContain('Dry run only')->assertExitCode(0);

        $this->assertSame($before, $this->counts(array_keys($before)));
    }

    public function test_wrong_confirmation_changes_nothing(): void
    {
        $before = $this->counts(array_merge(self::BUSINESS_SAMPLE, ['employees']));

        $this->artisan('seera:go-live-reset --execute')
            ->expectsQuestion('Type the database name (:memory:) to confirm the permanent deletion', 'something-else')
            ->assertExitCode(1);

        $this->assertSame($before, $this->counts(array_keys($before)));
    }

    public function test_execute_removes_business_records_and_keeps_accounts_masters_and_the_chart_of_accounts(): void
    {
        $protectedBefore = $this->counts(self::PROTECTED_SAMPLE);
        $this->assertGreaterThan(0, DB::table('journal_entries')->count());
        $this->assertGreaterThan(0, DB::table('employees')->count());
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->artisan('seera:go-live-reset --execute')
            ->expectsQuestion('Type the database name (:memory:) to confirm the permanent deletion', ':memory:')
            ->expectsOutputToContain('Reset complete.')
            ->assertExitCode(0);

        foreach (array_merge(self::BUSINESS_SAMPLE, ['employees', 'customers', 'suppliers', 'projects', 'sites', 'items', 'warehouses', 'salary_structures', 'employee_documents']) as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table.' should be empty');
        }
        $this->assertSame($protectedBefore, $this->counts(self::PROTECTED_SAMPLE), 'protected tables must be untouched');
        $this->assertSame(0, DB::table('users')->whereNotNull('project_id')->orWhereNotNull('site_id')->orWhereNotNull('warehouse_id')->count());
        $this->assertSame(0, (int) DB::table('document_sequences')->max('last_value'));
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));

        $this->actingAs($admin->fresh())->get(route('admin.dashboard'))->assertOk();
    }

    public function test_kept_groups_survive_with_their_master_details_while_their_documents_go(): void
    {
        $employees = DB::table('employees')->count();
        $projects = DB::table('projects')->count();
        $sites = DB::table('sites')->count();
        $structures = DB::table('salary_structures')->count();
        $this->assertGreaterThan(0, $employees * $projects * $structures);

        $this->artisan('seera:go-live-reset --execute --keep=employees,projects --keep-sequences')
            ->expectsQuestion('Type the database name (:memory:) to confirm the permanent deletion', ':memory:')
            ->assertExitCode(0);

        $this->assertSame($employees, DB::table('employees')->count());
        $this->assertSame($projects, DB::table('projects')->count());
        $this->assertSame($sites, DB::table('sites')->count());
        $this->assertSame($structures, DB::table('salary_structures')->count());
        $this->assertSame(0, DB::table('payroll_runs')->count());
        $this->assertSame(0, DB::table('attendance_records')->count());
        $this->assertSame(0, DB::table('customers')->count());
        $this->assertSame(0, DB::table('journal_entries')->count());
        $this->assertGreaterThan(0, (int) DB::table('document_sequences')->max('last_value'), '--keep-sequences leaves the counters alone');
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_inventory_cannot_be_kept_without_projects(): void
    {
        $this->artisan('seera:go-live-reset --execute --keep=inventory')->assertExitCode(2);
        $this->assertGreaterThan(0, DB::table('journal_entries')->count());
    }
}
