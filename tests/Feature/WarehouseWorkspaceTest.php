<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\StockLedgerEntry;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Support\Workspace\InventoryWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InventoryWorkspaceFixtures;
use Tests\TestCase;

class WarehouseWorkspaceTest extends TestCase
{
    use InventoryWorkspaceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixtures();
    }

    private function panel(string $panel, array $query = [])
    {
        return $this->getJson(route('admin.master.warehouses.workspace.panel', [$this->warehouse, $panel, ...$query]));
    }

    public function test_readonly_view_manage_identity_and_all_panels_leave_data_unchanged(): void
    {
        $actor = $this->inventoryActor();
        $this->actingAs($actor);
        $before = $this->warehouse->fresh()->getAttributes();
        $stocks = WarehouseStock::all()->toArray();
        $logs = ActivityLog::count();
        $this->get(route('admin.master.warehouses.show', $this->warehouse))->assertOk()->assertSee('Visible warehouse')->assertSee('Visible project')->assertSee('Visible site')->assertDontSee('name="_save_action"', false);
        $this->get(route('admin.master.warehouses.edit', $this->warehouse))->assertOk()->assertSee('data-workspace-guard-tabs', false)->assertSee('Save &amp; close', false);
        foreach (InventoryWorkspace::panels($this->warehouse, $actor) as $panel) {
            $this->panel($panel->key)->assertOk()->assertDontSee('<form', false);
        }
        $this->assertSame($before, $this->warehouse->fresh()->getAttributes());
        $this->assertSame($stocks, WarehouseStock::all()->toArray());
        $this->assertSame($logs, ActivityLog::count());
    }

    public function test_profile_save_actions_preserve_stock_and_safe_origin(): void
    {
        $this->actingAs($this->inventoryActor());
        $url = route('admin.master.warehouses.update', $this->warehouse);
        $origin = '/admin/master/sites/'.$this->warehouse->site_id.'#warehouses';
        $this->put($url, $this->warehouseInput(['_save_action' => 'stay', 'quantity' => 10000, 'total_value' => 10000]))->assertRedirect(route('admin.master.warehouses.edit', $this->warehouse));
        $this->put($url, $this->warehouseInput(['_save_action' => 'close', '_return_to' => $origin]))->assertRedirect($origin);
        $this->put($url, $this->warehouseInput(['_save_action' => 'new']))->assertRedirect(route('admin.master.warehouses.create'));
        $this->put($url, $this->warehouseInput(['_return_to' => '/admin/../logout']))->assertRedirect(route('admin.master.warehouses.index'));
        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $this->warehouse->id, 'quantity' => 5, 'total_value' => 125]);
        $this->post(route('admin.master.warehouses.store'), $this->warehouseInput(['code' => 'NEW', '_save_action' => 'stay']))->assertRedirect(route('admin.master.warehouses.edit', Warehouse::where('code', 'NEW')->firstOrFail()));
    }

    public function test_ownership_cannot_move_stock_to_another_scope_and_new_parents_must_match(): void
    {
        $this->actingAs($this->inventoryActor());
        $this->put(route('admin.master.warehouses.update', $this->warehouse), $this->warehouseInput(['project_id' => $this->foreign->project_id]))->assertSessionHasErrors('project_id');
        $this->put(route('admin.master.warehouses.update', $this->warehouse), $this->warehouseInput(['site_id' => null]))->assertSessionHasErrors('site_id');
        $this->post(route('admin.master.warehouses.store'), $this->warehouseInput(['code' => 'BAD', 'site_id' => $this->foreign->site_id]))->assertNotFound();
        $this->assertSame($this->warehouse->project_id, $this->warehouse->fresh()->project_id);
        $this->actingAs($this->inventoryActor(scope: 'Warehouse Level'));
        $this->get(route('admin.master.warehouses.show', $this->foreign))->assertNotFound();
        $this->getJson(route('admin.master.warehouses.workspace.panel', [$this->foreign, 'stock']))->assertNotFound();
        $this->post(route('admin.master.warehouses.store'), $this->warehouseInput(['code' => 'OUTSIDE']))->assertForbidden();
    }

    public function test_totals_use_only_parent_warehouse_and_quantities_are_unit_wise(): void
    {
        $unit = Unit::create(['code' => 'EA', 'name' => 'Each']);
        $other = Item::create(['item_code' => 'B', 'name' => 'Tools', 'unit_id' => $unit->id]);
        WarehouseStock::create(['item_id' => $other->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 4, 'average_cost' => 10, 'total_value' => 40]);
        $actor = $this->inventoryActor();
        $this->actingAs($actor);
        $summary = InventoryWorkspace::summary($this->warehouse, $actor);
        $this->assertEquals(165, $summary['value']);
        $this->assertSame(2, $summary['items']);
        $this->assertCount(2, $summary['units']);
        $this->assertEquals(['KG' => 5, 'EA' => 4], $summary['units']->pluck('qty', 'unit')->all());
        $this->panel('stock')->assertOk()->assertSee('Steel A')->assertSee('Tools')->assertDontSee('Secret warehouse')->assertDontSee('90,000.00');
        $this->get(route('admin.master.warehouses.show', $this->warehouse))->assertOk()->assertSee('165.00')->assertDontSee('90,000.00');
    }

    public function test_documents_only_this_warehouse_and_transfers_cover_both_directions(): void
    {
        $this->inventoryDocuments($this->warehouse, 'LOCAL');
        $this->inventoryDocuments($this->foreign, 'FOREIGN');
        $this->inventoryTransfer($this->warehouse, $this->foreign, 'OUTBOUND');
        $this->inventoryTransfer($this->foreign, $this->warehouse, 'INBOUND');
        $third = Warehouse::create(['name' => 'Third', 'code' => 'THIRD']);
        $this->inventoryTransfer($this->foreign, $third, 'UNRELATED');
        $this->actingAs($this->inventoryActor());
        foreach (['receipts' => 'GRN-', 'issues' => 'ISS-', 'adjustments' => 'ADJ-', 'ledger' => 'MOV-'] as $panel => $prefix) {
            $this->panel($panel)->assertOk()->assertSee($prefix.'LOCAL')->assertDontSee($prefix.'FOREIGN');
        }
        $this->panel('issues')->assertDontSee('ISS-LOCAL-draft');
        $this->panel('transfers')->assertOk()->assertSee('OUTBOUND')->assertSee('INBOUND')->assertDontSee('UNRELATED')->assertSee('Receive needs');
        $context = $this->panel('context')->assertOk()->assertSee('Visible project')->assertSee('Visible site');
        $this->assertStringContainsString(route('admin.master.projects.show', $this->warehouse->project_id), $context->json('html'));
        foreach (['warehouse_id', 'item_id', 'project_id', 'site_id', 'record'] as $field) {
            $this->panel('stock', [$field => $this->foreign->id])->assertUnprocessable();
        }
    }

    public function test_warehouse_right_does_not_grant_child_or_financial_rights(): void
    {
        $this->inventoryDocuments($this->warehouse, 'LOCAL');
        $this->actingAs($this->inventoryActor(['Warehouses', 'Goods Receipts', 'Stock Ledger']));
        $this->get(route('admin.master.warehouses.show', $this->warehouse))->assertOk()->assertDontSee('125.00')->assertDontSee('data-workspace-related="stock"', false);
        foreach (['stock', 'issues', 'transfers', 'adjustments', 'activity'] as $panel) {
            $this->panel($panel)->assertForbidden();
        }
        $this->panel('receipts')->assertOk()->assertDontSee('225.00')->assertDontSee('Unit Cost')->assertDontSee('Not Yet Invoiced');
        $this->panel('ledger')->assertOk()->assertDontSee('225.00')->assertDontSee('Unit Cost');
        $this->panel('context')->assertOk()->assertDontSee('Visible project')->assertDontSee('Visible site');
        $this->actingAs($this->inventoryActor(['Warehouse Stock']));
        $this->panel('stock')->assertForbidden();
    }

    public function test_stock_and_ledger_paginate_and_foreign_warehouse_names_stay_hidden(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $item = Item::create(['item_code' => 'PAGE-'.$i, 'name' => 'Paged '.$i, 'unit_id' => $this->item->unit_id]);
            WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'item_id' => $item->id, 'quantity' => 1]);
            StockLedgerEntry::create(['warehouse_id' => $this->warehouse->id, 'item_id' => $item->id, 'movement_type' => 'grn', 'movement_date' => today(), 'reference_number' => 'MOV-PAGE-'.$i]);
        }
        $this->actingAs($this->inventoryActor(scope: 'Warehouse Level'));
        foreach (['stock', 'ledger'] as $panel) {
            $this->panel($panel)->assertOk()->assertSee('data-panel-load')->assertSee('PAGE-11')->assertDontSee('PAGE-0<');
            $this->panel($panel, ['page' => 2])->assertOk()->assertSee('PAGE-0');
        }
    }

    public function test_deactivation_keeps_stock_ledger_and_documents_instead_of_cascading_delete(): void
    {
        $this->inventoryDocuments($this->warehouse, 'HISTORY');
        $this->actingAs($this->inventoryActor());
        $this->delete(route('admin.master.warehouses.destroy', $this->warehouse))->assertRedirect();
        $this->assertSame('inactive', $this->warehouse->fresh()->status);
        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $this->warehouse->id, 'quantity' => 5]);
        $this->assertDatabaseHas('goods_receipts', ['grn_number' => 'GRN-HISTORY']);
        $this->assertDatabaseHas('stock_ledger_entries', ['reference_number' => 'MOV-HISTORY']);
    }

    public function test_activity_requires_exact_token_and_arabic_rtl(): void
    {
        $actor = $this->inventoryActor();
        $actor->update(['language' => 'Arabic']);
        $this->actingAs($actor);
        foreach (['[Warehouse #'.$this->warehouse->id.'] W', '[Warehouse #999] W', 'Visible warehouse'] as $i => $description) {
            ActivityLog::create(['user_id' => $actor->id, 'user_name' => 'Tester', 'module' => 'Warehouses', 'action' => 'EVENT-'.$i, 'description' => $description]);
        }
        $this->panel('activity')->assertOk()->assertSee('EVENT-0')->assertDontSee('EVENT-1')->assertDontSee('EVENT-2');
        $this->get(route('admin.master.warehouses.show', $this->warehouse))->assertOk()->assertSee('dir="rtl"', false);
    }
}
