<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\Item;
use App\Models\StockLedgerEntry;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Support\Workspace\InventoryWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InventoryWorkspaceFixtures;
use Tests\TestCase;

class ItemWorkspaceTest extends TestCase
{
    use InventoryWorkspaceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixtures();
    }

    private function panel(string $panel, array $query = [])
    {
        return $this->getJson(route('admin.inventory.items.workspace.panel', [$this->item, $panel, ...$query]));
    }

    public function test_view_manage_identity_and_every_panel_are_read_only_on_get(): void
    {
        $actor = $this->inventoryActor();
        $this->actingAs($actor);
        $before = $this->item->fresh()->getAttributes();
        $stocks = WarehouseStock::all()->toArray();
        $logs = ActivityLog::count();
        $this->get(route('admin.inventory.items.show', $this->item))->assertOk()->assertSee('ITEM-A')->assertSee('KG')->assertDontSee('name="_save_action"', false)->assertDontSee('name="inventory_account_id"', false);
        $this->get(route('admin.inventory.items.edit', $this->item))->assertOk()->assertSee('data-workspace-guard-tabs', false)->assertSee('Save &amp; close', false);
        foreach (InventoryWorkspace::panels($this->item, $actor) as $panel) {
            $this->panel($panel->key)->assertOk()->assertDontSee('<form', false);
        }
        $this->assertSame($before, $this->item->fresh()->getAttributes());
        $this->assertSame($stocks, WarehouseStock::all()->toArray());
        $this->assertSame($logs, ActivityLog::count());
    }

    public function test_save_stay_close_new_safe_origin_and_stock_not_editable(): void
    {
        $this->actingAs($this->inventoryActor());
        $url = route('admin.inventory.items.update', $this->item);
        $this->put($url, $this->itemInput(['_save_action' => 'stay', 'quantity' => 999, 'average_cost' => 1]))->assertRedirect(route('admin.inventory.items.edit', $this->item));
        $this->assertSame('999999.0000', $this->item->fresh()->average_cost);
        $this->assertSame('5.000', WarehouseStock::where('warehouse_id', $this->warehouse->id)->value('quantity'));
        $origin = '/admin/master/projects/'.$this->warehouse->project_id.'#stock';
        $this->put($url, $this->itemInput(['_save_action' => 'close', '_return_to' => $origin]))->assertRedirect($origin);
        $this->put($url, $this->itemInput(['_save_action' => 'new']))->assertRedirect(route('admin.inventory.items.create'));
        $this->put($url, $this->itemInput(['_return_to' => 'https://evil.test']))->assertRedirect(route('admin.inventory.items.index'));
        $this->post(route('admin.inventory.items.store'), $this->itemInput(['item_code' => 'NEW', '_save_action' => 'stay']))->assertRedirect(route('admin.inventory.items.edit', Item::where('item_code', 'NEW')->firstOrFail()));
    }

    public function test_stock_totals_values_and_list_never_use_hidden_warehouses_or_company_cost(): void
    {
        $actor = $this->inventoryActor(scope: 'Project Level');
        $this->actingAs($actor);
        $summary = InventoryWorkspace::summary($this->item, $actor);
        $this->assertEquals(5, $summary['quantity']);
        $this->assertEquals(125, $summary['value']);
        $this->assertSame(1, $summary['warehouses']);
        $this->assertTrue($summary['low']);
        $this->panel('stock')->assertOk()->assertSee('Visible warehouse')->assertSee('125.00')->assertSee('25.00')->assertDontSee('Secret warehouse')->assertDontSee('90,000.00');
        $this->get(route('admin.inventory.items.show', $this->item))->assertOk()->assertSee('125.00')->assertDontSee('90,000.00')->assertDontSee('999,999');
        $this->get(route('admin.inventory.items.index'))->assertOk()->assertDontSee('999,999')->assertDontSee('90,000.00');
    }

    public function test_item_view_does_not_grant_stock_finance_procurement_or_activity(): void
    {
        $this->actingAs($this->inventoryActor(['Items']));
        $this->get(route('admin.inventory.items.show', $this->item))->assertOk()->assertDontSee('125.00')->assertDontSee('data-workspace-related="', false);
        $this->get(route('admin.inventory.items.index'))->assertOk()->assertDontSee('Stock Value')->assertDontSee('999,999');
        $this->get(route('admin.inventory.items.edit', $this->item))->assertOk()->assertDontSee('name="inventory_account_id"', false);
        foreach (['stock', 'ledger', 'requests', 'orders', 'receipts', 'issues', 'transfers', 'adjustments', 'accounting', 'activity'] as $panel) {
            $this->panel($panel)->assertForbidden();
        }
        $this->put(route('admin.inventory.items.update', $this->item), $this->itemInput(['inventory_account_id' => null]))->assertForbidden();
        $this->actingAs($this->inventoryActor(['Warehouse Stock']));
        $this->panel('stock')->assertForbidden();
    }

    public function test_document_relations_and_financial_permissions(): void
    {
        $this->inventoryDocuments($this->warehouse, 'VISIBLE');
        $this->inventoryDocuments($this->foreign, 'HIDDEN');
        $other = Item::create(['item_code' => 'OTHER', 'name' => 'Other item']);
        $this->inventoryDocuments($this->warehouse, 'OTHER', $other);
        $this->actingAs($this->inventoryActor(scope: 'Project Level'));
        foreach (['requests' => 'PR-', 'orders' => 'PO-', 'receipts' => 'GRN-', 'issues' => 'ISS-', 'adjustments' => 'ADJ-', 'ledger' => 'MOV-'] as $panel => $prefix) {
            $this->panel($panel)->assertOk()->assertSee($prefix.'VISIBLE')->assertDontSee($prefix.'HIDDEN')->assertDontSee($prefix.'OTHER');
        }
        $this->panel('issues')->assertDontSee('ISS-VISIBLE-draft')->assertDontSee('GRN-VISIBLE');
        $html = $this->panel('receipts')->json('html');
        $this->assertStringContainsString('Not Yet Invoiced', $html);
        $this->assertStringContainsString('6.000', $html);
        $this->actingAs($this->inventoryActor(['Items', 'Goods Receipts']));
        $html = $this->panel('receipts')->assertOk()->json('html');
        $this->assertStringNotContainsString('Not Yet Invoiced', $html);
        $this->assertStringNotContainsString('225.00', $html);
        $this->assertStringNotContainsString('Unit Cost', $html);
    }

    public function test_ledger_pagination_and_parent_filter_forgery(): void
    {
        for ($i = 0; $i < 12; $i++) {
            StockLedgerEntry::create(['item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'movement_type' => 'grn', 'movement_date' => today(), 'reference_number' => 'ENTRY-'.$i, 'balance_quantity' => 77]);
        }
        $this->actingAs($this->inventoryActor());
        $this->panel('ledger')->assertOk()->assertSee('ENTRY-11')->assertDontSee('ENTRY-0<')->assertSee('77.000')->assertSee('data-panel-load');
        $this->panel('ledger', ['page' => 2])->assertOk()->assertSee('ENTRY-0');
        foreach (['warehouse_id', 'item_id', 'project_id', 'site_id', 'record'] as $field) {
            $this->panel('stock', [$field => $this->foreign->id])->assertUnprocessable();
        }
    }

    public function test_transfer_scope_retains_existing_source_only_project_policy(): void
    {
        $this->inventoryTransfer($this->warehouse, $this->foreign, 'OUTBOUND');
        $this->inventoryTransfer($this->foreign, $this->warehouse, 'INBOUND');
        $this->actingAs($this->inventoryActor(scope: 'Project Level'));
        $this->panel('transfers')->assertOk()->assertSee('OUTBOUND')->assertDontSee('INBOUND')->assertDontSee('Secret warehouse');
        $this->actingAs($this->inventoryActor(scope: 'Warehouse Level'));
        $this->panel('transfers')->assertOk()->assertSee('OUTBOUND')->assertSee('INBOUND')->assertSee('Outside permitted scope');
    }

    public function test_deletion_cannot_cascade_hidden_stock_or_document_history(): void
    {
        WarehouseStock::where('warehouse_id', $this->warehouse->id)->delete();
        $this->actingAs($this->inventoryActor(['Items'], 'Project Level'));
        $this->delete(route('admin.inventory.items.destroy', $this->item))->assertRedirect();
        $this->assertSame('inactive', $this->item->fresh()->status);
        $this->assertDatabaseHas('warehouse_stocks', ['item_id' => $this->item->id, 'warehouse_id' => $this->foreign->id, 'quantity' => 900]);
    }

    public function test_arabic_keys_rtl_and_exact_activity_tokens(): void
    {
        $actor = $this->inventoryActor();
        $actor->update(['language' => 'Arabic']);
        $this->actingAs($actor);
        $this->get(route('admin.inventory.items.show', $this->item))->assertOk()->assertSee('dir="rtl"', false);
        $en = require base_path('lang/en/inventory_workspace.php');
        $ar = require base_path('lang/ar/inventory_workspace.php');
        $this->assertSame(array_keys($en), array_keys($ar));
        foreach ($ar as $value) {
            $this->assertMatchesRegularExpression('/[\x{0600}-\x{06ff}]/u', $value);
        }
        foreach (['[Item #'.$this->item->id.'] Steel', '[Item #999] Steel', 'Steel A'] as $i => $description) {
            ActivityLog::create(['user_id' => $actor->id, 'user_name' => 'Tester', 'module' => 'Inventory', 'action' => 'ACT-'.$i, 'description' => $description]);
        }
        $this->panel('activity')->assertOk()->assertSee('ACT-0')->assertDontSee('ACT-1')->assertDontSee('ACT-2');
    }

    public function test_mapping_uses_existing_master_fields_without_posting_or_revaluation(): void
    {
        $account = ChartOfAccount::create(['account_code' => 'INV-TEST', 'account_name' => 'Inventory mapping test', 'account_type' => 'asset']);
        $this->actingAs($this->inventoryActor());
        $this->put(route('admin.inventory.items.update', $this->item), $this->itemInput(['inventory_account_id' => $account->id, 'valuation_method' => 'fifo', '_save_action' => 'stay']))->assertRedirect();
        $this->panel('accounting')->assertOk()->assertSee('Inventory mapping test')->assertSee('fifo')->assertSee('configuration only');
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $this->warehouse->id, 'quantity' => 5, 'average_cost' => 25, 'total_value' => 125]);
        $this->actingAs($this->inventoryActor(['Items']));
        $this->get(route('admin.inventory.items.edit', $this->item))->assertOk()->assertDontSee('Inventory mapping test');
        $this->panel('accounting')->assertForbidden();
    }

    public function test_lazy_panel_contract_and_query_count_does_not_grow_per_row(): void
    {
        $this->actingAs($this->inventoryActor());
        $countQueries = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->panel('stock')->assertOk()->assertSee('data-panel-status');
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };
        $initial = $countQueries();
        for ($i = 0; $i < 10; $i++) {
            $warehouse = Warehouse::create(['code' => 'PERF-'.$i, 'name' => 'Paged warehouse '.$i]);
            WarehouseStock::create(['warehouse_id' => $warehouse->id, 'item_id' => $this->item->id, 'quantity' => 1]);
        }
        $this->assertLessThanOrEqual($initial + 2, $countQueries());
    }
}
