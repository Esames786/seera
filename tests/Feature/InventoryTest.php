<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\StockLedgerEntry;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
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

    private function stockFor(int $itemId, int $warehouseId): float
    {
        return (float) (WarehouseStock::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity') ?? 0);
    }

    /** A warehouse row that already holds enough stock to issue or transfer from. */
    private function stockedRow(): WarehouseStock
    {
        return WarehouseStock::where('quantity', '>', 50)->orderByDesc('quantity')->firstOrFail();
    }

    public function test_inventory_screens_return_ok(): void
    {
        $item = Item::firstOrFail();
        $category = ItemCategory::firstOrFail();
        $unit = Unit::firstOrFail();
        $pr = PurchaseRequest::firstOrFail();
        $draftPr = PurchaseRequest::whereIn('status', ['draft', 'pending'])->firstOrFail();
        $po = PurchaseOrder::firstOrFail();
        $draftPo = PurchaseOrder::where('status', 'draft')->firstOrFail();
        $grn = GoodsReceipt::firstOrFail();
        $draftGrn = GoodsReceipt::where('status', 'draft')->firstOrFail();
        $issue = StockIssue::firstOrFail();
        $draftIssue = StockIssue::where('status', 'draft')->firstOrFail();
        $transfer = StockTransfer::firstOrFail();
        $draftTransfer = StockTransfer::where('status', 'draft')->firstOrFail();
        $adjustment = StockAdjustment::firstOrFail();
        $draftAdjustment = StockAdjustment::whereIn('status', ['draft', 'approved'])->firstOrFail();

        $urls = [
            '/admin/inventory/dashboard',
            '/admin/inventory/items', '/admin/inventory/items/create',
            "/admin/inventory/items/{$item->id}", "/admin/inventory/items/{$item->id}/edit",
            '/admin/inventory/categories', '/admin/inventory/categories/create',
            "/admin/inventory/categories/{$category->id}/edit",
            '/admin/inventory/units', '/admin/inventory/units/create', "/admin/inventory/units/{$unit->id}/edit",
            '/admin/inventory/stock', '/admin/inventory/stock-ledger',
            '/admin/inventory/purchase-requests', '/admin/inventory/purchase-requests/create',
            "/admin/inventory/purchase-requests/{$pr->id}", "/admin/inventory/purchase-requests/{$draftPr->id}/edit",
            '/admin/inventory/purchase-orders', '/admin/inventory/purchase-orders/create',
            "/admin/inventory/purchase-orders/{$po->id}", "/admin/inventory/purchase-orders/{$draftPo->id}/edit",
            '/admin/inventory/goods-receipts', '/admin/inventory/goods-receipts/create',
            "/admin/inventory/goods-receipts/{$grn->id}", "/admin/inventory/goods-receipts/{$draftGrn->id}/edit",
            '/admin/inventory/stock-issues', '/admin/inventory/stock-issues/create',
            "/admin/inventory/stock-issues/{$issue->id}", "/admin/inventory/stock-issues/{$draftIssue->id}/edit",
            '/admin/inventory/stock-transfers', '/admin/inventory/stock-transfers/create',
            "/admin/inventory/stock-transfers/{$transfer->id}", "/admin/inventory/stock-transfers/{$draftTransfer->id}/edit",
            '/admin/inventory/stock-adjustments', '/admin/inventory/stock-adjustments/create',
            "/admin/inventory/stock-adjustments/{$adjustment->id}", "/admin/inventory/stock-adjustments/{$draftAdjustment->id}/edit",
            '/admin/inventory/reports',
            '/admin/inventory/reports/stock-valuation',
            '/admin/inventory/reports/low-stock',
            '/admin/inventory/reports/project-consumption',
            '/admin/inventory/reports/movement',
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->admin())->get($url)->assertOk();
        }
    }

    public function test_inventory_routes_require_authentication(): void
    {
        $this->get('/admin/inventory/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/inventory/items')->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_seeded_inventory_data(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.inventory.dashboard'))
            ->assertOk()
            ->assertSee('Stock Value')
            ->assertSee('Low Stock')
            ->assertSee('Pending PRs');
    }

    public function test_item_can_be_created_and_updated(): void
    {
        $unit = Unit::firstOrFail();
        $category = ItemCategory::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.items.store'), [
                'item_code' => 'ITM-9001',
                'name' => 'Scaffolding Plank',
                'item_category_id' => $category->id,
                'unit_id' => $unit->id,
                'valuation_method' => 'average',
                'reorder_level' => 50,
                'minimum_stock' => 25,
                'maximum_stock' => 200,
                'vat_applicable' => 1,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.inventory.items.index'));

        $item = Item::where('item_code', 'ITM-9001')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.inventory.items.update', $item), [
                'item_code' => 'ITM-9001',
                'name' => 'Scaffolding Plank Heavy Duty',
                'unit_id' => $unit->id,
                'valuation_method' => 'average',
                'reorder_level' => 80,
                'minimum_stock' => 40,
                'maximum_stock' => 320,
                'status' => 'active',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('Scaffolding Plank Heavy Duty', $item->name);
        $this->assertSame('80.000', (string) $item->reorder_level);
    }

    public function test_category_and_unit_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.inventory.categories.store'), [
                'code' => 'CAT-TEST',
                'name' => 'Test Category',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.inventory.categories.index'));

        $this->assertDatabaseHas('item_categories', ['code' => 'CAT-TEST']);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.units.store'), [
                'code' => 'DRUM',
                'name' => 'Drum',
                'allows_decimal' => 0,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.inventory.units.index'));

        $this->assertDatabaseHas('units', ['code' => 'DRUM', 'allows_decimal' => 0]);
    }

    public function test_stock_on_hand_screen_shows_seeded_stock(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.inventory.stock.index'))
            ->assertOk()
            ->assertSee('Total Stock Value');

        $this->assertGreaterThan(0, WarehouseStock::sum('quantity'));
    }

    public function test_purchase_request_can_be_created_and_approved(): void
    {
        $item = Item::firstOrFail();
        $warehouse = Warehouse::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.purchase-requests.store'), [
                'request_date' => now()->toDateString(),
                'required_date' => now()->addDays(10)->toDateString(),
                'warehouse_id' => $warehouse->id,
                'priority' => 'high',
                'reason' => 'Slab casting next week',
                'status' => 'pending',
                'lines' => [
                    ['item_id' => $item->id, 'quantity' => 100, 'estimated_unit_cost' => 20],
                ],
            ])
            ->assertRedirect();

        $pr = PurchaseRequest::where('reason', 'Slab casting next week')->firstOrFail();
        $this->assertSame('2000.00', (string) $pr->estimated_total);
        $this->assertSame('pending', $pr->status);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.purchase-requests.approve', $pr))
            ->assertRedirect();

        $pr->refresh();
        $this->assertSame('approved', $pr->status);
        $this->assertSame($this->admin()->id, $pr->approved_by);
    }

    public function test_purchase_request_can_be_rejected_with_a_reason(): void
    {
        $pr = PurchaseRequest::whereIn('status', ['draft', 'pending'])->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.purchase-requests.reject', $pr), ['rejection_reason' => 'Budget on hold'])
            ->assertRedirect();

        $pr->refresh();
        $this->assertSame('rejected', $pr->status);
        $this->assertSame('Budget on hold', $pr->rejection_reason);
    }

    public function test_purchase_order_can_be_created_and_approved(): void
    {
        $supplier = Supplier::firstOrFail();
        $warehouse = Warehouse::firstOrFail();
        $item = Item::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'po_date' => now()->toDateString(),
                'warehouse_id' => $warehouse->id,
                'vat_rate' => 15,
                'lines' => [
                    ['item_id' => $item->id, 'quantity' => 100, 'unit_price' => 20],
                ],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::latest('id')->firstOrFail();
        $this->assertSame('2000.00', (string) $order->taxable_amount);
        $this->assertSame('300.00', (string) $order->vat_amount);
        $this->assertSame('2300.00', (string) $order->total_amount);
        $this->assertSame('draft', $order->status);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.purchase-orders.approve', $order))
            ->assertRedirect();

        $this->assertSame('approved', $order->refresh()->status);
    }

    public function test_goods_receipt_can_be_created_and_posting_increases_stock(): void
    {
        $supplier = Supplier::firstOrFail();
        $warehouse = Warehouse::firstOrFail();
        $item = Item::firstOrFail();

        $before = $this->stockFor($item->id, $warehouse->id);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.goods-receipts.store'), [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'received_date' => now()->toDateString(),
                'delivery_note_number' => 'DN-TEST-001',
                'vat_rate' => 15,
                'lines' => [
                    ['item_id' => $item->id, 'ordered_quantity' => 100, 'received_quantity' => 100, 'accepted_quantity' => 90, 'unit_cost' => 20],
                ],
            ])
            ->assertRedirect();

        $grn = GoodsReceipt::where('delivery_note_number', 'DN-TEST-001')->firstOrFail();
        $this->assertSame('draft', $grn->status);
        $this->assertSame('1800.00', (string) $grn->taxable_amount);
        $this->assertSame('270.00', (string) $grn->vat_amount);
        $this->assertSame('10.000', (string) $grn->lines->first()->rejected_quantity);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.goods-receipts.post-stock', $grn))
            ->assertRedirect(route('admin.inventory.goods-receipts.show', $grn));

        $grn->refresh();
        $this->assertSame('posted', $grn->status);
        $this->assertTrue($grn->stock_updated);

        // Only the accepted quantity enters stock.
        $this->assertEqualsWithDelta($before + 90, $this->stockFor($item->id, $warehouse->id), 0.001);

        $this->assertDatabaseHas('stock_ledger_entries', [
            'reference_number' => $grn->grn_number,
            'movement_type' => 'grn',
            'in_quantity' => 90,
        ]);
    }

    public function test_posted_goods_receipt_is_read_only(): void
    {
        $grn = GoodsReceipt::where('status', 'posted')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.inventory.goods-receipts.edit', $grn))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.goods-receipts.post-stock', $grn))
            ->assertSessionHasErrors('grn');
    }

    public function test_goods_receipt_posting_creates_accounting_journal(): void
    {
        $grn = GoodsReceipt::where('status', 'posted')->whereNotNull('journal_entry_id')->firstOrFail();
        $entry = $grn->journalEntry;

        $this->assertNotNull($entry);
        $this->assertSame('Inventory', $entry->source_module);
        $this->assertTrue($entry->isBalanced());
        $this->assertEqualsWithDelta((float) $grn->total_amount, (float) $entry->total_credit, 0.02);
    }

    public function test_stock_issue_decreases_warehouse_stock(): void
    {
        $row = $this->stockedRow();
        $before = (float) $row->quantity;

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.stock-issues.store'), [
                'warehouse_id' => $row->warehouse_id,
                'issue_date' => now()->toDateString(),
                'purpose' => 'Issue test',
                'lines' => [
                    ['item_id' => $row->item_id, 'quantity' => 10],
                ],
            ])
            ->assertRedirect();

        $issue = StockIssue::where('purpose', 'Issue test')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.stock-issues.post', $issue))
            ->assertRedirect(route('admin.inventory.stock-issues.show', $issue));

        $issue->refresh();
        $this->assertSame('posted', $issue->status);
        $this->assertEqualsWithDelta($before - 10, $this->stockFor($row->item_id, $row->warehouse_id), 0.001);
        $this->assertGreaterThan(0, (float) $issue->total_cost);

        $this->assertDatabaseHas('stock_ledger_entries', [
            'reference_number' => $issue->issue_number,
            'movement_type' => 'issue',
            'out_quantity' => 10,
        ]);

        // Debit project material expense, credit inventory asset.
        $this->assertNotNull($issue->journalEntry);
        $this->assertTrue($issue->journalEntry->isBalanced());
    }

    public function test_negative_stock_is_rejected(): void
    {
        $row = $this->stockedRow();
        $before = (float) $row->quantity;

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.stock-issues.store'), [
                'warehouse_id' => $row->warehouse_id,
                'issue_date' => now()->toDateString(),
                'purpose' => 'Oversized issue',
                'lines' => [
                    ['item_id' => $row->item_id, 'quantity' => $before + 500],
                ],
            ])
            ->assertRedirect();

        $issue = StockIssue::where('purpose', 'Oversized issue')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.stock-issues.post', $issue))
            ->assertSessionHasErrors('issue');

        // Nothing moved and the document stays editable.
        $this->assertSame('draft', $issue->refresh()->status);
        $this->assertEqualsWithDelta($before, $this->stockFor($row->item_id, $row->warehouse_id), 0.001);
    }

    public function test_stock_transfer_dispatch_and_receive_moves_stock(): void
    {
        $row = $this->stockedRow();
        $destination = Warehouse::where('id', '!=', $row->warehouse_id)->firstOrFail();

        $sourceBefore = (float) $row->quantity;
        $destinationBefore = $this->stockFor($row->item_id, $destination->id);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.stock-transfers.store'), [
                'transfer_date' => now()->toDateString(),
                'from_warehouse_id' => $row->warehouse_id,
                'to_warehouse_id' => $destination->id,
                'notes' => 'Transfer test',
                'lines' => [
                    ['item_id' => $row->item_id, 'quantity' => 15],
                ],
            ])
            ->assertRedirect();

        $transfer = StockTransfer::where('notes', 'Transfer test')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.stock-transfers.dispatch', $transfer))
            ->assertRedirect();

        $transfer->refresh();
        $this->assertSame('dispatched', $transfer->status);
        $this->assertEqualsWithDelta($sourceBefore - 15, $this->stockFor($row->item_id, $row->warehouse_id), 0.001);
        // Stock is in transit, so it has not arrived yet.
        $this->assertEqualsWithDelta($destinationBefore, $this->stockFor($row->item_id, $destination->id), 0.001);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.stock-transfers.receive', $transfer))
            ->assertRedirect();

        $transfer->refresh();
        $this->assertSame('received', $transfer->status);
        $this->assertEqualsWithDelta($destinationBefore + 15, $this->stockFor($row->item_id, $destination->id), 0.001);

        $this->assertDatabaseHas('stock_ledger_entries', ['reference_number' => $transfer->transfer_number, 'movement_type' => 'transfer_out']);
        $this->assertDatabaseHas('stock_ledger_entries', ['reference_number' => $transfer->transfer_number, 'movement_type' => 'transfer_in']);
    }

    public function test_transfer_destination_must_differ_from_source(): void
    {
        $warehouse = Warehouse::firstOrFail();
        $item = Item::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.stock-transfers.store'), [
                'transfer_date' => now()->toDateString(),
                'from_warehouse_id' => $warehouse->id,
                'to_warehouse_id' => $warehouse->id,
                'lines' => [['item_id' => $item->id, 'quantity' => 5]],
            ])
            ->assertSessionHasErrors('to_warehouse_id');
    }

    public function test_stock_adjustment_requires_approval_before_posting(): void
    {
        $row = $this->stockedRow();
        $before = (float) $row->quantity;
        $counted = round($before - 5, 3);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.stock-adjustments.store'), [
                'warehouse_id' => $row->warehouse_id,
                'item_id' => $row->item_id,
                'adjustment_date' => now()->toDateString(),
                'adjusted_quantity' => $counted,
                'reason' => 'Adjustment test',
            ])
            ->assertRedirect();

        $adjustment = StockAdjustment::where('reason', 'Adjustment test')->firstOrFail();
        $this->assertSame('draft', $adjustment->status);

        // Posting a draft is rejected; stock only moves after approval.
        $this->actingAs($this->admin())
            ->post(route('admin.inventory.stock-adjustments.post', $adjustment))
            ->assertSessionHasErrors('adjustment');

        $this->assertEqualsWithDelta($before, $this->stockFor($row->item_id, $row->warehouse_id), 0.001);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.stock-adjustments.approve', $adjustment))
            ->assertRedirect();

        $this->assertSame('approved', $adjustment->refresh()->status);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.stock-adjustments.post', $adjustment))
            ->assertRedirect(route('admin.inventory.stock-adjustments.show', $adjustment));

        $adjustment->refresh();
        $this->assertSame('posted', $adjustment->status);
        $this->assertSame('-5.000', (string) $adjustment->difference_quantity);
        $this->assertEqualsWithDelta($counted, $this->stockFor($row->item_id, $row->warehouse_id), 0.001);

        // A loss debits inventory adjustment expense and credits inventory asset.
        $this->assertNotNull($adjustment->journalEntry);
        $this->assertTrue($adjustment->journalEntry->isBalanced());
    }

    public function test_stock_adjustment_uses_live_balance_when_posted(): void
    {
        $row = $this->stockedRow();
        $original = (float) $row->quantity;
        $target = $original - 5;

        $this->actingAs($this->admin())->post(route('admin.inventory.stock-adjustments.store'), [
            'warehouse_id' => $row->warehouse_id,
            'item_id' => $row->item_id,
            'adjustment_date' => now()->toDateString(),
            'adjusted_quantity' => $target,
            'reason' => 'Live balance regression',
        ])->assertRedirect();

        $adjustment = StockAdjustment::where('reason', 'Live balance regression')->firstOrFail();
        $item = Item::findOrFail($row->item_id);
        app(\App\Services\Inventory\StockService::class)->receive(
            $item,
            $row->warehouse_id,
            10,
            (float) $row->average_cost,
            ['movement_type' => 'grn', 'movement_date' => now()->toDateString()]
        );

        $this->actingAs($this->admin())->post(route('admin.inventory.stock-adjustments.approve', $adjustment))->assertRedirect();
        $this->actingAs($this->admin())->post(route('admin.inventory.stock-adjustments.post', $adjustment))->assertRedirect();

        $adjustment->refresh();
        $this->assertSame(number_format($original + 10, 3, '.', ''), (string) $adjustment->current_quantity);
        $this->assertSame('-15.000', (string) $adjustment->difference_quantity);
        $this->assertEqualsWithDelta($target, $this->stockFor($row->item_id, $row->warehouse_id), 0.001);
        $this->assertEqualsWithDelta(
            abs((float) $adjustment->difference_quantity) * (float) $adjustment->unit_cost,
            (float) $adjustment->adjustment_value,
            0.01
        );
    }

    public function test_stock_ledger_records_every_movement(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.inventory.stock-ledger'))
            ->assertOk()
            ->assertSee('Stock Ledger Entries');

        foreach (['grn', 'issue', 'transfer_out', 'transfer_in', 'adjustment'] as $type) {
            $this->assertGreaterThan(
                0,
                StockLedgerEntry::where('movement_type', $type)->count(),
                "Expected seeded ledger entries for movement type {$type}."
            );
        }
    }

    public function test_inventory_reports_load(): void
    {
        $query = ['from' => now()->startOfYear()->toDateString(), 'to' => now()->toDateString()];

        foreach (['stock-valuation', 'low-stock', 'project-consumption', 'movement'] as $report) {
            $this->actingAs($this->admin())
                ->get(route('admin.inventory.reports.'.$report, $query))
                ->assertOk();
        }
    }

    public function test_inventory_permissions_and_warehouse_incharge_role_are_seeded(): void
    {
        foreach (['Items', 'Purchase Requests', 'Goods Receipts', 'Stock Issues', 'Stock Transfers', 'Stock Adjustments', 'Stock Ledger', 'Inventory Reports'] as $module) {
            $this->assertDatabaseHas('permissions', ['module' => $module, 'action' => 'view']);
        }

        foreach (['reject', 'receive', 'issue', 'transfer', 'adjust'] as $action) {
            $this->assertContains($action, Permission::ACTIONS);
            $this->assertDatabaseHas('permissions', ['module' => 'Goods Receipts', 'action' => $action]);
        }

        $role = Role::where('code', 'WAREHOUSE_INCHARGE')->firstOrFail();
        $this->assertSame('Warehouse Incharge', $role->name);

        $granted = $role->permissions()->get()->groupBy('module')->map(fn ($rows) => $rows->pluck('action')->all());

        $this->assertContains('receive', $granted['Goods Receipts'] ?? []);
        $this->assertContains('issue', $granted['Stock Issues'] ?? []);
        $this->assertContains('transfer', $granted['Stock Transfers'] ?? []);
        $this->assertContains('adjust', $granted['Stock Adjustments'] ?? []);
        $this->assertContains('view', $granted['Warehouse Stock'] ?? []);

        // Warehouse Incharge stays off master data and accounting.
        $this->assertArrayNotHasKey('Items', $granted->all());
        $this->assertArrayNotHasKey('Chart of Accounts', $granted->all());
        $this->assertArrayNotHasKey('Purchase Orders', $granted->all());
    }

    public function test_seed_data_covers_every_phase6_table(): void
    {
        $this->assertSame(8, ItemCategory::count());
        $this->assertSame(8, Unit::count());
        $this->assertSame(40, Item::count());
        $this->assertGreaterThan(0, WarehouseStock::count());
        $this->assertSame(8, PurchaseRequest::count());
        $this->assertSame(6, PurchaseOrder::count());
        $this->assertSame(5, GoodsReceipt::count());
        $this->assertSame(10, StockIssue::count());
        $this->assertGreaterThan(0, StockTransfer::count());
        $this->assertGreaterThan(0, StockAdjustment::count());
        $this->assertGreaterThan(0, StockLedgerEntry::count());
        $this->assertGreaterThan(0, WarehouseStock::lowStockCount());

        // Inventory postings reach the ledger.
        $this->assertGreaterThan(0, JournalEntry::where('source_module', 'Inventory')->count());
    }
}
