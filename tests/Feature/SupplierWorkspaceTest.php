<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\PostingService;
use App\Support\SaveAction;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Supplier connected workspace (Connected Workspace Standard, pilot).
 * List → View (read-only) → Edit workspace; every panel keeps its own
 * permission and access scope; the supplier in the URL is authoritative;
 * approvals and payments never happen from the supplier profile.
 */
class SupplierWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected Supplier $supplier;

    protected Project $mine;

    protected Project $theirs;

    protected Warehouse $myStore;

    protected Warehouse $theirStore;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->supplier = Supplier::orderBy('id')->firstOrFail();
        $this->mine = Project::create(['name' => 'Mine', 'code' => 'PRJ-SW-A', 'status' => 'active']);
        $this->theirs = Project::create(['name' => 'Theirs', 'code' => 'PRJ-SW-B', 'status' => 'active']);
        $this->myStore = Warehouse::create(['name' => 'My store', 'code' => 'WH-SW-A', 'project_id' => $this->mine->id, 'status' => 'active']);
        $this->theirStore = Warehouse::create(['name' => 'Their store', 'code' => 'WH-SW-B', 'project_id' => $this->theirs->id, 'status' => 'active']);
        $this->item = Item::create([
            'item_code' => 'ITM-SW', 'name' => 'Workspace item', 'unit_id' => Unit::first()?->id, 'valuation_method' => 'average',
            'inventory_account_id' => ChartOfAccount::where('account_code', PostingService::INVENTORY_ASSET)->value('id'),
            'expense_account_id' => ChartOfAccount::where('account_code', PostingService::MATERIAL_EXPENSE)->value('id'), 'status' => 'active',
        ]);
    }

    // ---------------------------------------------------------------- helpers

    protected function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /** @param array<string, array<int, string>> $grants module => actions */
    protected function user(array $grants, ?Project $project = null, string $suffix = 'a'): User
    {
        $role = Role::create(['name' => 'Workspace role '.$suffix, 'code' => 'SW_ROLE_'.strtoupper($suffix), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'Workspace '.$suffix, 'email' => 'ws-'.$suffix.'@example.test', 'username' => 'ws.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    protected function order(Project $project, Warehouse $warehouse, float $qty = 10, float $price = 100): PurchaseOrder
    {
        $money = PurchaseOrderLine::calculate($qty, $price, 0, 15);
        $order = PurchaseOrder::create([
            'po_number' => PurchaseOrder::nextNumber(2026), 'supplier_id' => $this->supplier->id, 'po_date' => now()->toDateString(),
            'project_id' => $project->id, 'warehouse_id' => $warehouse->id, 'taxable_amount' => $money['taxable_amount'], 'vat_rate' => 15,
            'vat_amount' => $money['vat_amount'], 'total_amount' => $money['total_amount'], 'status' => 'approved',
            'approved_by' => $this->admin()->id, 'approved_at' => now(),
        ]);
        $order->lines()->create(['item_id' => $this->item->id, 'quantity' => $qty, 'unit_price' => $price, 'taxable_amount' => $money['taxable_amount'], 'vat_rate' => 15, 'vat_amount' => $money['vat_amount'], 'total_amount' => $money['total_amount']]);

        return $order->fresh('lines');
    }

    protected function postedReceipt(PurchaseOrder $order, Warehouse $warehouse, float $qty = 10): GoodsReceipt
    {
        $dn = 'DN-SW-'.uniqid();
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.store'), [
            'purchase_order_id' => $order->id, 'supplier_id' => $this->supplier->id, 'warehouse_id' => $warehouse->id,
            'received_date' => now()->toDateString(), 'delivery_note_number' => $dn, 'vat_rate' => 15,
            'lines' => [['item_id' => $this->item->id, 'purchase_order_line_id' => $order->lines->first()->id, 'received_quantity' => $qty, 'accepted_quantity' => $qty, 'unit_cost' => 100]],
        ])->assertSessionHasNoErrors();
        $grn = GoodsReceipt::where('delivery_note_number', $dn)->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.post-stock', $grn))->assertSessionHasNoErrors();

        return $grn->fresh('lines');
    }

    protected function approvedBill(Project $project, string $number, float $net = 1000): SupplierBill
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), [
            'supplier_id' => $this->supplier->id, 'bill_number' => $number, 'bill_date' => now()->toDateString(), 'project_id' => $project->id,
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => $net]],
        ])->assertSessionHasNoErrors();
        $bill = SupplierBill::where('bill_number', $number)->firstOrFail();
        $bill->update(['approval_mode' => 'legacy']); // Explicit pre-runtime fixture; runtime coverage is separate.
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();

        return $bill->fresh();
    }

    protected function panel(User $user, string $panel, ?Supplier $supplier = null): TestResponse
    {
        return $this->actingAs($user)->getJson(route('admin.master.suppliers.workspace.panel', [$supplier ?? $this->supplier, $panel]));
    }

    protected function profile(array $extra = []): array
    {
        return $extra + ['name' => $this->supplier->name, 'code' => $this->supplier->code, 'status' => 'active'];
    }

    // ------------------------------------------------------------------ tests

    public function test_list_offers_view_edit_manage_and_deactivate_according_to_permission(): void
    {
        $page = $this->actingAs($this->admin())->get(route('admin.master.suppliers.index', ['search' => $this->supplier->code]))->assertOk();
        $page->assertSee('Edit / Manage')->assertSee('>Deactivate</button>', false)->assertSee('data-deactivate="1"', false)->assertDontSee('>Open<', false);

        // (The layout's confirmation dialog always carries the word "Deactivate"; the row button is what matters.)
        $viewer = $this->user(['Suppliers' => ['view']], null, 'viewer');
        $page = $this->actingAs($viewer)->get(route('admin.master.suppliers.index', ['search' => $this->supplier->code]))->assertOk();
        $page->assertSee('>View<', false)->assertDontSee('Edit / Manage')->assertDontSee('>Deactivate</button>', false);
        $this->actingAs($viewer)->get(route('admin.master.suppliers.edit', $this->supplier))->assertForbidden();
    }

    public function test_view_is_read_only_and_performs_no_write(): void
    {
        $before = $this->supplier->fresh()->updated_at;
        $page = $this->actingAs($this->admin())->get(route('admin.master.suppliers.show', $this->supplier))->assertOk();

        $page->assertSee($this->supplier->code)->assertSee($this->supplier->name)->assertSee('Read-only supplier view');
        $page->assertDontSee('data-related-save', false)->assertDontSee('name="'.SaveAction::FIELD.'"', false)->assertDontSee('data-workspace-related-host', false);
        $page->assertSee('id="bills"', false)->assertSee('id="goods-receipts"', false)->assertSee('View all');
        $this->assertEquals($before, $this->supplier->fresh()->updated_at);

        // A viewer without finance rights sees no money panels and no money in the header.
        $viewer = $this->user(['Suppliers' => ['view']], null, 'viewer');
        $page = $this->actingAs($viewer)->get(route('admin.master.suppliers.show', $this->supplier))->assertOk();
        $page->assertDontSee('id="bills"', false)->assertDontSee('Outstanding payable')->assertDontSee('Edit / Manage');
    }

    public function test_edit_workspace_shows_identity_navigation_and_only_permitted_panels(): void
    {
        $page = $this->actingAs($this->admin())->get(route('admin.master.suppliers.edit', $this->supplier))->assertOk();
        $page->assertSee('Supplier Workspace: '.$this->supplier->name)->assertSee($this->supplier->code)->assertSee('data-workspace-nav', false)->assertSee('data-workspace-related-host', false);
        foreach (['projects', 'purchase-orders', 'goods-receipts', 'bills', 'payments', 'accounting', 'activity'] as $key) {
            $page->assertSee('data-workspace-related="'.$key.'"', false);
        }
        $page->assertSee('name="'.SaveAction::FIELD.'" value="stay"', false)->assertSee('name="'.SaveAction::FIELD.'" value="new"', false);
        $page->assertDontSee('name="project_ids_submitted"', false);   // projects live in their panel inside the workspace

        $clerk = $this->user(['Suppliers' => ['view', 'edit'], 'Purchase Orders' => ['view']], null, 'clerk');
        $page = $this->actingAs($clerk)->get(route('admin.master.suppliers.edit', $this->supplier))->assertOk();
        $page->assertSee('data-workspace-related="purchase-orders"', false)->assertSee('data-workspace-related="projects"', false);
        foreach (['goods-receipts', 'bills', 'payments', 'accounting', 'activity'] as $key) {
            $page->assertDontSee('data-workspace-related="'.$key.'"', false);
        }
        $page->assertDontSee('Outstanding payable');
    }

    public function test_profile_save_stays_in_the_workspace_close_exits_and_new_opens_a_fresh_form(): void
    {
        $this->supplier->projects()->sync([$this->mine->id]);

        $this->actingAs($this->admin())->put(route('admin.master.suppliers.update', $this->supplier), $this->profile(['city' => 'Jeddah', SaveAction::FIELD => SaveAction::STAY]))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.master.suppliers.edit', $this->supplier));
        $this->assertSame('Jeddah', $this->supplier->fresh()->city);
        $this->assertSame([$this->mine->id], $this->supplier->projects()->pluck('projects.id')->all(), 'a workspace save without the project list keeps the links');

        $this->actingAs($this->admin())->put(route('admin.master.suppliers.update', $this->supplier), $this->profile([SaveAction::FIELD => SaveAction::CLOSE]))
            ->assertRedirect(route('admin.master.suppliers.index'));
        $this->actingAs($this->admin())->put(route('admin.master.suppliers.update', $this->supplier), $this->profile([SaveAction::FIELD => SaveAction::CLOSE, SaveAction::RETURN_FIELD => '/admin/accounting/dashboard']))
            ->assertRedirect('/admin/accounting/dashboard');
        $this->actingAs($this->admin())->put(route('admin.master.suppliers.update', $this->supplier), $this->profile([SaveAction::FIELD => SaveAction::NEW]))
            ->assertRedirect(route('admin.master.suppliers.create'));

        // The create form still submits the project list explicitly, and Save opens the new supplier's workspace.
        $this->actingAs($this->admin())->post(route('admin.master.suppliers.store'), ['name' => 'New Vendor', 'status' => 'active', 'project_ids_submitted' => 1, 'project_ids' => [$this->theirs->id], SaveAction::FIELD => SaveAction::STAY])
            ->assertSessionHasNoErrors();
        $created = Supplier::where('name', 'New Vendor')->firstOrFail();
        $this->assertSame([$this->theirs->id], $created->projects()->pluck('projects.id')->all());
    }

    public function test_projects_panel_links_and_unlinks_only_within_permission_and_scope(): void
    {
        $this->panel($this->admin(), 'projects')->assertOk()->assertJsonPath('html', fn ($html) => str_contains($html, 'Link a project'));

        // Link within scope, then it shows in the panel and the header count.
        $this->actingAs($this->admin())->postJson(route('admin.master.suppliers.workspace.save', [$this->supplier, 'projects']), ['project_id' => $this->mine->id])
            ->assertOk()->assertJsonPath('panel_url', route('admin.master.suppliers.workspace.panel', [$this->supplier, 'projects']));
        $this->assertTrue($this->supplier->projects()->whereKey($this->mine->id)->exists());
        $this->panel($this->admin(), 'projects')->assertOk()->assertJsonPath('html', fn ($html) => str_contains($html, 'PRJ-SW-A'));

        // A project-scoped editor cannot link a project outside their scope, and cannot see it either.
        $scoped = $this->user(['Suppliers' => ['view', 'edit'], 'Projects' => ['view']], $this->mine, 'scoped');
        // The request-scope middleware refuses the out-of-scope project id before the controller runs.
        $this->actingAs($scoped)->postJson(route('admin.master.suppliers.workspace.save', [$this->supplier, 'projects']), ['project_id' => $this->theirs->id])
            ->assertForbidden();
        $this->assertFalse($this->supplier->projects()->whereKey($this->theirs->id)->exists());

        // Unlink: only a project actually linked to THIS supplier.
        $this->actingAs($this->admin())->postJson(route('admin.master.suppliers.workspace.action', [$this->supplier, 'projects', $this->theirs->id, 'remove']))->assertNotFound();
        $this->actingAs($this->admin())->postJson(route('admin.master.suppliers.workspace.action', [$this->supplier, 'projects', $this->mine->id, 'remove']))->assertOk();
        $this->assertFalse($this->supplier->projects()->whereKey($this->mine->id)->exists());

        // Without Suppliers edit the panel is read-only and writes are refused.
        $viewer = $this->user(['Suppliers' => ['view'], 'Projects' => ['view']], null, 'viewer');
        $this->panel($viewer, 'projects')->assertOk()->assertJsonPath('html', fn ($html) => ! str_contains($html, 'Link a project'));
        $this->actingAs($viewer)->postJson(route('admin.master.suppliers.workspace.save', [$this->supplier, 'projects']), ['project_id' => $this->mine->id])->assertForbidden();
    }

    public function test_order_receipt_bill_and_payment_panels_show_only_scoped_rows_and_totals_exclude_hidden_rows(): void
    {
        $orderA = $this->order($this->mine, $this->myStore);
        $orderB = $this->order($this->theirs, $this->theirStore);
        $grnA = $this->postedReceipt($orderA, $this->myStore);
        $grnB = $this->postedReceipt($orderB, $this->theirStore);
        $billA = $this->approvedBill($this->mine, 'BILL-SW-A', 1000);
        $billB = $this->approvedBill($this->theirs, 'BILL-SW-B', 5000);
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.payment.store', $billA), [
            'payment_date' => now()->toDateString(), 'amount' => 150, 'payment_method' => 'Cash', 'purpose' => 'Bill payment',
            'payment_account_id' => ChartOfAccount::where('account_code', PostingService::CASH)->value('id'), 'idempotency_key' => 'sw-pay-a',
        ])->assertSessionHasNoErrors();

        $scoped = $this->user(['Suppliers' => ['view', 'edit'], 'Purchase Orders' => ['view'], 'Goods Receipts' => ['view'], 'Accounts Payable' => ['view']], $this->mine, 'scoped');

        $orders = $this->panel($scoped, 'purchase-orders')->assertOk()->json('html');
        $this->assertStringContainsString($orderA->po_number, $orders);
        $this->assertStringNotContainsString($orderB->po_number, $orders);

        $receipts = $this->panel($scoped, 'goods-receipts')->assertOk()->json('html');
        $this->assertStringContainsString($grnA->grn_number, $receipts);
        $this->assertStringNotContainsString($grnB->grn_number, $receipts);

        $bills = $this->panel($scoped, 'bills')->assertOk()->json('html');
        $this->assertStringContainsString('BILL-SW-A', $bills);
        $this->assertStringNotContainsString('BILL-SW-B', $bills);

        // Header and payments panel totals come from the visible bill only: 1,150 − 150 paid.
        $edit = $this->actingAs($scoped)->get(route('admin.master.suppliers.edit', $this->supplier))->assertOk();
        $edit->assertSee('SAR 1,000.00')->assertDontSee('SAR 6,750.00')->assertDontSee('5,750.00');
        $payments = $this->panel($scoped, 'payments')->assertOk()->json('html');
        $this->assertStringContainsString('SAR 150.00', $payments);
        $this->assertStringContainsString('SAR 1,000.00', $payments);

        // The company-level administrator sees both, and the total includes both bills.
        $this->actingAs($this->admin())->get(route('admin.master.suppliers.edit', $this->supplier))->assertOk()->assertSee('SAR 6,750.00');
        $this->assertStringContainsString('BILL-SW-B', $this->panel($this->admin(), 'bills')->json('html'));
    }

    public function test_goods_receipt_panel_reports_the_f04_invoicing_state_and_offers_create_bill_only_with_ap_create(): void
    {
        $grn = $this->postedReceipt($this->order($this->mine, $this->myStore), $this->myStore, 6);

        $html = $this->panel($this->admin(), 'goods-receipts')->assertOk()->json('html');
        $this->assertStringContainsString('Uninvoiced', $html);
        $this->assertStringContainsString('Create Supplier Bill', $html);
        $this->assertStringContainsString('goods_receipt='.$grn->id, $html);
        $this->assertStringContainsString('return_to=', $html);

        $storeKeeper = $this->user(['Suppliers' => ['view'], 'Goods Receipts' => ['view']], null, 'keeper');
        $html = $this->panel($storeKeeper, 'goods-receipts')->assertOk()->json('html');
        $this->assertStringContainsString($grn->grn_number, $html);
        $this->assertStringNotContainsString('Create Supplier Bill', $html);

        // Invoice 4 of the 6 through the existing F04 flow: the panel shows the remainder, then "Invoiced".
        $line = $grn->lines->first();
        foreach ([[4, 'BILL-SW-M1'], [2, 'BILL-SW-M2']] as [$qty, $number]) {
            $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), [
                'supplier_id' => $this->supplier->id, 'bill_number' => $number, 'bill_date' => now()->toDateString(), 'vat_rate' => 15,
                'lines' => [['description' => 'Goods', 'goods_receipt_line_id' => $line->id, 'matched_quantity' => $qty, 'quantity' => $qty, 'unit_price' => 100]],
            ])->assertSessionHasNoErrors();
            $bill = SupplierBill::where('bill_number', $number)->firstOrFail();
            $bill->update(['approval_mode' => 'legacy']); // Explicit pre-runtime fixture; runtime coverage is separate.
            $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
            $html = $this->panel($this->admin(), 'goods-receipts')->json('html');
            $this->assertStringContainsString($qty === 4 ? '2 uninvoiced' : '>Invoiced<', $html);
        }
        // This receipt is fully invoiced: no Create Supplier Bill link for it (other seeded receipts may still offer one).
        $this->assertStringNotContainsString('goods_receipt='.$grn->id, $html);
        $this->assertStringContainsString('1 GRN line', $this->panel($this->admin(), 'bills')->json('html'));
    }

    public function test_accounting_and_activity_panels_require_their_own_permissions(): void
    {
        $bill = $this->approvedBill($this->mine, 'BILL-SW-ACC');

        $html = $this->panel($this->admin(), 'accounting')->assertOk()->json('html');
        $this->assertStringContainsString($bill->journalEntry->journal_number, $html);
        $this->assertStringContainsString('Linked payable account', $html);

        $noFinance = $this->user(['Suppliers' => ['view', 'edit'], 'Accounts Payable' => ['view']], null, 'nofin');
        $this->panel($noFinance, 'accounting')->assertForbidden();
        $this->actingAs($noFinance)->get(route('admin.master.suppliers.edit', $this->supplier))->assertOk()->assertDontSee('data-workspace-related="accounting"', false);

        $this->actingAs($this->admin())->put(route('admin.master.suppliers.update', $this->supplier), $this->profile(['city' => 'Dammam']))->assertSessionHasNoErrors();
        $html = $this->panel($this->admin(), 'activity')->assertOk()->json('html');
        $this->assertStringContainsString('Updated supplier', $html);
        $this->assertStringContainsString('Approved supplier bill', $html);
        $this->panel($noFinance, 'activity')->assertForbidden();
    }

    public function test_direct_panel_urls_enforce_permissions_and_cross_supplier_ids_are_rejected(): void
    {
        $other = Supplier::where('id', '!=', $this->supplier->id)->orderBy('id')->firstOrFail();
        $other->projects()->sync([$this->theirs->id]);

        // Unknown panel, and panels the user may not view.
        $this->panel($this->admin(), 'nope')->assertNotFound();
        $viewer = $this->user(['Suppliers' => ['view']], null, 'viewer');
        foreach (['purchase-orders', 'goods-receipts', 'bills', 'payments', 'accounting', 'activity'] as $key) {
            $this->panel($viewer, $key)->assertForbidden();
        }
        $this->actingAs($viewer)->postJson(route('admin.master.suppliers.workspace.save', [$this->supplier, 'projects']), ['project_id' => $this->mine->id])->assertForbidden();

        // A project linked to ANOTHER supplier cannot be unlinked through this supplier's URL.
        $this->actingAs($this->admin())->postJson(route('admin.master.suppliers.workspace.action', [$this->supplier, 'projects', $this->theirs->id, 'remove']))->assertNotFound();
        $this->assertTrue($other->projects()->whereKey($this->theirs->id)->exists());

        // A forged supplier_id in the body is ignored in favour of the URL.
        $this->actingAs($this->admin())->postJson(route('admin.master.suppliers.workspace.save', [$this->supplier, 'projects']), ['project_id' => $this->mine->id, 'supplier_id' => $other->id])->assertForbidden();
        $this->assertFalse($other->projects()->whereKey($this->mine->id)->exists());
        $this->assertFalse($this->supplier->projects()->whereKey($this->mine->id)->exists());

        // Only the projects panel accepts writes; documents are never written from the workspace.
        $this->actingAs($this->admin())->postJson(route('admin.master.suppliers.workspace.save', [$this->supplier, 'bills']), ['bill_number' => 'X'])->assertNotFound();
        $this->actingAs($this->admin())->postJson(route('admin.master.suppliers.workspace.action', [$this->supplier, 'bills', 1, 'approve']))->assertNotFound();
    }

    public function test_deactivate_keeps_the_supplier_and_its_documents(): void
    {
        $bill = $this->approvedBill($this->mine, 'BILL-SW-KEEP');

        $this->actingAs($this->admin())->delete(route('admin.master.suppliers.destroy', $this->supplier))->assertRedirect(route('admin.master.suppliers.index'));

        $this->assertSame('inactive', $this->supplier->fresh()->status);
        $this->assertSame('unpaid', $bill->fresh()->status);
        $this->assertNotNull($bill->fresh()->journal_entry_id);
    }
}
