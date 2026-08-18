<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Project;
use App\Models\Site;
use App\Models\StockLedgerEntry;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockLedgerController extends Controller
{
    public function index(Request $request): View
    {
        $query = StockLedgerEntry::with(['item.unit', 'warehouse', 'project', 'site'])
            ->when($request->filled('item'), fn ($q) => $q->where('item_id', $request->integer('item')))
            ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
            ->when($request->filled('movement_type'), fn ($q) => $q->where('movement_type', $request->string('movement_type')))
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->integer('project')))
            ->when($request->filled('site'), fn ($q) => $q->where('site_id', $request->integer('site')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('movement_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('movement_date', '<=', $request->date('to')));

        $totals = (clone $query)
            ->selectRaw('COALESCE(SUM(in_quantity), 0) as total_in, COALESCE(SUM(out_quantity), 0) as total_out, COALESCE(SUM(value), 0) as total_value')
            ->first();

        return view('admin.inventory.ledger.index', [
            'entries' => $query->orderBy('movement_date')->orderBy('id')->paginate(25)->withQueryString(),
            'totalIn' => round((float) $totals->total_in, 3),
            'totalOut' => round((float) $totals->total_out, 3),
            'totalValue' => round((float) $totals->total_value, 2),
            'items' => Item::orderBy('item_code')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'movementTypes' => StockLedgerEntry::MOVEMENT_TYPES,
        ]);
    }
}
