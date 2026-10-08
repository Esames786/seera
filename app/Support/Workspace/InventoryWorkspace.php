<?php

namespace App\Support\Workspace;

use App\Models\ActivityLog;
use App\Models\GoodsReceiptLine;
use App\Models\Item;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequestLine;
use App\Models\StockAdjustment;
use App\Models\StockIssueLine;
use App\Models\StockLedgerEntry;
use App\Models\StockTransferLine;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;

/** Read-only inventory context. StockService and document controllers own all movements. */
class InventoryWorkspace
{
    public static function data(Item|Warehouse $parent): array
    {
        $actor = auth()->user();
        $parent->load($parent instanceof Item ? ['unit', 'category', 'preferredSupplier'] : ['branch', 'project', 'site', 'incharge']);

        return ['parent' => $parent, 'panels' => self::panels($parent, $actor), 'summary' => self::summary($parent, $actor), $parent instanceof Item ? 'item' : 'warehouse' => $parent];
    }

    public static function module(Item|Warehouse $parent): string
    {
        return $parent instanceof Item ? 'Items' : 'Warehouses';
    }

    public static function prefix(Item|Warehouse $parent): string
    {
        return $parent instanceof Item ? 'admin.inventory.items' : 'admin.master.warehouses';
    }

    public static function panels(Item|Warehouse $parent, User $actor): array
    {
        if (! $actor->hasPermission(self::module($parent), 'view')) {
            return [];
        }
        $modules = ['stock' => 'Warehouse Stock', 'ledger' => 'Stock Ledger'];
        if ($parent instanceof Item) {
            $modules += ['requests' => 'Purchase Requests', 'orders' => 'Purchase Orders'];
        }
        $modules += ['receipts' => 'Goods Receipts', 'issues' => 'Stock Issues', 'transfers' => 'Stock Transfers', 'adjustments' => 'Stock Adjustments'];
        $modules += $parent instanceof Item ? ['accounting' => 'Chart of Accounts'] : ['context' => 'Warehouses'];
        $modules['activity'] = 'Activity Logs';

        return array_filter(array_map(fn ($key, $module) => new PanelDefinition($key, 'inventory_workspace.'.$key, $module), array_keys($modules), array_values($modules)), fn ($definition) => $definition->canView($actor));
    }

    public static function stocks(Item|Warehouse $parent): Builder
    {
        return $parent->stocks()->getQuery()->whereHas('warehouse');
    }

    public static function summary(Item|Warehouse $parent, User $actor): array
    {
        if (! $actor->hasPermission('Warehouse Stock', 'view')) {
            return [];
        }
        $query = self::stocks($parent);
        $totals = (clone $query)->selectRaw('COALESCE(SUM(quantity),0) as qty, COALESCE(SUM(total_value),0) as value')->first();
        $summary = ['value' => $totals->value];
        if ($parent instanceof Item) {
            $summary += ['quantity' => $totals->qty, 'warehouses' => (clone $query)->where('quantity', '>', 0)->count(), 'low' => (float) $parent->reorder_level > 0 && (float) $totals->qty <= (float) $parent->reorder_level];
        } else {
            $summary['items'] = (clone $query)->where('quantity', '>', 0)->count();
            $summary['units'] = (clone $query)->join('items', 'items.id', '=', 'warehouse_stocks.item_id')->leftJoin('units', 'units.id', '=', 'items.unit_id')
                ->groupBy('units.id', 'units.code')->selectRaw('units.code as unit, SUM(warehouse_stocks.quantity) as qty')->get();
        }

        return $summary;
    }

