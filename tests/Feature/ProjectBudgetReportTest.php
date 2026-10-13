<?php

namespace Tests\Feature;

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteExpense;
use App\Models\StockIssue;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierBillGrnMatch;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\ProjectCostReport;
use App\Services\Payroll\PayrollAccountingService;
use App\Services\Payroll\PayrollSettlementService;
use App\Services\Projects\ProjectBudgetReport;
use App\Services\Projects\ProjectBudgetService;
use App\Support\ReportPeriod;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Budget vs Actual: actual cost is the posted ledger (Project Cost Report
 * semantics) bucketed by the accounts the budget lines map to; committed cost
 * is the unreceived value of approved purchase orders; receipts, GRNI, drafts,
 * other projects, payroll item totals and salary settlements never count.
 */
class ProjectBudgetReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected Project $other;

    protected Site $site;

    protected Item $steel;

    protected Warehouse $store;

    protected Supplier $supplier;

    protected ChartOfAccount $salary;

    protected ChartOfAccount $material;

    protected ChartOfAccount $fuel;

    protected ChartOfAccount $maintenance;

    protected ProjectBudget $budget;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->project = Project::create(['code' => 'BOQ-R-A', 'name' => 'Riyadh Commercial Tower', 'budget' => 500000, 'status' => 'active']);
        $this->other = Project::create(['code' => 'BOQ-R-B', 'name' => 'Jeddah Warehouse', 'status' => 'active']);
        $this->site = Site::create(['name' => 'Tower Main Site', 'code' => 'BOQ-R-S1', 'project_id' => $this->project->id, 'status' => 'active']);
        CostCenter::create(['code' => 'CC-BOQ-R-A', 'name' => 'Riyadh Commercial Tower', 'type' => 'project', 'linked_id' => $this->project->id, 'status' => 'active']);
        $this->salary = ChartOfAccount::where('account_code', '5100')->firstOrFail();
        $this->material = ChartOfAccount::where('account_code', '5200')->firstOrFail();
        $this->fuel = ChartOfAccount::where('account_code', '5300')->firstOrFail();
        $this->maintenance = ChartOfAccount::where('account_code', '5400')->firstOrFail();
        $unit = Unit::create(['code' => 'BOQ-R-T', 'name' => 'Tonne']);
        $this->steel = Item::create(['item_code' => 'BOQ-R-STEEL', 'name' => 'Reinforcement steel 16mm', 'unit_id' => $unit->id, 'status' => 'active',
            'expense_account_id' => $this->material->id, 'inventory_account_id' => ChartOfAccount::where('account_code', '1400')->value('id')]);
        $this->store = Warehouse::create(['name' => 'Tower store', 'code' => 'BOQ-R-WH', 'project_id' => $this->project->id, 'site_id' => $this->site->id, 'status' => 'active']);
        $this->supplier = Supplier::first();
        AutomaticPostingRule::where('source_module', 'Site Expense')->update(['auto_post' => true]);

        $service = app(ProjectBudgetService::class);
        $this->budget = $service->createDraft($this->project, ['title' => 'Baseline'], $this->admin->id);
        $service->addLine($this->budget, ['cost_code' => 'MAT-001', 'category' => 'materials', 'description' => 'Reinforcement steel', 'site_id' => $this->site->id, 'item_id' => $this->steel->id, 'quantity' => 100, 'rate' => 10, 'chart_of_account_id' => $this->material->id], $this->admin->id);
        $service->addLine($this->budget, ['cost_code' => 'MAT-002', 'category' => 'materials', 'description' => 'Consumables (planning only)', 'amount' => 50], $this->admin->id);
        $service->addLine($this->budget, ['cost_code' => 'LAB-001', 'category' => 'labour', 'description' => 'Site labour', 'quantity' => 20, 'rate' => 40, 'chart_of_account_id' => $this->salary->id], $this->admin->id);
        $service->addLine($this->budget, ['cost_code' => 'FUEL-001', 'category' => 'site_expenses', 'description' => 'Generator fuel', 'site_id' => $this->site->id, 'amount' => 500, 'chart_of_account_id' => $this->fuel->id], $this->admin->id);
        $service->addLine($this->budget, ['cost_code' => 'EQP-001', 'category' => 'equipment', 'description' => 'Crane hire', 'amount' => 30000], $this->admin->id);
        $this->budget = $service->finalize($this->budget->id, $this->admin->id);
    }

    // ---------------------------------------------------------------- fixtures

    public function test_review_legacy_cross_category_mapping_does_not_double_count_actual(): void
    {
        // Historical data accepted before the validation fix: do not rewrite the frozen version.
        $this->budget->lines()->create(['cost_code' => 'MAT-001', 'category' => 'equipment', 'description' => 'Legacy ambiguous mapping',
            'amount' => 25, 'chart_of_account_id' => $this->material->id]);
        $entry = JournalEntry::create(['journal_number' => 'REVIEW-BOQ', 'journal_date' => today(), 'source_module' => 'Manual', 'status' => 'posted', 'total_debit' => 100, 'total_credit' => 100]);
        $entry->lines()->create(['chart_of_account_id' => $this->material->id, 'project_id' => $this->project->id, 'debit' => 100, 'credit' => 0]);
        $entry->lines()->create(['chart_of_account_id' => ChartOfAccount::where('account_code', PostingService::CASH)->value('id'), 'project_id' => $this->project->id, 'debit' => 0, 'credit' => 100]);
        $report = $this->report();
        $this->assertCount(1, $report['mapping_conflicts']);
        $this->assertEquals(100, $report['totals']['actual']);
        $this->assertEquals(100, $report['unmapped']['actual']);
        $this->assertEquals($report['totals']['actual'], array_sum(array_column($report['rows'], 'actual')) + $report['unmapped']['actual']);
        $this->actingAs($this->admin)->get(route('admin.master.projects.budget.show', $this->project))->assertOk()->assertSee('Ambiguous account mapping');
    }

    /** @param array<string, array<int, string>> $grants */
    protected function user(array $grants, ?Project $project = null, string $suffix = 'u'): User
    {
        $suffix .= '-'.++$this->seq;
        $role = Role::create(['name' => 'BOQR role '.$suffix, 'code' => 'BOQR_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'BOQR '.$suffix, 'email' => 'boqr-'.$suffix.'@example.test', 'username' => 'boqr.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    protected function report(?int $siteId = null): array
    {
        return app(ProjectBudgetReport::class)->build($this->project->fresh(), $this->budget->fresh(), $siteId);
    }

    protected function row(array $report, string $costCode): array
    {
        foreach ($report['rows'] as $row) {
            if ($row['cost_code'] === $costCode) {
                return $row;
            }
        }
        $this->fail('Row '.$costCode.' missing');
    }

    protected function projectCost(Project $project): float
    {
        return (float) app(ProjectCostReport::class)->rows(Project::whereKey($project->id)->get(), new ReportPeriod(null, null, 'custom'))->first()['cost'];
    }

    /** Approved PO for 100 t steel at 10, 40 received (committed = 60 × 10). */
    protected function purchaseOrder(Project $project, string $number, string $status = 'approved', float $received = 40): PurchaseOrder
    {
        $order = PurchaseOrder::create(['po_number' => $number, 'supplier_id' => $this->supplier->id, 'po_date' => '2026-09-01', 'project_id' => $project->id, 'site_id' => $project->id === $this->project->id ? $this->site->id : null,
            'warehouse_id' => $this->store->id, 'taxable_amount' => 1000, 'vat_rate' => 0, 'vat_amount' => 0, 'total_amount' => 1000, 'status' => $status]);
        $order->lines()->create(['item_id' => $this->steel->id, 'description' => 'Steel', 'quantity' => 100, 'received_quantity' => $received, 'unit_price' => 10, 'discount_percent' => 0, 'discount_amount' => 0, 'taxable_amount' => 1000, 'vat_rate' => 0, 'vat_amount' => 0, 'total_amount' => 1000]);

        return $order;
    }

    protected function postedReceipt(PurchaseOrder $order): GoodsReceipt
    {
        $grn = GoodsReceipt::create(['grn_number' => 'BOQ-GRN-'.$order->id, 'purchase_order_id' => $order->id, 'supplier_id' => $this->supplier->id, 'warehouse_id' => $this->store->id, 'received_date' => '2026-09-05', 'status' => 'posted', 'stock_updated' => true, 'taxable_amount' => 400, 'total_amount' => 400]);
        $grn->lines()->create(['item_id' => $this->steel->id, 'purchase_order_line_id' => $order->lines()->value('id'), 'ordered_quantity' => 100, 'received_quantity' => 40, 'accepted_quantity' => 40, 'unit_cost' => 10, 'total_cost' => 400]);
        $entry = app(PostingService::class)->postGoodsReceipt($grn, $this->admin->id);
        $grn->update(['journal_entry_id' => $entry->id, 'accounting_posted' => true]);
        $this->assertSame('posted', $entry->status);

        return $grn;
    }

    protected function postedIssue(float $quantity = 30): StockIssue
    {
        $issue = StockIssue::create(['issue_number' => 'BOQ-ISS-'.(++$this->seq), 'warehouse_id' => $this->store->id, 'project_id' => $this->project->id, 'site_id' => $this->site->id, 'issue_date' => '2026-09-10', 'status' => 'posted', 'total_cost' => $quantity * 10]);
        $issue->lines()->create(['item_id' => $this->steel->id, 'quantity' => $quantity, 'unit_cost' => 10, 'total_cost' => $quantity * 10]);
        StockLedgerEntry::create(['warehouse_id' => $this->store->id, 'item_id' => $this->steel->id, 'project_id' => $this->project->id, 'site_id' => $this->site->id, 'movement_type' => 'issue', 'reference_type' => StockIssue::class, 'reference_id' => $issue->id, 'reference_number' => $issue->issue_number, 'movement_date' => '2026-09-10', 'out_quantity' => $quantity, 'unit_cost' => 10, 'value' => $quantity * 10]);
        $entry = app(PostingService::class)->postStockIssue($issue, $this->admin->id);
        $issue->update(['journal_entry_id' => $entry->id, 'accounting_posted' => true]);
        $this->assertSame('posted', $entry->status);

        return $issue;
    }

    /** Posted bill: one direct fuel line (200) and one GRN-matched steel line (400, clears GRNI, no expense). */
    protected function postedBill(GoodsReceipt $grn): SupplierBill
    {
        $bill = SupplierBill::create(['supplier_id' => $this->supplier->id, 'bill_number' => 'BOQ-BILL-'.(++$this->seq), 'bill_date' => '2026-09-12', 'project_id' => $this->project->id, 'site_id' => $this->site->id, 'taxable_amount' => 600, 'vat_rate' => 0, 'vat_amount' => 0, 'total_amount' => 600, 'balance_amount' => 600, 'status' => 'approved']);
        $bill->lines()->create(['description' => 'Generator diesel', 'chart_of_account_id' => $this->fuel->id, 'quantity' => 1, 'unit_price' => 200, 'taxable_amount' => 200, 'vat_rate' => 0, 'vat_amount' => 0, 'total_amount' => 200]);
        $matched = $bill->lines()->create(['description' => 'Steel invoiced', 'chart_of_account_id' => $this->material->id, 'quantity' => 40, 'unit_price' => 10, 'taxable_amount' => 400, 'vat_rate' => 0, 'vat_amount' => 0, 'total_amount' => 400]);
        SupplierBillGrnMatch::create(['supplier_bill_id' => $bill->id, 'supplier_bill_line_id' => $matched->id, 'goods_receipt_id' => $grn->id, 'goods_receipt_line_id' => $grn->lines()->value('id'), 'matched_quantity' => 40, 'matched_taxable_amount' => 400, 'committed_at' => now()]);
        $entry = app(PostingService::class)->postSupplierBill($bill->fresh(), $this->admin->id);
        $bill->update(['journal_entry_id' => $entry->id]);
        $this->assertSame('posted', $entry->status);

        return $bill;
    }

    protected function postedSiteExpense(float $amount = 150): SiteExpense
    {
        $category = ExpenseCategory::create(['name' => 'Maintenance BOQ', 'code' => 'BOQ-MNT-'.(++$this->seq), 'chart_of_account_id' => $this->maintenance->id, 'payment_type' => 'Cash', 'status' => 'active']);
        $expense = SiteExpense::create(['expense_number' => 'BOQ-SE-'.$this->seq, 'expense_date' => '2026-09-15', 'submitted_by_user_id' => $this->admin->id, 'project_id' => $this->project->id, 'site_id' => $this->site->id,
            'expense_category_id' => $category->id, 'payment_type' => 'Cash', 'description' => 'Pump repair', 'taxable_amount' => $amount, 'vat_rate' => 0, 'vat_amount' => 0, 'total_amount' => $amount, 'status' => 'posted']);
        $entry = app(PostingService::class)->postSiteExpense($expense, $this->maintenance, ChartOfAccount::where('account_code', PostingService::CASH)->firstOrFail(), null, $this->admin->id);
        $expense->update(['journal_entry_id' => $entry->id, 'accounting_posted' => true]);
        $this->assertSame('posted', $entry->status);

        return $expense;
    }

    /** Approved payroll run for one project employee (gross 1,000) posted through the Payroll → GL rule. */
    protected function postedPayroll(): PayrollRun
    {
        $employee = Employee::create(['employee_code' => 'BOQ-R-EMP', 'first_name' => 'Ahmed', 'last_name' => 'Hassan', 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $this->project->id, 'basic_salary' => 1000]);
        $run = PayrollRun::create(['code' => 'PR-BOQ-R', 'payroll_month' => 9, 'payroll_year' => 2026, 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'status' => 'approved', 'approved_by' => $this->admin->id, 'approved_at' => now(), 'total_employees' => 1, 'gross_amount' => 1000, 'total_deductions' => 0, 'net_amount' => 1000]);
        PayrollRunItem::create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'basic_salary' => 1000, 'total_allowances' => 0, 'overtime_amount' => 0, 'total_deductions' => 0, 'gross_amount' => 1000, 'net_amount' => 1000, 'present_days' => 22, 'leave_days' => 0]);
        app(PayrollAccountingService::class)->attempt($run->id, $this->admin->id);
        $this->assertSame(PayrollRun::ACCOUNTING_POSTED, $run->fresh()->accounting_status);

        return $run->fresh();
    }

    // ---------------------------------------------------------------- tests

    public function test_actual_cost_comes_from_posted_ledger_lines_once_and_reconciles_with_the_project_cost_report(): void
    {
        $this->actingAs($this->admin);
        $order = $this->purchaseOrder($this->project, 'BOQ-PO-1');
        $grn = $this->postedReceipt($order);          // Dr inventory 400 / Cr GRNI 400: not cost
        $this->postedIssue(30);                       // Dr material 300: cost
        $this->postedBill($grn);                      // Dr fuel 200 + Dr GRNI 400 (matched): 200 cost
        $this->postedSiteExpense(150);                // Dr maintenance 150: cost on an unmapped account
        $run = $this->postedPayroll();                // Dr salary 1,000: cost (once, through the GL)
        // Noise that must not count: a draft journal, another project's cost, revenue.
        $draft = JournalEntry::create(['journal_number' => 'BOQ-DRAFT', 'journal_date' => '2026-09-20', 'source_module' => 'Manual', 'status' => 'draft']);
        $draft->lines()->create(['chart_of_account_id' => $this->material->id, 'project_id' => $this->project->id, 'debit' => 999, 'credit' => 0]);
        $foreign = JournalEntry::create(['journal_number' => 'BOQ-OTHER', 'journal_date' => '2026-09-20', 'source_module' => 'Manual', 'status' => 'posted']);
        $foreign->lines()->create(['chart_of_account_id' => $this->material->id, 'project_id' => $this->other->id, 'debit' => 777, 'credit' => 0]);
        $revenue = JournalEntry::create(['journal_number' => 'BOQ-REV', 'journal_date' => '2026-09-20', 'source_module' => 'Manual', 'status' => 'posted']);
        $revenue->lines()->create(['chart_of_account_id' => ChartOfAccount::where('account_code', '4100')->value('id'), 'project_id' => $this->project->id, 'debit' => 0, 'credit' => 5000]);

        $report = $this->report();
        $this->assertEqualsWithDelta(1650, $report['totals']['actual'], 0.001, '300 material + 200 fuel + 150 maintenance + 1,000 salary');
        $this->assertEqualsWithDelta($this->projectCost($this->project), $report['totals']['actual'], 0.001, 'total actual equals the Project Cost Report');
        $this->assertEqualsWithDelta(777, $this->projectCost($this->other), 0.001);

        $mat = $this->row($report, 'MAT-001');
        $this->assertEqualsWithDelta(1000, $mat['budget'], 0.001);
        $this->assertEqualsWithDelta(300, $mat['actual'], 0.001, 'material actual = posted stock issue, not the receipt, GRNI or PO');
        $this->assertEqualsWithDelta(600, $mat['committed'], 0.001, 'committed = (100 − 40 received) × 10 net unit price');
        $this->assertEqualsWithDelta(700, $mat['remaining'], 0.001);
        $this->assertEqualsWithDelta(100, $mat['variance'], 0.001, 'budget − actual − committed');
        $this->assertSame('Within budget', $mat['status']);
        $this->assertSame(['quantity' => 30.0, 'value' => 300.0], $report['issued'][$this->steel->id]);

        $lab = $this->row($report, 'LAB-001');
        $this->assertEqualsWithDelta(1000, $lab['actual'], 0.001, 'payroll reaches the project once, through the posted journal');
        $this->assertEqualsWithDelta(-200, $lab['remaining'], 0.001);
        $this->assertSame('Over budget', $lab['status']);
        $this->assertEqualsWithDelta(1000, (float) PayrollRunItem::where('payroll_run_id', $run->id)->sum('gross_amount'), 0.001, 'item totals exist but are not added on top');

        $fuel = $this->row($report, 'FUEL-001');
        $this->assertEqualsWithDelta(200, $fuel['actual'], 0.001, 'direct bill line counts; the GRN-matched line clears GRNI and is not expense');
        $this->assertEqualsWithDelta(0, $fuel['committed'], 0.001);

        $eqp = $this->row($report, 'EQP-001');
        $this->assertNull($eqp['actual']);
        $this->assertSame('No account mapped', $eqp['status']);
        $this->assertNull($this->row($report, 'MAT-002')['actual']);

        $this->assertEqualsWithDelta(150, $report['unmapped']['actual'], 0.001, 'posted maintenance cost on an account no line maps to is shown, never hidden');
        $this->assertContains($this->maintenance->label(), $report['unmapped']['accounts']);
        $this->assertEqualsWithDelta(32350, $report['totals']['budget'], 0.001, '1,000 + 50 + 800 + 500 + 30,000');
        $this->assertEqualsWithDelta(600, $report['totals']['committed'], 0.001);
        $this->assertEqualsWithDelta(32350 - 1650, $report['totals']['remaining'], 0.001);
        $this->assertFalse($report['totals']['over_budget']);
        $equipment = collect($report['categories'])->firstWhere('category', 'equipment');
        $this->assertStringContainsString('posted fuel, maintenance', $equipment['integration']);
        $this->assertNull($equipment['actual']);
        $materials = collect($report['categories'])->firstWhere('category', 'materials');
        $this->assertEqualsWithDelta(1050, $materials['budget'], 0.001);
        $this->assertEqualsWithDelta(300, $materials['actual'], 0.001);

        // Salary settlement moves payable and bank only: project cost and the report are unchanged.
        $bank = ChartOfAccount::where('account_code', PostingService::BANK)->firstOrFail();
        app(PayrollSettlementService::class)->settle($run->id, ['payment_date' => '2026-10-05', 'payment_account_id' => $bank->id, 'idempotency_key' => (string) Str::uuid()], $this->admin->id);
        $this->assertEqualsWithDelta(1650, $this->report()['totals']['actual'], 0.001);
        $this->assertEqualsWithDelta(1650, $this->projectCost($this->project), 0.001);

        // The CSV carries the same figures, including the unmapped row and the total.
        $csv = app(ProjectBudgetReport::class)->csvRows($this->report());
        $this->assertSame('Category', $csv[0][0]);
        $this->assertContains('Posted cost / commitments on accounts no budget line maps to', array_column($csv, 2));
        $this->assertSame('1650.00', end($csv)[8]);
    }

    public function test_commitments_use_only_open_approved_orders_of_this_project(): void
    {
        $this->actingAs($this->admin);
        $this->purchaseOrder($this->project, 'BOQ-PO-OPEN');                       // 60 outstanding → 600
        $this->purchaseOrder($this->project, 'BOQ-PO-DRAFT', 'draft', 0);           // excluded
        $this->purchaseOrder($this->project, 'BOQ-PO-CANCELLED', 'cancelled', 0);   // excluded
        $this->purchaseOrder($this->project, 'BOQ-PO-DONE', 'received', 100);       // fully received → 0
        $this->purchaseOrder($this->other, 'BOQ-PO-OTHER');                         // other project → excluded
        $report = $this->report();
        $this->assertEqualsWithDelta(600, $report['totals']['committed'], 0.001);
        $this->assertEqualsWithDelta(600, $this->row($report, 'MAT-001')['committed'], 0.001);
        $this->assertEqualsWithDelta(0, $report['totals']['actual'], 0.001, 'ordering is not cost');

        // Receiving part of the order moves value out of commitment but not into cost until issued.
        $open = PurchaseOrder::where('po_number', 'BOQ-PO-OPEN')->firstOrFail();
        $open->lines()->update(['received_quantity' => 70]);
        $this->postedReceipt($open);
        $report = $this->report();
        $this->assertEqualsWithDelta(300, $report['totals']['committed'], 0.001);
        $this->assertEqualsWithDelta(0, $report['totals']['actual'], 0.001, 'GRN / GRNI is not project cost');
        $this->postedIssue(10);
        $report = $this->report();
        $this->assertEqualsWithDelta(300, $report['totals']['committed'], 0.001);
        $this->assertEqualsWithDelta(100, $report['totals']['actual'], 0.001);
        $this->assertEqualsWithDelta(600, $this->row($report, 'MAT-001')['variance'], 0.001, '1,000 − 100 − 300');
    }

    public function test_site_filter_compares_site_lines_with_site_tagged_cost(): void
    {
        $this->actingAs($this->admin);
        $this->postedIssue(30);                     // site-tagged (issue carries the site)
        $this->postedPayroll();                     // salary lines carry no site
        $report = $this->report($this->site->id);
        $this->assertEqualsWithDelta(1500, $report['totals']['budget'], 0.001, 'MAT-001 1,000 + FUEL-001 500 are the site lines');
        $this->assertEqualsWithDelta(300, $report['totals']['actual'], 0.001, 'only cost posted with this site');
        $this->assertEqualsWithDelta(1000, $this->report()['totals']['actual'] - $report['totals']['actual'], 0.001, 'the salary lines carry no site');
        $this->assertNull(collect($report['rows'])->firstWhere('cost_code', 'LAB-001'));
    }

    public function test_project_scoped_viewer_sees_only_own_project_figures_and_page_matches_the_service(): void
    {
        $this->actingAs($this->admin);
        $this->postedIssue(30);
        $foreign = JournalEntry::create(['journal_number' => 'BOQ-OTHER-2', 'journal_date' => '2026-09-20', 'source_module' => 'Manual', 'status' => 'posted']);
        $foreign->lines()->create(['chart_of_account_id' => $this->material->id, 'project_id' => $this->other->id, 'debit' => 777, 'credit' => 0]);
        $this->purchaseOrder($this->other, 'BOQ-PO-OTHER-2');

        $viewer = $this->user(['Projects' => ['view'], 'Financial Reports' => ['view']], $this->project, 'scoped');
        $page = $this->actingAs($viewer)->get(route('admin.master.projects.budget.show', $this->project))->assertOk();
        $page->assertSee('Actual cost (posted)')->assertSee('SAR 300.00')->assertDontSee('777.00');
        $this->assertEqualsWithDelta(300, $page->viewData('report')['totals']['actual'], 0.001);
        $this->assertEqualsWithDelta(0, $page->viewData('report')['totals']['committed'], 0.001, 'the other project\'s open order is not a commitment here');
        $this->actingAs($viewer)->get(route('admin.master.projects.budget.show', $this->other))->assertNotFound();
        // Without Financial Reports view the same page shows budget figures only.
        $planner = $this->user(['Projects' => ['view']], $this->project, 'planner');
        $this->actingAs($planner)->get(route('admin.master.projects.budget.show', $this->project))->assertOk()->assertSee('1,000.00')->assertDontSee('Actual cost (posted)')->assertDontSee('SAR 300.00');
    }

    public function test_finance_panel_and_header_use_the_same_totals_as_the_budget_page(): void
    {
        $this->actingAs($this->admin);
        $this->postedIssue(30);
        $this->purchaseOrder($this->project, 'BOQ-PO-H');
        $totals = $this->report()['totals'];
        $this->actingAs($this->admin)->get(route('admin.master.projects.show', $this->project))->assertOk()
            ->assertSee('Detailed current budget: SAR '.number_format($totals['budget'], 2).' (BOQ-R-A-B01)')
            ->assertSee('Committed (SAR):</strong> 600.00', false)
            ->assertSee('Remaining detailed budget (SAR):</strong> '.number_format($totals['remaining'], 2), false);
        $html = $this->actingAs($this->admin)->getJson(route('admin.master.projects.workspace.panel', [$this->project, 'finance']))->assertOk()->json('html');
        $this->assertStringContainsString('Committed (open purchase orders)</th><td>SAR 600.00', $html);
        $this->assertStringContainsString('Actual (posted cost, same as above)</th><td>SAR 300.00', $html);
    }
}
