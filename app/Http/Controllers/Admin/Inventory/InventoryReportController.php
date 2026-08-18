<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Project;
use App\Models\StockLedgerEntry;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryReportController extends Controller
{
    public function index(): View
    {
        return view('admin.inventory.reports.index');
    }

    public function stockValuation(Request $request): View
    {
        $rows = WarehouseStock::with(['item.unit', 'item.category', 'warehouse'])
            ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
            ->when($request->filled('category'), fn ($q) => $q->whereHas('item', fn ($i) => $i->where('item_category_id', $request->integer('category'))))
            ->where('quantity', '>', 0)
            ->orderByDesc('total_value')
            ->get();

        return view('admin.inventory.reports.stock-valuation', $this->filterOptions() + [
            'rows' => $rows,
            'totalValue' => round((float) $rows->sum('total_value'), 2),
            'totalQuantity' => round((float) $rows->sum('quantity'), 3),
        ]);
    }

    public function lowStock(Request $request): View
    {
        $rows = WarehouseStock::with(['item.unit', 'warehouse'])
            ->join('items', 'items.id', '=', 'warehouse_stocks.item_id')
            ->whereColumn('warehouse_stocks.quantity', '<=', 'items.reorder_level')
            ->where('items.reorder_level', '>', 0)
            ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_stocks.warehouse_id', $request->integer('warehouse')))
            ->select('warehouse_stocks.*')
            ->orderBy('warehouse_stocks.quantity')
            ->get();

        return view('admin.inventory.reports.low-stock', $this->filterOptions() + [
            'rows' => $rows,
            'outOfStock' => $rows->filter(fn ($row) => (float) $row->quantity <= 0)->count(),
        ]);
    }

    /**
     * Material consumed per project, taken from issue movements in the ledger.
     */
    public function projectConsumption(Request $request): View
    {
        $rows = StockLedgerEntry::query()
            ->join('projects', 'projects.id', '=', 'stock_ledger_entries.project_id')
            ->where('stock_ledger_entries.movement_type', 'issue')
            ->when($request->filled('project'), fn ($q) => $q->where('stock_ledger_entries.project_id', $request->integer('project')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('stock_ledger_entries.movement_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('stock_ledger_entries.movement_date', '<=', $request->date('to')))
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('total_value')
            ->selectRaw('projects.id as project_id, projects.name as project_name, COALESCE(SUM(stock_ledger_entries.out_quantity), 0) as total_quantity, COALESCE(SUM(stock_ledger_entries.value), 0) as total_value')
            ->get();

        return view('admin.inventory.reports.project-consumption', $this->filterOptions() + [
            'rows' => $rows,
            'totalValue' => round((float) $rows->sum('total_value'), 2),
        ]);
    }

    public function movement(Request $request): View
    {
        $rows = StockLedgerEntry::query()
            ->join('items', 'items.id', '=', 'stock_ledger_entries.item_id')
            ->when($request->filled('warehouse'), fn ($q) => $q->where('stock_ledger_entries.warehouse_id', $request->integer('warehouse')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('stock_ledger_entries.movement_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('stock_ledger_entries.movement_date', '<=', $request->date('to')))
            ->groupBy('items.id', 'items.item_code', 'items.name')
            ->orderBy('items.item_code')
            ->selectRaw('items.id as item_id, items.item_code, items.name as item_name, COALESCE(SUM(stock_ledger_entries.in_quantity), 0) as total_in, COALESCE(SUM(stock_ledger_entries.out_quantity), 0) as total_out, COALESCE(SUM(stock_ledger_entries.value), 0) as total_value')
            ->get();

        return view('admin.inventory.reports.movement', $this->filterOptions() + [
            'rows' => $rows,
            'totalIn' => round((float) $rows->sum('total_in'), 3),
            'totalOut' => round((float) $rows->sum('total_out'), 3),
        ]);
    }

    private function filterOptions(): array
    {
        return [
            'warehouses' => Warehouse::orderBy('name')->get(),
            'categories' => ItemCategory::orderBy('code')->get(),
            'projects' => Project::orderBy('name')->get(),
            'items' => Item::orderBy('item_code')->get(),
        ];
    }
}
