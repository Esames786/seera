<?php

namespace Tests\Feature;

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\VatPeriod;
use App\Models\VatTransaction;
use App\Models\ZatcaInvoiceRecord;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingTest extends TestCase
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

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    public function test_accounting_screens_return_ok(): void
    {
        $account = $this->account('5200');
        $entry = JournalEntry::firstOrFail();
        $bill = SupplierBill::firstOrFail();
        $draftBill = SupplierBill::where('status', 'draft')->firstOrFail();
        $invoice = CustomerInvoice::firstOrFail();
        $unpaidBill = SupplierBill::whereIn('status', ['unpaid', 'partially_paid'])->firstOrFail();
        $unpaidInvoice = CustomerInvoice::whereIn('payment_status', ['unpaid', 'partially_paid'])->firstOrFail();
        $vatPeriod = VatPeriod::firstOrFail();
        $zatca = ZatcaInvoiceRecord::firstOrFail();
        $costCenter = CostCenter::firstOrFail();
        $rule = AutomaticPostingRule::firstOrFail();

        $urls = [
            '/admin/accounting/dashboard',
            '/admin/accounting/chart-of-accounts', '/admin/accounting/chart-of-accounts/create',
            "/admin/accounting/chart-of-accounts/{$account->id}", "/admin/accounting/chart-of-accounts/{$account->id}/edit",
            '/admin/accounting/journal-entries', '/admin/accounting/journal-entries/create',
            "/admin/accounting/journal-entries/{$entry->id}",
            '/admin/accounting/general-ledger',
            '/admin/accounting/general-ledger?account='.$account->id,
            '/admin/accounting/accounts-payable', '/admin/accounting/accounts-payable/create',
            "/admin/accounting/accounts-payable/{$bill->id}",
            "/admin/accounting/accounts-payable/{$draftBill->id}/edit",
            "/admin/accounting/accounts-payable/{$unpaidBill->id}/payment",
            '/admin/accounting/accounts-receivable', '/admin/accounting/accounts-receivable/create',
            "/admin/accounting/accounts-receivable/{$invoice->id}",
            "/admin/accounting/accounts-receivable/{$unpaidInvoice->id}/receipt",
            '/admin/accounting/vat', "/admin/accounting/vat/{$vatPeriod->id}",
            '/admin/accounting/zatca', "/admin/accounting/zatca/{$zatca->id}",
            '/admin/accounting/reports',
            '/admin/accounting/reports/balance-sheet',
            '/admin/accounting/reports/profit-loss',
            '/admin/accounting/reports/trial-balance',
            '/admin/accounting/reports/cash-flow',
            '/admin/accounting/reports/vat-report',
            '/admin/accounting/reports/project-cost-report',
            '/admin/accounting/cost-centers', '/admin/accounting/cost-centers/create',
            "/admin/accounting/cost-centers/{$costCenter->id}", "/admin/accounting/cost-centers/{$costCenter->id}/edit",
            '/admin/accounting/posting-rules', '/admin/accounting/posting-rules/create',
            "/admin/accounting/posting-rules/{$rule->id}", "/admin/accounting/posting-rules/{$rule->id}/edit",
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->admin())->get($url)->assertOk();
        }
    }

    public function test_accounting_routes_require_authentication(): void
    {
        $this->get('/admin/accounting/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/accounting/journal-entries')->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_seeded_finance_data(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.accounting.dashboard'))
            ->assertOk()
            ->assertSee('Accounts Payable')
            ->assertSee('VAT Payable')
            ->assertSee('Unposted Journals')
            ->assertSee('ZATCA Failed Invoices');
    }

    public function test_chart_of_account_can_be_created_updated_and_deleted(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.accounting.chart-of-accounts.store'), [
                'account_code' => '5600',
                'account_name' => 'Subcontractor Expense',
                'account_type' => 'expense',
                'parent_id' => $this->account('5000')->id,
                'opening_balance' => 0,
                'normal_balance' => 'debit',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.accounting.chart-of-accounts.index'));

        $account = ChartOfAccount::where('account_code', '5600')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.accounting.chart-of-accounts.update', $account), [
                'account_code' => '5600',
                'account_name' => 'Subcontractor Costs',
                'account_type' => 'expense',
                'opening_balance' => 0,
                'normal_balance' => 'debit',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertSame('Subcontractor Costs', $account->refresh()->account_name);

        $this->actingAs($this->admin())
            ->delete(route('admin.accounting.chart-of-accounts.destroy', $account))
            ->assertRedirect();

        $this->assertDatabaseMissing('chart_of_accounts', ['id' => $account->id]);
    }

    public function test_account_with_transactions_is_deactivated_instead_of_deleted(): void
    {
        $account = $this->account('2100');

        $this->actingAs($this->admin())
            ->delete(route('admin.accounting.chart-of-accounts.destroy', $account))
            ->assertRedirect();

        $this->assertDatabaseHas('chart_of_accounts', ['id' => $account->id, 'status' => 'inactive']);
    }

    public function test_balanced_journal_entry_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.accounting.journal-entries.store'), [
                'journal_date' => '2026-08-10',
                'reference_number' => 'ADJ-001',
                'source_module' => 'Manual',
                'description' => 'Manual adjustment',
                'status' => 'draft',
                'lines' => [
                    ['chart_of_account_id' => $this->account('5300')->id, 'debit' => 1000, 'credit' => 0, 'description' => 'Fuel'],
                    ['chart_of_account_id' => $this->account('1110')->id, 'debit' => 0, 'credit' => 1000, 'description' => 'Cash'],
                ],
            ])
            ->assertRedirect();

        $entry = JournalEntry::where('reference_number', 'ADJ-001')->firstOrFail();
        $this->assertCount(2, $entry->lines);
        $this->assertSame('1000.00', (string) $entry->total_debit);
        $this->assertSame('1000.00', (string) $entry->total_credit);
        $this->assertTrue($entry->isBalanced());
    }

    public function test_unbalanced_journal_entry_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.accounting.journal-entries.store'), [
                'journal_date' => '2026-08-10',
                'reference_number' => 'BROKEN-001',
                'source_module' => 'Manual',
                'status' => 'draft',
                'lines' => [
                    ['chart_of_account_id' => $this->account('5300')->id, 'debit' => 1000, 'credit' => 0],
                    ['chart_of_account_id' => $this->account('1110')->id, 'debit' => 0, 'credit' => 400],
                ],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertDatabaseMissing('journal_entries', ['reference_number' => 'BROKEN-001']);
    }

    public function test_journal_entry_needs_at_least_two_usable_lines(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.accounting.journal-entries.store'), [
                'journal_date' => '2026-08-10',
                'source_module' => 'Manual',
                'status' => 'draft',
                'lines' => [
                    ['chart_of_account_id' => $this->account('5300')->id, 'debit' => 1000, 'credit' => 0],
                    ['chart_of_account_id' => null, 'debit' => 0, 'credit' => 0],
                ],
            ])
            ->assertSessionHasErrors('lines');
    }

    public function test_journal_post_changes_status_and_appears_in_ledger(): void
    {
        $entry = JournalEntry::where('status', 'draft')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.journal-entries.post', $entry))
            ->assertRedirect(route('admin.accounting.journal-entries.show', $entry));

        $entry->refresh();
        $this->assertSame('posted', $entry->status);
        $this->assertSame($this->admin()->id, $entry->posted_by);
        $this->assertNotNull($entry->posted_at);

        $this->actingAs($this->admin())
            ->get(route('admin.accounting.general-ledger'))
            ->assertOk()
            ->assertSee($entry->journal_number);
    }

    public function test_unbalanced_journal_cannot_be_posted(): void
    {
        $entry = JournalEntry::create([
            'journal_number' => 'JV-TEST-0001',
            'journal_date' => '2026-08-10',
            'source_module' => 'Manual',
            'total_debit' => 500,
            'total_credit' => 100,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.journal-entries.post', $entry))
            ->assertSessionHasErrors('journal');

        $this->assertSame('draft', $entry->refresh()->status);
    }

    public function test_posted_journal_cannot_be_edited(): void
    {
        $entry = JournalEntry::where('status', 'posted')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.accounting.journal-entries.edit', $entry))
            ->assertForbidden();
    }

    public function test_supplier_bill_can_be_created_and_approved_with_posting(): void
    {
        $supplier = Supplier::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.accounts-payable.store'), [
                'supplier_id' => $supplier->id,
                'bill_number' => 'BILL-TEST-001',
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'vat_rate' => 15,
                'lines' => [
                    ['description' => 'Ready-mix concrete', 'quantity' => 10, 'unit_price' => 1000, 'chart_of_account_id' => $this->account('5200')->id],
                ],
            ])
            ->assertRedirect();

        $bill = SupplierBill::where('bill_number', 'BILL-TEST-001')->firstOrFail();
        $this->assertSame('10000.00', (string) $bill->taxable_amount);
        $this->assertSame('1500.00', (string) $bill->vat_amount);
        $this->assertSame('11500.00', (string) $bill->total_amount);
        $this->assertSame('draft', $bill->status);

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.accounts-payable.approve', $bill))
            ->assertRedirect();

        $bill->refresh();
        $this->assertSame('unpaid', $bill->status);
        $this->assertNotNull($bill->journal_entry_id);

        // Debit expense + input VAT, credit accounts payable.
        $entry = $bill->journalEntry;
        $this->assertSame('11500.00', (string) $entry->total_debit);
        $this->assertSame('11500.00', (string) $entry->total_credit);
        $this->assertSame(10000.0, (float) $entry->lines->firstWhere('chart_of_account_id', $this->account('5200')->id)->debit);
        $this->assertSame(1500.0, (float) $entry->lines->firstWhere('chart_of_account_id', $this->account('1300')->id)->debit);
        $this->assertSame(11500.0, (float) $entry->lines->firstWhere('chart_of_account_id', $this->account('2100')->id)->credit);

        $this->assertDatabaseHas('vat_transactions', [
            'source_reference' => 'BILL-TEST-001',
            'vat_type' => 'input',
            'vat_amount' => 1500.00,
        ]);
    }

    public function test_supplier_payment_posts_and_updates_bill_status(): void
    {
        $bill = SupplierBill::where('status', 'unpaid')->firstOrFail();
        $balance = (float) $bill->balance_amount;

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.accounts-payable.payment.store', $bill), [
                'payment_date' => now()->toDateString(),
                'payment_account_id' => $this->account('1120')->id,
                'amount' => $balance,
                'reference_number' => 'PAY-TEST-001',
            ])
            ->assertRedirect();

        $bill->refresh();
        $this->assertSame('paid', $bill->status);
        $this->assertSame(0.0, (float) $bill->balance_amount);

        $payment = $bill->payments()->where('reference_number', 'PAY-TEST-001')->firstOrFail();
        $this->assertNotNull($payment->journal_entry_id);
        $this->assertSame($balance, (float) $payment->journalEntry->lines->firstWhere('chart_of_account_id', $this->account('2100')->id)->debit);
    }

    public function test_supplier_payment_cannot_exceed_outstanding_balance(): void
    {
        $bill = SupplierBill::where('status', 'unpaid')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.accounts-payable.payment.store', $bill), [
                'payment_date' => now()->toDateString(),
                'payment_account_id' => $this->account('1120')->id,
                'amount' => (float) $bill->balance_amount + 1000,
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_customer_invoice_can_be_created_and_approved_with_posting_and_zatca(): void
    {
        $customer = Customer::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.accounts-receivable.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'vat_rate' => 15,
                'lines' => [
                    ['description' => 'Progress claim 4', 'quantity' => 1, 'unit_price' => 200000],
                ],
            ])
            ->assertRedirect();

        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        $this->assertSame('200000.00', (string) $invoice->taxable_amount);
        $this->assertSame('30000.00', (string) $invoice->vat_amount);
        $this->assertSame('230000.00', (string) $invoice->total_amount);

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.accounts-receivable.approve', $invoice))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertSame('pending_clearance', $invoice->zatca_status);
        $this->assertNotNull($invoice->journal_entry_id);

        // Debit receivable, credit revenue + output VAT.
        $entry = $invoice->journalEntry;
        $this->assertSame(230000.0, (float) $entry->lines->firstWhere('chart_of_account_id', $this->account('1200')->id)->debit);
        $this->assertSame(200000.0, (float) $entry->lines->firstWhere('chart_of_account_id', $this->account('4100')->id)->credit);
        $this->assertSame(30000.0, (float) $entry->lines->firstWhere('chart_of_account_id', $this->account('2210')->id)->credit);

        $record = $invoice->zatcaRecord;
        $this->assertNotNull($record);
        $this->assertNotEmpty($record->uuid);
        $this->assertNotEmpty($record->qr_code_data);
        $this->assertNotEmpty($record->tamper_proof_hash);
    }

    public function test_customer_receipt_posts_and_updates_invoice_status(): void
    {
        $invoice = CustomerInvoice::where('payment_status', 'unpaid')->firstOrFail();
        $balance = (float) $invoice->balance_amount;

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), [
                'receipt_date' => now()->toDateString(),
                'receipt_account_id' => $this->account('1120')->id,
                'amount' => $balance,
                'reference_number' => 'RCPT-TEST-001',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame(0.0, (float) $invoice->balance_amount);

        $receipt = $invoice->receipts()->where('reference_number', 'RCPT-TEST-001')->firstOrFail();
        $this->assertSame($balance, (float) $receipt->journalEntry->lines->firstWhere('chart_of_account_id', $this->account('1120')->id)->debit);
    }

    public function test_vat_period_recalculates_payable_as_output_minus_input(): void
    {
        $period = VatPeriod::orderByDesc('start_date')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.vat.recalculate', $period))
            ->assertRedirect();

        $period->refresh();

        $output = (float) VatTransaction::where('vat_period_id', $period->id)->where('vat_type', 'output')->sum('vat_amount');
        $input = (float) VatTransaction::where('vat_period_id', $period->id)->where('vat_type', 'input')->sum('vat_amount');

        $this->assertEqualsWithDelta($output - $input, (float) $period->vat_payable, 0.01);
        $this->assertEqualsWithDelta($output, (float) $period->output_vat, 0.01);
    }

    public function test_zatca_record_is_created_and_failed_record_can_be_retried(): void
    {
        $record = ZatcaInvoiceRecord::where('clearance_status', 'failed')->firstOrFail();
        $before = $record->retry_count;

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.zatca.retry', $record))
            ->assertRedirect();

        $record->refresh();
        $this->assertSame('pending', $record->clearance_status);
        $this->assertSame($before + 1, $record->retry_count);
        $this->assertNull($record->failed_reason);
        $this->assertSame('pending_clearance', $record->customerInvoice->zatca_status);
    }

    public function test_only_failed_zatca_records_can_be_retried(): void
    {
        $record = ZatcaInvoiceRecord::where('clearance_status', 'cleared')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.zatca.retry', $record))
            ->assertSessionHasErrors('zatca');
    }

    public function test_cost_center_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.accounting.cost-centers.store'), [
                'code' => 'CC-TEST-001',
                'name' => 'Test Cost Center',
                'type' => 'project',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.accounting.cost-centers.index'));

        $this->assertDatabaseHas('cost_centers', ['code' => 'CC-TEST-001', 'type' => 'project']);
    }

    public function test_auto_posting_rule_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.accounting.posting-rules.store'), [
                'source_module' => 'Inventory',
                'trigger_event' => 'Inventory Purchase',
                'debit_account_id' => $this->account('5200')->id,
                'credit_account_id' => $this->account('2100')->id,
                'cost_center_rule' => 'Warehouse / Project',
                'auto_post' => 1,
                'approval_required' => 0,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.accounting.posting-rules.index'));

        $rule = AutomaticPostingRule::where('source_module', 'Inventory')
            ->where('cost_center_rule', 'Warehouse / Project')
            ->latest('id')
            ->firstOrFail();

        $this->assertTrue($rule->auto_post);
        $this->assertFalse($rule->approval_required);
    }

    public function test_trial_balance_debits_equal_credits(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.accounting.reports.trial-balance'))
            ->assertOk();

        $this->assertEqualsWithDelta(
            $response->viewData('totalDebit'),
            $response->viewData('totalCredit'),
            0.01
        );
    }

    public function test_reports_load_with_date_filters(): void
    {
        $query = ['from' => now()->startOfYear()->toDateString(), 'to' => now()->toDateString()];

        foreach (['balance-sheet', 'profit-loss', 'trial-balance', 'cash-flow', 'vat-report', 'project-cost-report'] as $report) {
            $this->actingAs($this->admin())
                ->get(route('admin.accounting.reports.'.$report, $query))
                ->assertOk();
        }
    }

    public function test_accounting_sidebar_links_to_live_screens(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.accounting.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.accounting.chart-of-accounts.index'))
            ->assertSee(route('admin.accounting.zatca.index'))
            ->assertDontSee(route('admin.coming-soon', 'chart-of-accounts'));
    }

    public function test_accounting_permissions_are_seeded_and_granted_to_finance_manager(): void
    {
        foreach (['Chart of Accounts', 'Journal Entries', 'Accounts Payable', 'Accounts Receivable', 'VAT Management', 'Cost Centers', 'Auto Posting Rules'] as $module) {
            $this->assertDatabaseHas('permissions', ['module' => $module, 'action' => 'view']);
        }

        $this->assertDatabaseHas('permissions', ['module' => 'Journal Entries', 'action' => 'post']);
        $this->assertDatabaseHas('permissions', ['module' => 'ZATCA Invoicing', 'action' => 'retry']);

        $finance = \App\Models\Role::where('code', 'FINANCE_MANAGER')->firstOrFail();
        $granted = $finance->permissions()->where('module', 'Journal Entries')->pluck('action')->all();

        $this->assertContains('post', $granted);
        $this->assertContains('approve', $granted);
    }

    public function test_permission_matrix_covers_every_action(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.roles.permission-matrix'))
            ->assertOk()
            ->assertSee('Post')
            ->assertSee('Retry')
            ->assertViewHas('actions', Permission::ACTIONS);
    }

    public function test_seed_data_covers_every_phase4_table(): void
    {
        $this->assertGreaterThan(15, ChartOfAccount::count());
        $this->assertGreaterThan(0, CostCenter::where('type', 'project')->count());
        $this->assertGreaterThan(0, JournalEntry::where('status', 'posted')->count());
        $this->assertSame(4, SupplierBill::count());
        $this->assertGreaterThan(0, \App\Models\SupplierPayment::count());
        $this->assertSame(4, CustomerInvoice::count());
        $this->assertGreaterThan(0, \App\Models\CustomerReceipt::count());
        $this->assertSame(2, VatPeriod::count());
        $this->assertGreaterThan(0, VatTransaction::where('vat_type', 'input')->count());
        $this->assertGreaterThan(0, VatTransaction::where('vat_type', 'output')->count());
        $this->assertSame(1, ZatcaInvoiceRecord::where('clearance_status', 'cleared')->count());
        $this->assertSame(1, ZatcaInvoiceRecord::where('clearance_status', 'failed')->count());
        $this->assertGreaterThan(0, AutomaticPostingRule::count());

        // The Phase 3 payroll run is mirrored into the ledger.
        $this->assertDatabaseHas('journal_entries', ['source_module' => 'Payroll', 'status' => 'posted']);
    }
}