    public static function query(Item|Warehouse $parent, string $panel, User $actor): Builder
    {
        $item = $parent instanceof Item;
        $warehouses = Warehouse::query()->when(! $item, fn ($q) => $q->whereKey($parent->id))->select('warehouses.id');
        $inWarehouse = fn ($q) => $q->whereIn('warehouse_id', clone $warehouses);
        $purchasing = fn ($q) => $q->where(fn ($q) => $q->whereNull('warehouse_id')->orWhereIn('warehouse_id', clone $warehouses));
        $query = match ($panel) {
            'stock' => self::stocks($parent)->with(['item.unit', 'warehouse.project', 'warehouse.site']),
            'ledger' => StockLedgerEntry::whereIn('warehouse_id', clone $warehouses)->with(['item.unit', 'warehouse', 'project', 'site']),
            'requests' => PurchaseRequestLine::whereHas('purchaseRequest', $purchasing)->with(['purchaseRequest.project', 'purchaseRequest.site']),
            'orders' => PurchaseOrderLine::whereHas('purchaseOrder', $purchasing)->with(['purchaseOrder.supplier', 'purchaseOrder.project', 'purchaseOrder.site']),
            'receipts' => GoodsReceiptLine::whereHas('goodsReceipt', $inWarehouse)->with(['item.unit', 'goodsReceipt.warehouse', 'goodsReceipt.supplier', 'goodsReceipt.purchaseOrder.project', 'goodsReceipt.purchaseOrder.site']),
            'issues' => StockIssueLine::whereHas('stockIssue', fn ($q) => $inWarehouse($q)->where('status', 'posted'))->with(['item.unit', 'stockIssue.warehouse', 'stockIssue.project', 'stockIssue.site']),
            'transfers' => StockTransferLine::whereHas('stockTransfer', fn ($q) => $q->where(fn ($q) => $q->whereIn('from_warehouse_id', clone $warehouses)->orWhereIn('to_warehouse_id', clone $warehouses)))->with(['item.unit', 'stockTransfer.fromWarehouse', 'stockTransfer.toWarehouse']),
            'adjustments' => StockAdjustment::whereIn('warehouse_id', clone $warehouses)->with(['item.unit', 'warehouse']),
            'activity' => ActivityLog::visibleTo($actor)->where('module', $item ? 'Inventory' : 'Warehouses')->where('description', 'like', '['.($item ? 'Item' : 'Warehouse').' #'.$parent->id.'] %'),
            default => abort(404),
        };
        if ($item && ! in_array($panel, ['stock', 'activity'])) {
            $query->where('item_id', $parent->id);
        }

        return $query;
    }

