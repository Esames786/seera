<?php

namespace Database\Seeders;

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\VatPeriod;
use Illuminate\Database\Seeder;

/**
 * The accounting configuration a production company needs before its first
 * bill: the standard chart of accounts with zero balances, the automatic
 * posting rules that decide which journals post straight to the ledger, and
 * an open VAT period for the current quarter.
 *
 * No transactions, balances or demo records are created. Every step is
 * idempotent and never overwrites an account the accountant has already
 * created or changed, so it is safe to re-run after a partial setup.
 *
 *   php artisan db:seed --class=ProductionChartOfAccountsSeeder --force
 */
class ProductionChartOfAccountsSeeder extends Seeder
{
    /** @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: array}> */
    public const ACCOUNT_TREE = [
        ['1000', 'Assets', 'asset', 'debit', [
            ['1100', 'Cash & Bank', 'asset', 'debit', [
                ['1110', 'Cash in Hand', 'asset', 'debit', []],
                ['1120', 'Bank Account', 'asset', 'debit', []],
            ]],
            ['1200', 'Accounts Receivable', 'asset', 'debit', []],
            ['1300', 'Input VAT Receivable', 'asset', 'debit', []],
            ['1400', 'Inventory Asset', 'asset', 'debit', []],
        ]],
        ['2000', 'Liabilities', 'liability', 'credit', [
            ['2100', 'Accounts Payable', 'liability', 'credit', []],
            ['2150', 'Goods Received Not Invoiced', 'liability', 'credit', []],
            ['2200', 'VAT Payable', 'liability', 'credit', [
                ['2210', 'Output VAT', 'liability', 'credit', []],
            ]],
            ['2300', 'Salary Payable', 'liability', 'credit', []],
        ]],
        ['3000', 'Equity', 'equity', 'credit', [
            ['3100', 'Owner Equity', 'equity', 'credit', []],
        ]],
        ['4000', 'Revenue', 'revenue', 'credit', [
            ['4100', 'Project Revenue', 'revenue', 'credit', []],
            ['4200', 'Service Revenue', 'revenue', 'credit', []],
        ]],
        ['5000', 'Expenses', 'expense', 'debit', [
            ['5100', 'Salary Expense', 'expense', 'debit', []],
            ['5200', 'Material Expense', 'expense', 'debit', []],
            ['5300', 'Fuel Expense', 'expense', 'debit', []],
            ['5400', 'Maintenance Expense', 'expense', 'debit', []],
            ['5500', 'Equipment Expense', 'expense', 'debit', []],
            ['5600', 'Inventory Adjustment Expense', 'expense', 'debit', []],
        ]],
    ];

    public const VAT_ACCOUNTS = ['1300', '2200', '2210'];

    public const COST_CENTER_ACCOUNTS = ['4100', '4200', '5100', '5200', '5300', '5400', '5500', '5600'];

    /** [module, event, debit code, credit code, cost centre rule, auto post, approval required, note] */
    public const POSTING_RULES = [
        ['Payroll', 'Payroll Approved', '5100', '2300', 'Employee Project / Department', true, true, 'Salary expense against salary payable when a payroll run is approved.'],
        ['Site Expense', 'Site Expense Approved', '5200', '1110', 'Selected Project / Site', false, true, 'Expense category linked account against cash when a site expense is approved.'],
        ['Inventory', 'Inventory Purchase', '1400', '2150', 'Warehouse / Project', true, false, 'Inventory asset against Goods Received Not Invoiced when a goods receipt is posted; the matched supplier bill clears GRNI and records input VAT against accounts payable.'],
        ['Inventory', 'Stock Issued', '5200', '1400', 'Selected Project / Site', true, false, 'Project material expense against inventory asset when stock is issued.'],
        ['Inventory', 'Stock Adjusted', '5600', '1400', 'Warehouse / Project', true, false, 'Inventory adjustment expense against inventory asset on a stock loss. A gain reverses the sides.'],
        ['Customer Invoice', 'Invoice Approved', '1200', '4100', 'Invoice Project', true, false, 'Accounts receivable against revenue and output VAT when an invoice is approved.'],
        ['Supplier Bill', 'Bill Approved', '5200', '2100', 'Selected Project / Site', true, false, 'Expense and input VAT against accounts payable when a supplier bill is approved.'],
        ['Supplier Payment', 'Payment Recorded', '2100', '1120', 'None', true, false, 'Accounts payable against bank when a supplier payment is recorded.'],
        ['Customer Receipt', 'Receipt Recorded', '1120', '1200', 'None', true, false, 'Bank against accounts receivable when a customer receipt is recorded.'],
    ];

    public function run(): void
    {
        $created = $this->seedAccounts(self::ACCOUNT_TREE, null);
        $rules = $this->seedPostingRules();
        $period = $this->seedCurrentVatPeriod();

        $this->command?->info(sprintf(
            'Chart of accounts ready: %d accounts (%d added), %d posting rules (%d added), VAT period "%s".',
            ChartOfAccount::count(),
            $created,
            AutomaticPostingRule::count(),
            $rules,
            $period->period_name
        ));
    }

    /**
     * @param  array<int, array>  $nodes
     * @return int Number of accounts created (existing codes are left untouched).
     */
    private function seedAccounts(array $nodes, ?int $parentId): int
    {
        $created = 0;

        foreach ($nodes as [$code, $name, $type, $normal, $children]) {
            $account = ChartOfAccount::firstOrNew(['account_code' => $code]);

            if (! $account->exists) {
                $account->fill([
                    'account_name' => $name,
                    'account_type' => $type,
                    'parent_id' => $parentId,
                    'opening_balance' => 0,
                    'normal_balance' => $normal,
                    'vat_applicable' => in_array($code, self::VAT_ACCOUNTS, true),
                    'cost_center_required' => in_array($code, self::COST_CENTER_ACCOUNTS, true),
                    'status' => 'active',
                ])->save();
                $created++;
            }

            $created += $this->seedAccounts($children, $account->id);
        }

        return $created;
    }

    private function seedPostingRules(): int
    {
        $accounts = ChartOfAccount::pluck('id', 'account_code');
        $created = 0;

        foreach (self::POSTING_RULES as [$module, $event, $debit, $credit, $costRule, $autoPost, $approval, $notes]) {
            $rule = AutomaticPostingRule::firstOrNew(['source_module' => $module, 'trigger_event' => $event]);

            if (! $rule->exists) {
                $rule->fill([
                    'debit_account_id' => $accounts[$debit] ?? null,
                    'credit_account_id' => $accounts[$credit] ?? null,
                    'cost_center_rule' => $costRule,
                    'auto_post' => $autoPost,
                    'approval_required' => $approval,
                    'status' => 'active',
                    'notes' => $notes,
                ])->save();
                $created++;
            }
        }

        return $created;
    }

    /** VAT transactions are filed against the quarter they fall in; make sure the current one exists. */
    private function seedCurrentVatPeriod(): VatPeriod
    {
        $start = now()->startOfQuarter();

        return VatPeriod::firstOrCreate(
            ['period_name' => 'Q'.$start->quarter.' '.$start->year],
            [
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->endOfQuarter()->toDateString(),
                'status' => 'draft',
                'notes' => 'Open quarter created by the production bootstrap.',
            ]
        );
    }
}
