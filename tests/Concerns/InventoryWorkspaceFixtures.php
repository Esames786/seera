<?php

namespace Tests\Concerns;

use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\StockLedgerEntry;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;

trait InventoryWorkspaceFixtures
{
    protected Item $item;

    protected Warehouse $warehouse;

    protected Warehouse $foreign;

    protected function inventoryFixtures(): void
    {
        $unit = Unit::create(['code' => 'KG', 'name' => 'Kilograms']);
        $this->item = Item::create(['item_code' => 'ITEM-A', 'name' => 'Steel A', 'unit_id' => $unit->id, 'reorder_level' => 8, 'minimum_stock' => 2, 'average_cost' => 999999]);
        foreach (['Visible', 'Secret'] as $name) {
            $project = Project::create(['code' => $name, 'name' => $name.' project']);
            $site = Site::create(['code' => $name, 'name' => $name.' site', 'project_id' => $project->id]);
            $warehouse = Warehouse::create(['code' => $name, 'name' => $name.' warehouse', 'project_id' => $project->id, 'site_id' => $site->id, 'valuation_method' => 'Average']);
            if ($name === 'Visible') {
                $this->warehouse = $warehouse;
            } else {
                $this->foreign = $warehouse;
            }
            WarehouseStock::create(['item_id' => $this->item->id, 'warehouse_id' => $warehouse->id, 'quantity' => $name === 'Visible' ? 5 : 900, 'average_cost' => $name === 'Visible' ? 25 : 100, 'total_value' => $name === 'Visible' ? 125 : 90000]);
        }
    }

    protected function inventoryActor(?array $modules = null, string $scope = 'Company Level'): User
    {
        $role = Role::create(['name' => 'Inventory tester', 'code' => uniqid(), 'status' => 'active', 'access_scope' => $scope]);
        foreach ($modules ?? ['Items', 'Warehouses', 'Warehouse Stock', 'Stock Ledger', 'Purchase Requests', 'Purchase Orders', 'Goods Receipts', 'Stock Issues', 'Stock Transfers', 'Stock Adjustments', 'Chart of Accounts', 'Activity Logs', 'Projects', 'Sites', 'Suppliers', 'Accounts Payable'] as $module) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $role->permissions()->attach(Permission::firstOrCreate(compact('module', 'action'))->id);
            }
        }
        $actor = User::factory()->create(['status' => 'active', 'project_id' => $this->warehouse->project_id, 'site_id' => $this->warehouse->site_id, 'warehouse_id' => $this->warehouse->id]);
        $actor->roles()->attach($role, ['is_primary' => true]);

        return $actor;
    }

    protected function inventoryDocuments(Warehouse $warehouse, string $tag, ?Item $item = null): void
    {
        $item ??= $this->item;
        $supplier = Supplier::firstOrCreate(['code' => 'SUP-A'], ['name' => 'Supplier A']);
        $context = ['warehouse_id' => $warehouse->id, 'project_id' => $warehouse->project_id, 'site_id' => $warehouse->site_id];
        $pr = PurchaseRequest::create($context + ['pr_number' => 'PR-'.$tag, 'request_date' => today()]);
        $pr->lines()->create(['item_id' => $item->id, 'quantity' => 10]);
        $po = PurchaseOrder::create($context + ['po_number' => 'PO-'.$tag, 'po_date' => today(), 'supplier_id' => $supplier->id]);
        $po->lines()->create(['item_id' => $item->id, 'quantity' => 10]);
        $grn = GoodsReceipt::create(['warehouse_id' => $warehouse->id, 'grn_number' => 'GRN-'.$tag, 'received_date' => today(), 'supplier_id' => $supplier->id, 'purchase_order_id' => $po->id, 'status' => 'posted', 'stock_updated' => true]);
        $grn->lines()->create(['item_id' => $item->id, 'received_quantity' => 10, 'accepted_quantity' => 9, 'rejected_quantity' => 1, 'invoiced_quantity' => 3, 'unit_cost' => 25, 'total_cost' => 225]);
        foreach (['posted', 'draft'] as $state) {
            $issue = StockIssue::create($context + ['issue_number' => 'ISS-'.$tag.'-'.$state, 'issue_date' => today(), 'status' => $state]);
            $issue->lines()->create(['item_id' => $item->id, 'quantity' => 2, 'unit_cost' => 25, 'total_cost' => 50]);
        }
        StockAdjustment::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'adjustment_number' => 'ADJ-'.$tag, 'adjustment_date' => today(), 'difference_quantity' => -1, 'reason' => 'Count correction', 'status' => 'posted', 'adjustment_value' => 25]);
        StockLedgerEntry::create($context + ['item_id' => $item->id, 'movement_type' => 'grn', 'movement_date' => today(), 'reference_number' => 'MOV-'.$tag, 'in_quantity' => 9, 'balance_quantity' => 9, 'unit_cost' => 25, 'value' => 225]);
    }

    protected function inventoryTransfer(Warehouse $from, Warehouse $to, string $tag): StockTransfer
    {
        $transfer = StockTransfer::create(['transfer_number' => $tag, 'transfer_date' => today(), 'from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'status' => 'received', 'dispatch_date' => today(), 'receive_date' => today()]);
        $transfer->lines()->create(['item_id' => $this->item->id, 'quantity' => 3, 'unit_cost' => 25, 'total_cost' => 75]);

        return $transfer;
    }

    protected function itemInput(array $extra = []): array
    {
        return $extra + ['item_code' => $this->item->item_code, 'name' => $this->item->name, 'unit_id' => $this->item->unit_id, 'valuation_method' => 'average', 'reorder_level' => 8, 'minimum_stock' => 2, 'maximum_stock' => 100, 'status' => 'active', 'vat_applicable' => 1];
    }

    protected function warehouseInput(array $extra = []): array
    {
        return $extra + ['code' => $this->warehouse->code, 'name' => $this->warehouse->name, 'project_id' => $this->warehouse->project_id, 'site_id' => $this->warehouse->site_id, 'valuation_method' => 'Average', 'status' => 'active'];
    }
}
