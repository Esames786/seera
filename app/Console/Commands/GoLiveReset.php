<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Go-live reset: remove every test record while keeping the configuration the client set up.
 *
 * Kept untouched: login accounts, roles and permissions, organisation structure (branches,
 * departments, designations), company profile, chart of accounts, posting rules, VAT periods,
 * ZATCA device units, lookup values, payment terms, units, item and expense categories, leave
 * types, shifts, approval workflows, project classifications and the framework tables.
 *
 * Always removed: every business document and posting (journals, invoices, receipts, bills,
 * payments, purchase requests / orders / receipts, stock movements and balances, site expenses,
 * attendance, leave, overtime, payroll, end of service, budgets, equipment usage, marketing,
 * approvals, notifications, activity logs, ZATCA records, VAT transactions).
 *
 * Removed unless kept with --keep: the entry groups customers, suppliers, employees (with their
 * documents, shifts and salary structures), projects (with sites),
 * inventory (items and warehouses) and equipment (assets). Cost centres are standalone masters and stay.
 *
 * Dry run by default; --execute deletes inside one transaction after the operator types the
 * database name; --force is required in production. Every table in the schema must be classified
 * here, so an unknown table stops the command instead of being silently kept or dropped.
 */
class GoLiveReset extends Command
{
    use ConfirmableTrait;

    protected $signature = 'seera:go-live-reset
        {--execute : Delete the records (without it the command only prints the plan)}
        {--keep=* : Entry groups to retain: customers, suppliers, employees, projects, inventory, equipment (repeat or comma-separate)}
        {--keep-sequences : Leave the document number sequences where they are instead of restarting them}
        {--force : Run in production without the environment confirmation}';

    protected $description = 'Remove test records before go-live while keeping accounts, roles, masters and the chart of accounts';

    public const GROUPS = ['customers', 'suppliers', 'employees', 'projects', 'inventory', 'equipment'];

    /** Tables that are never touched. */
    public const PROTECTED = [
        'users', 'user_roles', 'roles', 'permissions', 'role_permissions', 'role_reporting_parents', 'user_permission_overrides',
        'branches', 'departments', 'designations', 'company_profiles',
        'chart_of_accounts', 'cost_centers', 'automatic_posting_rules', 'vat_periods', 'zatca_egs_units',
        'lookup_values', 'payment_terms', 'units', 'item_categories', 'expense_categories', 'leave_types', 'shifts',
        'approval_workflows', 'approval_workflow_steps', 'project_classifications', 'document_sequences',
        'migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'failed_jobs', 'job_batches', 'password_reset_tokens',
    ];

    /** Business documents and postings: always removed, children before parents. */
    public const TRANSACTIONS = [
        'notifications', 'activity_logs', 'approval_instance_steps', 'approval_instances', 'offline_sync_receipts',
        'payroll_settlements', 'payroll_bank_exports', 'payroll_run_items', 'payroll_runs',
        'end_of_service_records', 'overtime_records', 'leave_requests', 'attendance_records',
        'site_expense_receipts', 'site_expenses',
        'supplier_bill_grn_matches', 'supplier_bill_lines', 'supplier_payments', 'supplier_bills',
        'goods_receipt_lines', 'goods_receipts', 'purchase_order_attachments', 'purchase_order_lines', 'purchase_orders',
        'purchase_request_lines', 'purchase_requests',
        'stock_ledger_entries', 'stock_issue_lines', 'stock_issues', 'stock_transfer_lines', 'stock_transfers', 'stock_adjustments', 'warehouse_stocks',
        'customer_credit_refunds', 'customer_credit_allocations', 'customer_adjustment_note_lines', 'customer_adjustment_notes',
        'customer_receipts', 'customer_invoice_lines', 'customer_invoices', 'customer_notes',
        'zatca_submissions', 'zatca_invoice_records', 'vat_transactions', 'journal_entry_lines', 'journal_entries',
        'project_budget_lines', 'project_budgets',
        'equipment_fuel_logs', 'equipment_maintenance_records', 'equipment_documents', 'equipment_assignments',
        'marketing_visits', 'marketing_leads', 'supplier_projects',
    ];

    /** Entry groups removed unless kept, children before parents. */
    public const ENTRY_GROUPS = [
        'customers' => ['customer_documents', 'customer_contacts', 'customers'],
        'suppliers' => ['supplier_documents', 'suppliers'],
        'employees' => ['employee_documents', 'employee_shift_assignments', 'salary_structure_items', 'salary_structures', 'employees'],
        'projects' => ['sites', 'projects'],
        'inventory' => ['items', 'warehouses'],
        'equipment' => ['equipment_assets'],
    ];

