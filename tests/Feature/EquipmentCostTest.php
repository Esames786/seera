<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\EquipmentAsset;
use App\Models\EquipmentFuelLog;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\VatTransaction;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\ProjectCostReport;
use App\Services\Projects\ProjectBudgetReport;
use App\Services\Projects\ProjectBudgetService;
use App\Support\ReportPeriod;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Equipment cost: fuel and maintenance are operational until Finance posts
 * them; Cash / Bank post one journal, Supplier Credit creates one supplier bill
 * (AP once); posted cost reaches the Project Cost Report and the BOQ Equipment
 * category exactly once.
 */
class EquipmentCostTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $finance;

    protected Project $project;

    protected Site $site;

    protected EquipmentAsset $asset;

    protected ChartOfAccount $fuelAccount;

    protected ChartOfAccount $maintenanceAccount;

    protected ChartOfAccount $cash;

    protected ChartOfAccount $bank;

    protected ExpenseCategory $fuelCategory;

    protected ExpenseCategory $maintenanceCategory;

    protected Supplier $supplier;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->project = Project::create(['code' => 'EQC-A', 'name' => 'Riyadh Commercial Tower', 'status' => 'active']);
        CostCenter::create(['code' => 'CC-EQC-A', 'name' => 'Riyadh Commercial Tower', 'type' => 'project', 'linked_id' => $this->project->id, 'status' => 'active']);
        $this->site = Site::create(['name' => 'Tower Main Site', 'code' => 'EQC-A-S1', 'project_id' => $this->project->id, 'status' => 'active']);
        $this->fuelAccount = ChartOfAccount::where('account_code', '5300')->firstOrFail();
        $this->maintenanceAccount = ChartOfAccount::where('account_code', '5400')->firstOrFail();
        $this->cash = ChartOfAccount::where('account_code', PostingService::CASH)->firstOrFail();
        $this->bank = ChartOfAccount::where('account_code', PostingService::BANK)->firstOrFail();
        $this->fuelCategory = ExpenseCategory::create(['name' => 'Diesel (equipment)', 'code' => 'EQC-FUEL', 'chart_of_account_id' => $this->fuelAccount->id, 'payment_type' => 'Cash', 'status' => 'active']);
        $this->maintenanceCategory = ExpenseCategory::create(['name' => 'Equipment workshop', 'code' => 'EQC-MNT', 'chart_of_account_id' => $this->maintenanceAccount->id, 'payment_type' => 'Bank', 'status' => 'active']);
        $this->supplier = Supplier::where('status', 'active')->firstOrFail();
        $this->finance = $this->user(['Equipment' => ['view', 'create', 'edit', 'process', 'post', 'export'], 'Financial Reports' => ['view'], 'Journal Entries' => ['view'], 'Accounts Payable' => ['view'], 'Projects' => ['view']], suffix: 'finance');
        $this->actingAs($this->admin)->post(route('admin.equipment.store'), ['asset_code' => 'EXC-100', 'name' => 'Excavator', 'asset_type' => 'equipment', 'ownership_type' => 'owned', 'meter_type' => 'hours', 'current_meter' => 1000])->assertSessionHasNoErrors();
        $this->asset = EquipmentAsset::where('asset_code', 'EXC-100')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.equipment.assign', $this->asset), ['project_id' => $this->project->id, 'site_id' => $this->site->id, 'assigned_from' => '2026-10-01'])->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string, array<int, string>> $grants */
    protected function user(array $grants, ?Project $project = null, string $suffix = 'u'): User
    {
        $suffix .= '-'.++$this->seq;
        $role = Role::create(['name' => 'EQC role '.$suffix, 'code' => 'EQC_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'EQC '.$suffix, 'email' => 'eqc-'.$suffix.'@example.test', 'username' => 'eqc.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    /** @param array<string, mixed> $overrides */
    protected function fuel(array $overrides = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->post(route('admin.equipment.fuel.store', $this->asset), $overrides + [
            'fuel_date' => '2026-10-03', 'quantity' => 100, 'rate' => 3.5, 'vat_rate' => 15, 'payment_type' => 'Cash', 'payment_account_id' => $this->cash->id, 'expense_category_id' => $this->fuelCategory->id, 'total_amount' => 1,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    protected function maintenance(array $overrides = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->post(route('admin.equipment.maintenance.store', $this->asset), $overrides + [
            'service_date' => '2026-10-04', 'maintenance_type' => 'repair', 'description' => 'Hydraulic hose replacement', 'cost' => 1000, 'vat_rate' => 0,
            'payment_type' => 'Bank', 'payment_account_id' => $this->bank->id, 'expense_category_id' => $this->maintenanceCategory->id,
        ]);
    }

    protected function projectCost(): float
    {
        return (float) app(ProjectCostReport::class)->rows(Project::whereKey($this->project->id)->get(), new ReportPeriod(null, null, 'custom'))->first()['cost'];
    }

    protected function postBill(SupplierBill $bill): JournalEntry
    {
        // Same ledger path Finance uses once the bill's runtime approval completes.
        $entry = app(PostingService::class)->postSupplierBill($bill->fresh(), $this->admin->id);
        $bill->update(['status' => 'unpaid', 'journal_entry_id' => $entry->id, 'balance_amount' => $bill->total_amount]);

        return $entry;
    }

    // ---------------------------------------------------------------- fuel

    public function test_fuel_total_is_server_calculated_meter_moves_forward_only_and_nothing_is_cost_until_posted(): void
    {
        $this->fuel(['meter_reading' => 1050])->assertSessionHasNoErrors();
        $log = EquipmentFuelLog::firstOrFail();
        $this->assertEqualsWithDelta(350, (float) $log->taxable_amount, 0.001, '100 × 3.5; the submitted total of 1 is ignored');
        $this->assertEqualsWithDelta(52.5, (float) $log->vat_amount, 0.001);
        $this->assertEqualsWithDelta(402.5, (float) $log->total_amount, 0.001);
        $this->assertSame($this->project->id, $log->project_id, 'project from the active assignment');
        $this->assertSame($this->site->id, $log->site_id);
        $this->assertSame(EquipmentFuelLog::ACC_NOT_POSTED, $log->accounting_status);
        $this->assertMatchesRegularExpression('/^FUEL-2026-\d{4}$/', $log->fuel_number);
        $this->assertEqualsWithDelta(1050, (float) $this->asset->fresh()->current_meter, 0.01);
        $this->assertEqualsWithDelta(0, $this->projectCost(), 0.001, 'an operational fuel record is not project cost');
        $this->assertSame(0, JournalEntry::where('source_module', EquipmentFuelLog::SOURCE_MODULE)->count());

        $this->fuel(['meter_reading' => 1040])->assertSessionHasErrors('meter_reading');
        $this->assertEqualsWithDelta(1050, (float) $this->asset->fresh()->current_meter, 0.01);
        $this->fuel(['meter_reading' => 1040, 'meter_correction' => 1])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(1040, (float) $this->asset->fresh()->current_meter, 0.01);
        $this->assertTrue(ActivityLog::where('module', 'Equipment')->where('action', 'Corrected equipment meter')->exists());
        $this->fuel(['fuel_date' => today()->addDay()->toDateString()])->assertSessionHasErrors('fuel_date');
        $this->fuel(['quantity' => 0])->assertSessionHasErrors('quantity');
        $this->fuel(['payment_type' => 'Supplier Credit', 'supplier_id' => null])->assertSessionHasErrors('supplier_id');
        $this->fuel(['payment_account_id' => $this->fuelAccount->id])->assertSessionHasErrors('payment_account_id');
        $this->fuel(['expense_category_id' => 999999])->assertSessionHasErrors('expense_category_id');
        $this->fuel(['vat_rate' => 7])->assertSessionHasErrors('vat_rate');
        $this->fuel([], $this->user(['Equipment' => ['view']], suffix: 'viewer'))->assertForbidden();
        $this->assertSame(2, EquipmentFuelLog::count());
    }

    public function test_posted_fuel_counts_once_in_project_cost_and_boq_and_journal_is_source_controlled(): void
    {
        $this->fuel()->assertSessionHasNoErrors();
        $log = EquipmentFuelLog::firstOrFail();
        $vatBefore = VatTransaction::count();
        $this->actingAs($this->user(['Equipment' => ['view', 'create']], suffix: 'nopost'))->post(route('admin.equipment.fuel.post', [$this->asset, $log->id]))->assertForbidden();
        $this->actingAs($this->finance)->post(route('admin.equipment.fuel.post', [$this->asset, $log->id]))->assertSessionHasNoErrors();
        $log->refresh();
        $this->assertSame(EquipmentFuelLog::ACC_POSTED, $log->accounting_status);
        $journal = $log->journalEntry;
        $this->assertSame('posted', $journal->status);
        $this->assertSame(EquipmentFuelLog::SOURCE_MODULE, $journal->source_module);
        $this->assertEqualsWithDelta(350, (float) $journal->lines->where('chart_of_account_id', $this->fuelAccount->id)->sum('debit'), 0.001, 'Dr fuel expense (category account)');
        $this->assertEqualsWithDelta(52.5, (float) $journal->lines->where('chart_of_account_id', ChartOfAccount::where('account_code', '1300')->value('id'))->sum('debit'), 0.001, 'Dr input VAT');
        $this->assertEqualsWithDelta(402.5, (float) $journal->lines->where('chart_of_account_id', $this->cash->id)->sum('credit'), 0.001, 'Cr cash');
        foreach ($journal->lines as $line) {
            $this->assertSame($this->project->id, $line->project_id);
            $this->assertSame($this->site->id, $line->site_id);
        }
        $this->assertSame($vatBefore + 1, VatTransaction::count());
        $this->assertEqualsWithDelta(350, $this->projectCost(), 0.001, 'posted fuel is project cost once (net of VAT)');

        // Idempotent: posting again creates nothing.
        $this->actingAs($this->finance)->post(route('admin.equipment.fuel.post', [$this->asset, $log->id]))->assertSessionHasNoErrors();
        $this->assertSame(1, JournalEntry::where('source_module', EquipmentFuelLog::SOURCE_MODULE)->count());
        $this->assertEqualsWithDelta(350, $this->projectCost(), 0.001);

        // The journal is source-controlled in Accounting.
        $this->actingAs($this->admin)->get(route('admin.accounting.journal-entries.edit', $journal))->assertForbidden();
        $this->actingAs($this->admin)->delete(route('admin.accounting.journal-entries.destroy', $journal))->assertForbidden();

        // BOQ: an Equipment line mapped to the fuel account now shows the actual; the category note is truthful.
        $budgetService = app(ProjectBudgetService::class);
        $budget = $budgetService->createDraft($this->project, ['title' => 'Baseline'], $this->admin->id);
        $budgetService->addLine($budget, ['cost_code' => 'EQP-001', 'category' => 'equipment', 'description' => 'Excavator fuel', 'amount' => 5000, 'chart_of_account_id' => $this->fuelAccount->id], $this->admin->id);
        $budget = $budgetService->finalize($budget->id, $this->admin->id);
        $report = app(ProjectBudgetReport::class)->build($this->project->fresh(), $budget);
        $equipment = collect($report['categories'])->firstWhere('category', 'equipment');
        $this->assertEqualsWithDelta(350, $equipment['actual'], 0.001);
        $this->assertStringContainsString('posted fuel, maintenance', $equipment['integration']);
        $this->assertStringNotContainsString('NOT YET OPERATIONAL', $equipment['integration']);
        $this->assertEqualsWithDelta(350, $report['totals']['actual'], 0.001, 'BOQ total actual reconciles with the Project Cost Report');

        // Workspace: costs section and project panel show the posted figure to Finance; a viewer sees restricted values.
        $this->actingAs($this->finance)->get(route('admin.equipment.show', $this->asset))->assertOk()->assertSee('Posted to accounting')->assertSee('SAR 402.50')->assertSee($journal->journal_number);
        $this->actingAs($this->user(['Equipment' => ['view']], suffix: 'viewer2'))->get(route('admin.equipment.show', $this->asset))->assertOk()->assertSee('Restricted')->assertDontSee('SAR 402.50')->assertDontSee('Post to accounting');
        $html = $this->actingAs($this->finance)->getJson(route('admin.master.projects.workspace.panel', [$this->project, 'equipment']))->assertOk()->json('html');
        $this->assertStringContainsString('402.50', $html);
    }

    public function test_posting_fails_safely_when_the_category_has_no_account_and_can_be_retried(): void
    {
        $this->fuelCategory->update(['chart_of_account_id' => null]);
        $this->fuel()->assertSessionHasNoErrors();
        $log = EquipmentFuelLog::firstOrFail();
        $this->actingAs($this->finance)->post(route('admin.equipment.fuel.post', [$this->asset, $log->id]))->assertSessionHasErrors('posting');
        $log->refresh();
        $this->assertSame(EquipmentFuelLog::ACC_FAILED, $log->accounting_status);
        $this->assertStringContainsString('no active expense account', $log->posting_error);
        $this->assertSame(0, JournalEntry::where('source_module', EquipmentFuelLog::SOURCE_MODULE)->count());
        $this->fuelCategory->update(['chart_of_account_id' => $this->fuelAccount->id]);
        $this->actingAs($this->finance)->post(route('admin.equipment.fuel.post', [$this->asset, $log->id]))->assertSessionHasNoErrors();
        $this->assertSame(EquipmentFuelLog::ACC_POSTED, $log->fresh()->accounting_status);
        $this->assertNull($log->fresh()->posting_error);
    }

    // ---------------------------------------------------------------- maintenance

    public function test_maintenance_lifecycle_history_next_service_and_posting_once(): void
    {
        $this->maintenance(['take_out_of_service' => 1, 'next_service_date' => '2026-10-20', 'next_service_meter' => 1500])->assertSessionHasNoErrors();
        $record = EquipmentMaintenanceRecord::firstOrFail();
        $this->assertSame('open', $record->status);
        $this->assertSame('under_maintenance', $this->asset->fresh()->status);
        $this->assertMatchesRegularExpression('/^MNT-2026-\d{4}$/', $record->maintenance_number);
        $this->assertEqualsWithDelta(0, $this->projectCost(), 0.001);
        // Cost of an open record cannot be posted.
        $this->actingAs($this->finance)->post(route('admin.equipment.maintenance.post', [$this->asset, $record->id]))->assertSessionHasErrors('posting');

        $this->actingAs($this->admin)->post(route('admin.equipment.maintenance.complete', [$this->asset, $record->id]), ['cost' => 1200, 'meter_reading' => 1100])->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame('completed', $record->status);
        $this->assertEqualsWithDelta(1200, (float) $record->total_amount, 0.001);
        $this->assertEqualsWithDelta(1100, (float) $this->asset->fresh()->current_meter, 0.01);
        $this->assertSame('assigned', $this->asset->fresh()->status, 'back to assigned after completion');
        $this->actingAs($this->admin)->post(route('admin.equipment.maintenance.complete', [$this->asset, $record->id]))->assertSessionHasErrors('maintenance');
        $this->actingAs($this->admin)->get(route('admin.equipment.show', $this->asset))->assertOk()->assertSee('2026-10-20')->assertSee('1,500.0 h');

        $this->actingAs($this->finance)->post(route('admin.equipment.maintenance.post', [$this->asset, $record->id]))->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame(EquipmentMaintenanceRecord::ACC_POSTED, $record->accounting_status);
        $this->assertEqualsWithDelta(1200, (float) $record->journalEntry->lines->where('chart_of_account_id', $this->maintenanceAccount->id)->sum('debit'), 0.001);
        $this->assertEqualsWithDelta(1200, (float) $record->journalEntry->lines->where('chart_of_account_id', $this->bank->id)->sum('credit'), 0.001, 'Cr bank');
        $this->assertEqualsWithDelta(1200, $this->projectCost(), 0.001, 'posted maintenance counted once');
        $this->actingAs($this->finance)->post(route('admin.equipment.maintenance.post', [$this->asset, $record->id]))->assertSessionHasNoErrors();
        $this->assertSame(1, JournalEntry::where('source_module', EquipmentMaintenanceRecord::SOURCE_MODULE)->count());
        $this->actingAs($this->admin)->post(route('admin.equipment.maintenance.cancel', [$this->asset, $record->id]))->assertSessionHasErrors('maintenance');

        // A new service never overwrites the previous one; cancelled records stay unposted history.
        $this->maintenance(['description' => 'Oil change', 'cost' => 300])->assertSessionHasNoErrors();
        $second = EquipmentMaintenanceRecord::orderByDesc('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.equipment.maintenance.cancel', [$this->asset, $second->id]))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $second->fresh()->status);
        $this->assertSame(2, EquipmentMaintenanceRecord::count());
        $this->assertSame('completed', $record->fresh()->status);
        $this->assertEqualsWithDelta(1200, $this->projectCost(), 0.001);
        // Service-due indicator when the next service date is near.
        $record->update(['next_service_date' => today()->addDays(5)]);
        $this->actingAs($this->admin)->get(route('admin.equipment.show', $this->asset))->assertOk()->assertSee('Service due');
    }

    // ---------------------------------------------------------------- supplier credit

    public function test_supplier_credit_creates_one_bill_no_equipment_journal_and_ap_once(): void
    {
        $this->fuel(['payment_type' => 'Supplier Credit', 'supplier_id' => $this->supplier->id, 'payment_account_id' => null, 'reference_number' => 'INV-77'])->assertSessionHasNoErrors();
        $log = EquipmentFuelLog::firstOrFail();
        $this->actingAs($this->finance)->post(route('admin.equipment.fuel.post', [$this->asset, $log->id]))->assertSessionHasNoErrors();
        $log->refresh();
        $this->assertSame(EquipmentFuelLog::ACC_BILL_PENDING, $log->accounting_status);
        $this->assertNull($log->journal_entry_id, 'no equipment journal for a supplier-credit record');
        $bill = $log->supplierBill;
        $this->assertNotNull($bill);
        $this->assertSame('draft', $bill->status);
        $this->assertSame('runtime', $bill->approval_mode);
        $this->assertSame($log->fuel_number, $bill->bill_number);
        $this->assertSame($this->project->id, $bill->project_id);
        $this->assertEqualsWithDelta(402.5, (float) $bill->total_amount, 0.001);
        $this->assertSame($this->fuelAccount->id, $bill->lines()->value('chart_of_account_id'));
        $this->assertEqualsWithDelta(0, $this->projectCost(), 0.001, 'a draft bill is not project cost');
        $this->actingAs($this->finance)->post(route('admin.equipment.fuel.post', [$this->asset, $log->id]))->assertSessionHasNoErrors();
        $this->assertSame(1, SupplierBill::where('bill_number', $log->fuel_number)->count(), 'the bridge never creates a second bill');
        $this->actingAs($this->finance)->get(route('admin.equipment.show', $this->asset))->assertOk()->assertSee('Supplier bill created')->assertSee('Bill '.$bill->bill_number);

        // Finance posts the bill through AP: expense once, AP once, the record follows the bill.
        $entry = $this->postBill($bill);
        $log->refresh();
        $this->assertSame(EquipmentFuelLog::ACC_POSTED, $log->accounting_status, 'the record reads the posted bill');
        $this->assertEqualsWithDelta(350, (float) $entry->lines->where('chart_of_account_id', $this->fuelAccount->id)->sum('debit'), 0.001);
        $this->assertEqualsWithDelta(402.5, (float) $entry->lines->where('chart_of_account_id', ChartOfAccount::where('account_code', '2100')->value('id'))->sum('credit'), 0.001, 'Cr accounts payable once');
        $this->assertEqualsWithDelta(350, $this->projectCost(), 0.001, 'counted once through the bill');
        $this->assertSame(0, JournalEntry::where('source_module', EquipmentFuelLog::SOURCE_MODULE)->count());
        $this->actingAs($this->finance)->post(route('admin.equipment.fuel.post', [$this->asset, $log->id]))->assertSessionHasNoErrors();
        $this->assertSame(1, SupplierBill::where('bill_number', $log->fuel_number)->count());
        $this->assertEqualsWithDelta(350, $this->projectCost(), 0.001);
    }

    public function test_all_equipment_sources_reconcile_with_the_project_cost_report_and_the_boq(): void
    {
        $this->fuel()->assertSessionHasNoErrors();                                                                                  // 350 cash
        $this->fuel(['payment_type' => 'Supplier Credit', 'supplier_id' => $this->supplier->id, 'payment_account_id' => null, 'quantity' => 10, 'rate' => 4, 'vat_rate' => 0])->assertSessionHasNoErrors();   // 40 credit
        $this->maintenance()->assertSessionHasNoErrors();                                                                           // 1000 bank
        $this->fuel(['quantity' => 5, 'rate' => 2, 'vat_rate' => 0])->assertSessionHasNoErrors();                                   // 10, stays unposted
        [$cashFuel, $creditFuel, , $unposted] = [EquipmentFuelLog::find(1), EquipmentFuelLog::find(2), null, EquipmentFuelLog::find(3)];
        $record = EquipmentMaintenanceRecord::firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.equipment.maintenance.complete', [$this->asset, $record->id]))->assertSessionHasNoErrors();
        foreach ([['fuel', $cashFuel->id], ['fuel', $creditFuel->id], ['maintenance', $record->id]] as [$kind, $id]) {
            $this->actingAs($this->finance)->post(route('admin.equipment.'.$kind.'.post', [$this->asset, $id]))->assertSessionHasNoErrors();
        }
        $this->postBill($creditFuel->fresh()->supplierBill);

        $this->assertEqualsWithDelta(350 + 40 + 1000, $this->projectCost(), 0.001, 'cash fuel + billed fuel + bank maintenance, each once; the unposted log excluded');
        $this->assertSame(EquipmentFuelLog::ACC_NOT_POSTED, $unposted->fresh()->accounting_status);
        $budgetService = app(ProjectBudgetService::class);
        $budget = $budgetService->createDraft($this->project, ['title' => 'Baseline'], $this->admin->id);
        $budgetService->addLine($budget, ['cost_code' => 'EQP-FUEL', 'category' => 'equipment', 'description' => 'Fuel', 'amount' => 1000, 'chart_of_account_id' => $this->fuelAccount->id], $this->admin->id);
        $budgetService->addLine($budget, ['cost_code' => 'EQP-MNT', 'category' => 'equipment', 'description' => 'Maintenance', 'amount' => 800, 'chart_of_account_id' => $this->maintenanceAccount->id], $this->admin->id);
        $budget = $budgetService->finalize($budget->id, $this->admin->id);
        $report = app(ProjectBudgetReport::class)->build($this->project->fresh(), $budget);
        $rows = collect($report['rows'])->keyBy('cost_code');
        $this->assertEqualsWithDelta(390, $rows['EQP-FUEL']['actual'], 0.001);
        $this->assertEqualsWithDelta(1000, $rows['EQP-MNT']['actual'], 0.001);
        $this->assertSame('Over budget', $rows['EQP-MNT']['status']);
        $this->assertEqualsWithDelta(1390, $report['totals']['actual'], 0.001);
        $this->assertEqualsWithDelta($this->projectCost(), $report['totals']['actual'], 0.001);
        $html = $this->actingAs($this->finance)->getJson(route('admin.master.projects.workspace.panel', [$this->project, 'equipment']))->assertOk()->json('html');
        $this->assertStringContainsString('442.50', $html, 'posted fuel 402.50 + 40.00 on the project panel');
        $this->assertStringContainsString('1,000.00', $html);
    }
}