    /** Label/value rows; only document links, never posting controls. */
    public static function row($row, string $panel, User $actor): array
    {
        $money = $actor->hasPermission('Warehouse Stock', 'view');
        $num = fn ($v) => number_format((float) $v, 3);
        $price = fn ($v) => number_format((float) $v, 2);
        $project = fn ($record) => $actor->hasPermission('Projects', 'view') ? $record?->project?->name : null;
        $site = fn ($record) => $actor->hasPermission('Sites', 'view') ? $record?->site?->name : null;
        $supplier = fn ($record) => $actor->hasPermission('Suppliers', 'view') ? $record?->supplier?->name : null;
        $document = null;
        $route = null;
        $cells = [];
        if ($panel === 'stock') {
            $cells = ['item' => $row->item?->label(), 'unit' => $row->item?->unit?->code, 'warehouse' => $row->warehouse?->name, 'project' => $project($row->warehouse), 'site' => $site($row->warehouse), 'quantity' => $num($row->quantity), 'reorder' => $num($row->item?->reorder_level), 'minimum' => $num($row->item?->minimum_stock), 'low' => $row->isLowStock() ? __('workspace.yes') : __('workspace.no')];
            if ($money) {
                $cells += ['average' => $price($row->average_cost), 'value' => $price($row->total_value)];
            }
        } elseif ($panel === 'ledger') {
            $cells = ['date' => $row->movement_date?->format('Y-m-d'), 'number' => $row->reference_number, 'movement' => __('inventory_workspace.'.$row->movement_type), 'item' => $row->item?->label(), 'unit' => $row->item?->unit?->code, 'warehouse' => $row->warehouse?->name, 'in' => $num($row->in_quantity), 'out' => $num($row->out_quantity), 'balance' => $num($row->balance_quantity), 'project' => $project($row), 'site' => $site($row)];
            if ($money) {
                $cells += ['unit_cost' => $price($row->unit_cost), 'value' => $price($row->value)];
            }
        } elseif ($panel === 'requests' || $panel === 'orders') {
            $order = $panel === 'orders';
            $document = $order ? $row->purchaseOrder : $row->purchaseRequest;
            $route = 'admin.inventory.'.($order ? 'purchase-orders' : 'purchase-requests').'.show';
            $cells = ['number' => $order ? $document->po_number : $document->pr_number, 'project' => $project($document), 'site' => $site($document), 'quantity' => $num($row->quantity), 'status' => $document->status];
            if ($order) {
                $cells += ['supplier' => $supplier($document), 'received' => $num($row->received_quantity)];
            }
        } elseif ($panel === 'receipts') {
            $document = $row->goodsReceipt;
            $route = 'admin.inventory.goods-receipts.show';
            $cells = ['number' => $document->grn_number, 'date' => $document->received_date?->format('Y-m-d'), 'item' => $row->item?->label(), 'unit' => $row->item?->unit?->code, 'po' => $actor->hasPermission('Purchase Orders', 'view') ? $document->purchaseOrder?->po_number : null, 'supplier' => $supplier($document), 'warehouse' => $document->warehouse?->name, 'project' => $project($document->purchaseOrder), 'site' => $site($document->purchaseOrder), 'received' => $num($row->received_quantity), 'accepted' => $num($row->accepted_quantity), 'rejected' => $num($row->rejected_quantity), 'status' => $document->status, 'stock_posted' => $document->stock_updated ? __('workspace.yes') : __('workspace.no')];
            if ($money) {
                $cells += ['unit_cost' => $price($row->unit_cost), 'value' => $price($row->total_cost)];
            }
            if ($actor->hasPermission('Accounts Payable', 'view')) {
                $cells += ['invoiced' => $num($row->invoiced_quantity), 'uninvoiced' => $num($row->uninvoicedQuantity())];
            }
        } elseif ($panel === 'issues') {
            $document = $row->stockIssue;
            $route = 'admin.inventory.stock-issues.show';
            $cells = ['number' => $document->issue_number, 'date' => $document->issue_date?->format('Y-m-d'), 'item' => $row->item?->label(), 'unit' => $row->item?->unit?->code, 'warehouse' => $document->warehouse?->name, 'project' => $project($document), 'site' => $site($document), 'quantity' => $num($row->quantity), 'status' => $document->status];
            if ($money) {
                $cells += ['value' => $price($row->total_cost)];
            }
        } elseif ($panel === 'transfers') {
            $document = $row->stockTransfer;
            $route = 'admin.inventory.stock-transfers.show';
            $cells = ['number' => $document->transfer_number, 'from' => $document->fromWarehouse?->name ?? __('inventory_workspace.outside_scope'), 'to' => $document->toWarehouse?->name ?? __('inventory_workspace.outside_scope'), 'item' => $row->item?->label(), 'unit' => $row->item?->unit?->code, 'quantity' => $num($row->quantity), 'status' => $document->status, 'sent' => $document->dispatch_date?->format('Y-m-d'), 'received_date' => $document->receive_date?->format('Y-m-d')];
        } elseif ($panel === 'adjustments') {
            $document = $row;
            $route = 'admin.inventory.stock-adjustments.show';
            $cells = ['number' => $row->adjustment_number, 'date' => $row->adjustment_date?->format('Y-m-d'), 'warehouse' => $row->warehouse?->name, 'item' => $row->item?->label(), 'unit' => $row->item?->unit?->code, 'difference' => $num($row->difference_quantity), 'reason' => $row->reason, 'status' => $row->status];
            if ($money) {
                $cells += ['value' => $price($row->adjustment_value)];
            }
        } else {
            $cells = ['date' => $row->created_at, 'action' => $row->action, 'actor' => $row->user_name];
        }

        return compact('cells', 'document', 'route');
    }
}