    /** Columns whose files live on disk; their folders are listed for manual cleanup, never deleted here. */
    private const FILE_COLUMNS = [
        'customer_documents' => 'path', 'supplier_documents' => 'path', 'employees' => 'profile_photo_path',
        'employee_documents' => 'file_path', 'site_expense_receipts' => 'path', 'purchase_order_attachments' => 'file_path',
        'equipment_documents' => 'path', 'customer_notes' => 'attachment_path',
    ];

    public function handle(): int
    {
        $keep = $this->keptGroups();
        if ($keep === null) {
            return self::INVALID;
        }
        if (in_array('inventory', $keep, true) && ! in_array('projects', $keep, true)) {
            $this->error('Warehouses belong to projects and sites: --keep=inventory requires --keep=projects.');

            return self::INVALID;
        }

        $unknown = array_diff($this->schemaTables(), self::PROTECTED, self::TRANSACTIONS, array_merge(...array_values(self::ENTRY_GROUPS)));
        if ($unknown !== []) {
            $this->error('Unclassified tables, refusing to run until they are listed as protected or removable: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $plan = $this->plan($keep);
        $this->printPlan($plan, $keep);

        if (! $this->option('execute')) {
            $this->newLine();
            $this->info('Dry run only. Nothing was changed. Add --execute (and --force in production) to delete these rows.');

            return self::SUCCESS;
        }
        if (! $this->confirmToProceed('Application in production! A verified database backup must exist before this reset.')) {
            return self::FAILURE;
        }

        $database = (string) DB::connection()->getDatabaseName();
        $typed = (string) $this->ask('Type the database name ('.$database.') to confirm the permanent deletion');
        if ($typed !== $database) {
            $this->error('Confirmation did not match. Nothing was changed.');

            return self::FAILURE;
        }

        $folders = $this->fileFolders($plan);
        $deleted = [];
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($plan, $keep, &$deleted) {
                $this->detachKeptRows($keep);
                foreach ($plan as $table => $count) {
                    $deleted[$table] = DB::table($table)->delete();
                }
                if (! $this->option('keep-sequences')) {
                    DB::table('document_sequences')->update(['last_value' => 0]);
                }
                $orphans = $this->orphans();
                if ($orphans !== []) {
                    throw new RuntimeException('Dangling references after deletion, rolled back: '.implode('; ', $orphans));
                }
            });
        } catch (RuntimeException $e) {
            Schema::enableForeignKeyConstraints();
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        Schema::enableForeignKeyConstraints();

        $this->newLine();
        $this->table(['Table', 'Rows deleted'], collect($deleted)->map(fn ($n, $t) => [$t, $n])->values()->all());
        $this->info('Reset complete. '.array_sum($deleted).' rows deleted; '.DB::table('users')->count().' login accounts, '
            .DB::table('chart_of_accounts')->count().' accounts and '.DB::table('roles')->count().' roles kept.');
        if (! $this->option('keep-sequences')) {
            $this->line('Document number sequences restarted (next document of each type starts from 1).');
        }
        if ($folders !== []) {
            $this->newLine();
            $this->warn('Uploaded files are not deleted by this command. Review and remove these storage folders manually if they only hold test uploads:');
            foreach ($folders as $folder) {
                $this->line('  - '.$folder);
            }
        }
        if (Schema::hasTable('zatca_egs_units') && DB::table('zatca_egs_units')->where('icv_counter', '>', 0)->exists()) {
            $this->warn('ZATCA device units keep their invoice counter and last hash (required for a chain that was ever reported). Clear them only with the ZATCA onboarding owner.');
        }

        return self::SUCCESS;
    }

    /** @return array<int, string>|null */
    private function keptGroups(): ?array
    {
        $keep = collect((array) $this->option('keep'))->flatMap(fn ($v) => explode(',', (string) $v))->map(fn ($v) => trim($v))->filter()->unique()->values();
        $invalid = $keep->diff(self::GROUPS);
        if ($invalid->isNotEmpty()) {
            $this->error('Unknown --keep group(s): '.$invalid->implode(', ').'. Valid: '.implode(', ', self::GROUPS));

            return null;
        }

        return $keep->all();
    }

    /** @return array<int, string> */
    private function schemaTables(): array
    {
        // MySQL lists every schema the user can see unless the current database is named; SQLite only knows "main".
        $schema = DB::connection()->getDriverName() === 'sqlite' ? null : DB::connection()->getDatabaseName();

        return array_values(array_map(fn ($t) => (string) $t, Schema::getTableListing($schema, schemaQualified: false)));
    }

    /** @return array<string, int> table => rows before deletion, in deletion order */
    private function plan(array $keep): array
    {
        $tables = self::TRANSACTIONS;
        foreach (self::ENTRY_GROUPS as $group => $groupTables) {
            if (! in_array($group, $keep, true)) {
                $tables = array_merge($tables, $groupTables);
            }
        }
        $plan = [];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $plan[$table] = (int) DB::table($table)->count();
            }
        }

