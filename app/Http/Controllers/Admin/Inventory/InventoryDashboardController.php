<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\StockLedgerEntry;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\View\View;

class InventoryDashboardController extends Controller
{
    public function index(): View
    {
        $unpostedStock = GoodsReceipt::where('status', 'draft')->count()
            + StockIssue::where('status', 'draft')->count()
            + StockAdjustment::whereIn('status', ['draft', 'approved'])->count();

        return view('admin.inventory.dashboard', [
            'totalItems' => Item::count(),
            'activeItems' => Item::where('status', 'active')->count(),
            'stockValue' => round((float) WarehouseStock::sum('total_value'), 2),
            'lowStockCount' => WarehouseStock::lowStockCount(),
            'pendingRequests' => PurchaseRequest::where('status', 'pending')->count(),
            'openOrders' => PurchaseOrder::whereIn('status', ['approved', 'partially_received'])->count(),
            'pendingReceipts' => GoodsReceipt::where('status', 'draft')->count(),
            'openTransfers' => StockTransfer::whereIn('status', ['draft', 'dispatched'])->count(),
            'unpostedStock' => $unpostedStock,
            'lowStockRows' => WarehouseStock::with(['item.unit', 'warehouse'])
                ->join('items', 'items.id', '=', 'warehouse_stocks.item_id')
                ->whereColumn('warehouse_stocks.quantity', '<=', 'items.reorder_level')
                ->where('items.reorder_level', '>', 0)
                ->select('warehouse_stocks.*')
                ->orderBy('warehouse_stocks.quantity')
                ->limit(8)
                ->get(),
            'warehouseSummary' => Warehouse::withCount('stocks')
                ->withSum('stocks as stock_value', 'total_value')
                ->orderBy('name')
                ->get(),
            'recentMovements' => StockLedgerEntry::with(['item', 'warehouse'])
                ->latest('movement_date')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }
}
