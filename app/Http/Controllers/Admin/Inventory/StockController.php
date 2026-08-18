<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    /**
     * Stock on hand across warehouses, with the warehouse summary alongside.
     */
    public function index(Request $request): View
    {
        $stocks = WarehouseStock::with(['item.unit', 'item.category', 'warehouse.project', 'warehouse.site'])
            ->when($request->filled('search'), fn ($q) => $q->whereHas('item', function ($i) use ($request) {
                $search = $request->string('search');
                $i->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%");
            }))
            ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
            ->when($request->filled('category'), fn ($q) => $q->whereHas('item', fn ($i) => $i->where('item_category_id', $request->integer('category'))))
            ->when($request->boolean('low_stock'), fn ($q) => $q->join('items', 'items.id', '=', 'warehouse_stocks.item_id')
                ->whereColumn('warehouse_stocks.quantity', '<=', 'items.reorder_level')
                ->where('items.reorder_level', '>', 0)
                ->select('warehouse_stocks.*'))
            ->orderByDesc('total_value')
            ->paginate(20)
            ->withQueryString();

        return view('admin.inventory.stock.index', [
            'stocks' => $stocks,
            'totalValue' => round((float) WarehouseStock::sum('total_value'), 2),
            'totalQuantity' => round((float) WarehouseStock::sum('quantity'), 3),
            'lowStockCount' => WarehouseStock::lowStockCount(),
            'stockedItems' => WarehouseStock::where('quantity', '>', 0)->distinct('item_id')->count('item_id'),
            'warehouseSummary' => Warehouse::withCount('stocks')
                ->withSum('stocks as stock_value', 'total_value')
                ->withSum('stocks as stock_quantity', 'quantity')
                ->orderBy('name')
                ->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'categories' => ItemCategory::orderBy('code')->get(),
            'items' => Item::orderBy('item_code')->get(),
        ]);
    }
}