        return $plan;
    }

    private function printPlan(array $plan, array $keep): void
    {
        $this->info('SEERA go-live reset plan for database "'.DB::connection()->getDatabaseName().'" ('.app()->environment().')');
        $this->line('Kept entry groups: '.($keep === [] ? 'none' : implode(', ', $keep)).'. Removed entry groups: '.implode(', ', array_diff(self::GROUPS, $keep)).'.');
        $this->newLine();
        $this->table(['Table to empty', 'Rows now'], collect($plan)->map(fn ($n, $t) => [$t, $n])->values()->all());
        $kept = collect(self::PROTECTED)->merge(collect(self::ENTRY_GROUPS)->only($keep)->flatten())
            ->filter(fn ($t) => Schema::hasTable($t))->map(fn ($t) => [$t, DB::table($t)->count()])->values()->all();
        $this->newLine();
        $this->table(['Table kept untouched', 'Rows'], $kept);
        $this->line('Rows to delete: '.array_sum($plan));
    }

    /** Kept rows that point at records about to disappear are detached first (foreign keys are off during the run). */
    private function detachKeptRows(array $keep): void
    {
        $nulls = [];
        if (! in_array('projects', $keep, true)) {
            $nulls['project_id'] = null;
            $nulls['site_id'] = null;
        }
        if (! in_array('inventory', $keep, true)) {
            $nulls['warehouse_id'] = null;
        }
        $nulls = array_filter($nulls, fn ($v, $c) => Schema::hasColumn('users', $c), ARRAY_FILTER_USE_BOTH);
        if ($nulls !== []) {
            DB::table('users')->update($nulls);
        }
        if (in_array('equipment', $keep, true) && ! in_array('employees', $keep, true) && Schema::hasColumn('equipment_assets', 'operator_employee_id')) {
            DB::table('equipment_assets')->update(['operator_employee_id' => null]);
        }
        if (in_array('equipment', $keep, true) && ! in_array('projects', $keep, true)) {
            foreach (['project_id', 'site_id'] as $column) {
                if (Schema::hasColumn('equipment_assets', $column)) {
                    DB::table('equipment_assets')->update([$column => null]);
                }
            }
        }
        if (in_array('inventory', $keep, true) && ! in_array('suppliers', $keep, true) && Schema::hasColumn('items', 'preferred_supplier_id')) {
            DB::table('items')->update(['preferred_supplier_id' => null]);
        }
        if (in_array('employees', $keep, true) && ! in_array('projects', $keep, true)) {
            foreach (['project_id', 'site_id'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    DB::table('employees')->update([$column => null]);
                }
            }
        }
    }

    /** @return array<int, string> distinct storage folders of the files whose rows will be deleted */
    private function fileFolders(array $plan): array
    {
        $folders = [];
        foreach (self::FILE_COLUMNS as $table => $column) {
            if (! isset($plan[$table]) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            foreach (DB::table($table)->whereNotNull($column)->distinct()->pluck($column) as $path) {
                $dir = dirname((string) $path);
                if ($dir !== '' && $dir !== '.') {
                    $folders[$dir] = true;
                }
            }
        }
        ksort($folders);

        return array_keys($folders);
    }

    /** @return array<int, string> human-readable dangling foreign-key references, empty when consistent */
    private function orphans(): array
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            return array_map(fn ($r) => ((array) $r)['table'].' -> '.((array) $r)['parent'].' (rowid '.((array) $r)['rowid'].')', DB::select('PRAGMA foreign_key_check'));
        }
        $problems = [];
        $keys = DB::select(
            'select TABLE_NAME child, COLUMN_NAME col, REFERENCED_TABLE_NAME parent, REFERENCED_COLUMN_NAME pcol
             from information_schema.KEY_COLUMN_USAGE where TABLE_SCHEMA = database() and REFERENCED_TABLE_NAME is not null'
        );
        foreach ($keys as $key) {
            $count = DB::table($key->child.' as c')->leftJoin($key->parent.' as p', 'c.'.$key->col, '=', 'p.'.$key->pcol)
                ->whereNotNull('c.'.$key->col)->whereNull('p.'.$key->pcol)->count();
            if ($count > 0) {
                $problems[] = $key->child.'.'.$key->col.' -> '.$key->parent.' ('.$count.' rows)';
            }
        }

        return $problems;
    }
}
