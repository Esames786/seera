<?php

namespace Database\Seeders;

use App\Models\AutomaticPostingRule;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerReceipt;
use App\Models\Department;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\VatPeriod;
use App\Models\Warehouse;
use App\Services\Accounting\PostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class Phase4AccountingSeeder extends Seeder
{
    private PostingService $posting;

    private ?int $financeUserId = null;

    public function run(): void
    {
        $this->posting = app(PostingService::class);
        $this->financeUserId = User::where('email', 'zubair@example.com')->value('id');

        $this->seedChartOfAccounts();
        $this->seedCostCenters();
        $this->seedVatPeriods();
        $this->seedPostingRules();
        $this->seedSupplierBills();
        $this->seedCustomerInvoices();
        $this->seedPayrollJournal();
        $this->seedDraftAccrual();
        $this->recalculateVatPeriods();
    }

    private function seedChartOfAccounts(): void
    {
        $tree = [
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

        // VAT-bearing and cost-center-bearing accounts, by code.
        $vatAccounts = ['1300', '2200', '2210'];
        $costCenterAccounts = ['4100', '4200', '5100', '5200', '5300', '5400', '5500', '5600'];

        $create = function (array $nodes, ?int $parentId) use (&$create, $vatAccounts, $costCenterAccounts) {
            foreach ($nodes as [$code, $name, $type, $normal, $children]) {
                $account = ChartOfAccount::create([
                    'account_code' => $code,
                    'account_name' => $name,
                    'account_type' => $type,
                    'parent_id' => $parentId,
                    'opening_balance' => $code === '1120' ? 850000 : ($code === '3100' ? 850000 : 0),
                    'normal_balance' => $normal,
                    'vat_applicable' => in_array($code, $vatAccounts, true),
                    'cost_center_required' => in_array($code, $costCenterAccounts, true),
                    'status' => 'active',
                ]);

                $create($children, $account->id);
            }
        };

        $create($tree, null);
    }

    private function seedCostCenters(): void
    {
        $sources = [
            ['branch', Branch::all(), 'CC-BR-'],
            ['department', Department::all(), 'CC-DEP-'],
            ['project', Project::all(), 'CC-PRJ-'],
            ['site', Site::all(), 'CC-SITE-'],
            ['warehouse', Warehouse::all(), 'CC-WH-'],
        ];

        foreach ($sources as [$type, $records, $prefix]) {
            foreach ($records as $index => $record) {
                CostCenter::create([
                    'code' => $prefix.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'name' => $record->name,
                    'type' => $type,
                    'linked_id' => $record->id,
                    'manager_id' => $record->manager_id ?? $record->head_user_id ?? $record->supervisor_id ?? $record->incharge_id ?? null,
                    'status' => 'active',
                ]);
            }
        }
    }

    private function seedVatPeriods(): void
    {
        $quarterStart = now()->startOfQuarter();

        foreach ([-1, 0] as $offset) {
            $start = $quarterStart->copy()->addQuarters($offset);
            $end = $start->copy()->endOfQuarter();

            VatPeriod::create([
                'period_name' => 'Q'.$start->quarter.' '.$start->year,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => $offset < 0 ? 'finalized' : 'draft',
                'submitted_at' => $offset < 0 ? $end->copy()->addDays(15) : null,
                'notes' => $offset < 0 ? 'Filed with ZATCA.' : 'Open quarter, still collecting transactions.',
            ]);
        }
    }

    private function seedPostingRules(): void
    {
        $accounts = ChartOfAccount::pluck('id', 'account_code');

        $rules = [
            ['Payroll', 'Payroll Approved', '5100', '2300', 'Employee Project / Department', true, true, 'Salary expense against salary payable when a payroll run is approved.'],
            ['Site Expense', 'Site Expense Approved', '5200', '1110', 'Selected Project / Site', false, true, 'Expense category linked account against cash when a site expense is approved.'],
            ['Inventory', 'Inventory Purchase', '1400', '2100', 'Warehouse / Project', true, false, 'Inventory asset and input VAT against accounts payable when a goods receipt is posted.'],
            ['Inventory', 'Stock Issued', '5200', '1400', 'Selected Project / Site', true, false, 'Project material expense against inventory asset when stock is issued.'],
            ['Inventory', 'Stock Adjusted', '5600', '1400', 'Warehouse / Project', true, false, 'Inventory adjustment expense against inventory asset on a stock loss. A gain reverses the sides.'],
            ['Customer Invoice', 'Invoice Approved', '1200', '4100', 'Invoice Project', true, false, 'Accounts receivable against revenue and output VAT when an invoice is approved.'],
            ['Supplier Bill', 'Bill Approved', '5200', '2100', 'Selected Project / Site', true, false, 'Expense and input VAT against accounts payable when a supplier bill is approved.'],
            ['Supplier Payment', 'Payment Recorded', '2100', '1120', 'None', true, false, 'Accounts payable against bank when a supplier payment is recorded.'],
            ['Customer Receipt', 'Receipt Recorded', '1120', '1200', 'None', true, false, 'Bank against accounts receivable when a customer receipt is recorded.'],
        ];

        foreach ($rules as [$module, $event, $debit, $credit, $costRule, $autoPost, $approval, $notes]) {
            AutomaticPostingRule::create([
                'source_module' => $module,
                'trigger_event' => $event,
                'debit_account_id' => $accounts[$debit] ?? null,
                'credit_account_id' => $accounts[$credit] ?? null,
                'cost_center_rule' => $costRule,
                'auto_post' => $autoPost,
                'approval_required' => $approval,
                'status' => 'active',
                'notes' => $notes,
            ]);
        }
    }

    private function seedSupplierBills(): void
    {
        $suppliers = Supplier::orderBy('id')->get();
        $projects = Project::orderBy('id')->get();
        $sites = Site::orderBy('id')->get();
        $accounts = ChartOfAccount::pluck('id', 'account_code');
        $bank = $accounts['1120'] ?? null;

        $rows = [
            [0, 'BILL-2026-001', 'Cement and steel for Riyadh Tower slab', '5200', 120000, 40],
            [1, 'BILL-2026-002', 'Diesel supply for site generators', '5300', 18000, 25],
            [2, 'BILL-2026-003', 'Structural steel delivery', '5200', 96000, 12],
            [0, 'BILL-2026-004', 'Block work material', '5200', 42000, 6],
        ];

        foreach ($rows as $index => [$supplierIndex, $number, $description, $accountCode, $amount, $daysAgo]) {
            $supplier = $suppliers[$supplierIndex] ?? $suppliers->first();
            $project = $projects[$index % max($projects->count(), 1)] ?? null;
            $site = $sites[$index % max($sites->count(), 1)] ?? null;
            $billDate = now()->subDays($daysAgo);

            $costCenter = $project ? CostCenter::where('type', 'project')->where('linked_id', $project->id)->first() : null;

            $bill = SupplierBill::create([
                'supplier_id' => $supplier->id,
                'bill_number' => $number,
                'bill_date' => $billDate->toDateString(),
                'due_date' => $billDate->copy()->addDays(30)->toDateString(),
                'reference_number' => 'PO-'.str_pad((string) (100 + $index), 4, '0', STR_PAD_LEFT),
                'project_id' => $project?->id,
                'site_id' => $site?->id,
                'cost_center_id' => $costCenter?->id,
                'vat_rate' => 15,
                'status' => 'draft',
                'notes' => 'Seeded demo bill.',
            ]);

            $vat = round($amount * 0.15, 2);

            $bill->lines()->create([
                'description' => $description,
                'chart_of_account_id' => $accounts[$accountCode] ?? null,
                'quantity' => 1,
                'unit_price' => $amount,
                'taxable_amount' => $amount,
                'vat_rate' => 15,
                'vat_amount' => $vat,
                'total_amount' => round($amount + $vat, 2),
                'cost_center_id' => $costCenter?->id,
            ]);

            $bill->update([
                'taxable_amount' => $amount,
                'vat_amount' => $vat,
                'total_amount' => round($amount + $vat, 2),
                'balance_amount' => round($amount + $vat, 2),
            ]);

            // Leave the last bill in draft so the approval flow is visible.
            if ($index === count($rows) - 1) {
                continue;
            }

            $entry = $this->posting->postSupplierBill($bill->fresh('lines'), $this->financeUserId);
            $bill->update(['status' => 'unpaid', 'journal_entry_id' => $entry?->id]);

            // Pay the first bill in full and the second one partially.
            $payAmount = match ($index) {
                0 => (float) $bill->total_amount,
                1 => round((float) $bill->total_amount / 2, 2),
                default => null,
            };

            if ($payAmount) {
                $payment = SupplierPayment::create([
                    'supplier_id' => $supplier->id,
                    'supplier_bill_id' => $bill->id,
                    'payment_date' => $billDate->copy()->addDays(10)->toDateString(),
                    'payment_account_id' => $bank,
                    'amount' => $payAmount,
                    'reference_number' => 'PAY-'.str_pad((string) (500 + $index), 4, '0', STR_PAD_LEFT),
                ]);

                $paymentEntry = $this->posting->postSupplierPayment($payment, $this->financeUserId);
                $payment->update(['journal_entry_id' => $paymentEntry?->id]);

                $bill->refresh()->refreshPaymentStatus();
            }
        }
    }

    private function seedCustomerInvoices(): void
    {
        $customers = Customer::orderBy('id')->get();
        $projects = Project::orderBy('id')->get();
        $accounts = ChartOfAccount::pluck('id', 'account_code');
        $bank = $accounts['1120'] ?? null;

        $rows = [
            [0, 'Riyadh Tower - progress claim 3', 320000, 45, 'cleared'],
            [1, 'Jeddah Warehouse - mobilization', 180000, 30, 'failed'],
            [2, 'Dammam Road - milestone 1', 240000, 12, 'pending'],
            [0, 'Riyadh Tower - variation order 2', 65000, 3, 'draft'],
        ];

        foreach ($rows as $index => [$customerIndex, $description, $amount, $daysAgo, $zatcaOutcome]) {
            $customer = $customers[$customerIndex] ?? $customers->first();
            $project = $projects[$index % max($projects->count(), 1)] ?? null;
            $invoiceDate = now()->subDays($daysAgo);
            $costCenter = $project ? CostCenter::where('type', 'project')->where('linked_id', $project->id)->first() : null;
            $vat = round($amount * 0.15, 2);

            $invoice = CustomerInvoice::create([
                'customer_id' => $customer->id,
                'invoice_number' => CustomerInvoice::nextNumber($invoiceDate->year),
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $invoiceDate->copy()->addDays(30)->toDateString(),
                'project_id' => $project?->id,
                'cost_center_id' => $costCenter?->id,
                'taxable_amount' => $amount,
                'vat_rate' => 15,
                'vat_amount' => $vat,
                'total_amount' => round($amount + $vat, 2),
                'balance_amount' => round($amount + $vat, 2),
                'payment_status' => 'draft',
                'zatca_status' => 'draft',
                'notes' => 'Seeded demo invoice.',
            ]);

            $invoice->lines()->create([
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $amount,
                'taxable_amount' => $amount,
                'vat_rate' => 15,
                'vat_amount' => $vat,
                'total_amount' => round($amount + $vat, 2),
                'revenue_account_id' => $accounts['4100'] ?? null,
                'cost_center_id' => $costCenter?->id,
            ]);

            // The last invoice stays a draft so the approval flow is visible.
            if ($zatcaOutcome === 'draft') {
                continue;
            }

            $entry = $this->posting->postCustomerInvoice($invoice->fresh('lines'), $this->financeUserId);
            $invoice->update(['payment_status' => 'unpaid', 'journal_entry_id' => $entry?->id]);

            $record = $this->posting->createZatcaRecord($invoice->fresh());
            $this->applyZatcaOutcome($record, $invoice, $zatcaOutcome, $invoiceDate);

            // Settle the first invoice fully and the second one partially.
            $receiveAmount = match ($index) {
                0 => (float) $invoice->total_amount,
                1 => round((float) $invoice->total_amount * 0.4, 2),
                default => null,
            };

            if ($receiveAmount) {
                $receipt = CustomerReceipt::create([
                    'customer_id' => $customer->id,
                    'customer_invoice_id' => $invoice->id,
                    'receipt_date' => $invoiceDate->copy()->addDays(12)->toDateString(),
                    'receipt_account_id' => $bank,
                    'amount' => $receiveAmount,
                    'reference_number' => 'RCPT-'.str_pad((string) (700 + $index), 4, '0', STR_PAD_LEFT),
                ]);

                $receiptEntry = $this->posting->postCustomerReceipt($receipt, $this->financeUserId);
                $receipt->update(['journal_entry_id' => $receiptEntry?->id]);

                $invoice->refresh()->refreshPaymentStatus();
            }
        }
    }

    private function applyZatcaOutcome($record, CustomerInvoice $invoice, string $outcome, Carbon $invoiceDate): void
    {
        match ($outcome) {
            'cleared' => tap($record)->update([
                'clearance_status' => 'cleared',
                'zatca_response_code' => '200',
                'zatca_response_message' => 'Invoice cleared successfully.',
                'cleared_at' => $invoiceDate->copy()->addHours(2),
            ]),
            'failed' => tap($record)->update([
                'clearance_status' => 'failed',
                'zatca_response_code' => '400',
                'zatca_response_message' => 'Invalid VAT registration number on the buyer record.',
                'failed_reason' => 'Buyer VAT number failed schema validation.',
                'retry_count' => 1,
            ]),
            default => tap($record)->update([
                'clearance_status' => 'pending',
                'zatca_response_message' => 'Awaiting clearance response.',
            ]),
        };

        $invoice->update([
            'zatca_status' => match ($outcome) {
                'cleared' => 'cleared',
                'failed' => 'failed',
                default => 'pending_clearance',
            },
        ]);
    }

    /**
     * Mirror the Phase 3 payroll run into accounting so the ledger has a
     * salary expense entry, exactly as the payroll posting rule describes.
     */
    private function seedPayrollJournal(): void
    {
        $run = PayrollRun::latest('id')->first();

        if (! $run || (float) $run->net_amount <= 0) {
            return;
        }

        $salaryExpense = $this->posting->account(PostingService::SALARY_EXPENSE);
        $salaryPayable = $this->posting->account(PostingService::SALARY_PAYABLE);

        if (! $salaryExpense || ! $salaryPayable) {
            return;
        }

        $entry = JournalEntry::create([
            'journal_number' => JournalEntry::nextNumber((int) now()->year),
            'journal_date' => $run->period_end,
            'reference_number' => $run->code,
            'source_module' => 'Payroll',
            'source_id' => $run->id,
            'description' => 'Payroll '.$run->periodLabel().' ('.$run->code.')',
            'total_debit' => $run->net_amount,
            'total_credit' => $run->net_amount,
            'status' => 'posted',
            'created_by' => $this->financeUserId,
            'posted_by' => $this->financeUserId,
            'posted_at' => now(),
        ]);

        $entry->lines()->createMany([
            [
                'chart_of_account_id' => $salaryExpense->id,
                'description' => 'Salary expense for '.$run->periodLabel(),
                'debit' => $run->net_amount,
                'credit' => 0,
            ],
            [
                'chart_of_account_id' => $salaryPayable->id,
                'description' => 'Salary payable for '.$run->periodLabel(),
                'debit' => 0,
                'credit' => $run->net_amount,
            ],
        ]);
    }

    /**
     * A balanced manual accrual left in draft so the review-then-post flow and
     * the "unposted journals" dashboard card both have something real to show.
     */
    private function seedDraftAccrual(): void
    {
        $expense = $this->posting->account(PostingService::MATERIAL_EXPENSE);
        $payable = $this->posting->account(PostingService::PAYABLE);

        if (! $expense || ! $payable) {
            return;
        }

        $costCenter = CostCenter::where('type', 'project')->first();

        $entry = JournalEntry::create([
            'journal_number' => JournalEntry::nextNumber((int) now()->year),
            'journal_date' => now()->endOfMonth()->toDateString(),
            'reference_number' => 'ACCRUAL-001',
            'source_module' => 'Manual',
            'description' => 'Month-end accrual for materials delivered but not yet invoiced.',
            'cost_center_id' => $costCenter?->id,
            'total_debit' => 12000,
            'total_credit' => 12000,
            'status' => 'draft',
            'created_by' => $this->financeUserId,
        ]);

        $entry->lines()->createMany([
            [
                'chart_of_account_id' => $expense->id,
                'description' => 'Accrued material cost',
                'debit' => 12000,
                'credit' => 0,
                'cost_center_id' => $costCenter?->id,
            ],
            [
                'chart_of_account_id' => $payable->id,
                'description' => 'Accrued supplier liability',
                'debit' => 0,
                'credit' => 12000,
                'cost_center_id' => $costCenter?->id,
            ],
        ]);
    }

    private function recalculateVatPeriods(): void
    {
        VatPeriod::each(fn (VatPeriod $period) => $period->recalculate());
    }
}
