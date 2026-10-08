<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\WarehouseStock;
use App\Support\SaveAction;
use App\Support\Workspace\InventoryWorkspace as Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $items = Item::with(['category', 'unit', 'preferredSupplier'])
            ->when($request->user()->hasPermission('Warehouse Stock', 'view'), fn ($q) => $q
                ->withSum(['stocks as on_hand' => fn ($s) => $s->whereHas('warehouse')], 'quantity')
                ->withSum(['stocks as stock_value' => fn ($s) => $s->whereHas('warehouse')], 'total_value'))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%"));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('item_category_id', $request->integer('category')))
            ->when($request->filled('unit'), fn ($q) => $q->where('unit_id', $request->integer('unit')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('low_stock') && $request->user()->hasPermission('Warehouse Stock', 'view'), fn ($q) => $q->where('reorder_level', '>', 0)->whereHas('stocks', fn ($s) => $s->whereHas('warehouse')->whereColumn('warehouse_stocks.quantity', '<=', 'items.reorder_level')))
            ->orderBy('item_code')
            ->paginate(15)
            ->withQueryString();

        return view('admin.inventory.items.index', [
            'items' => $items,
            'totalItems' => Item::count(),
            'activeItems' => Item::where('status', 'active')->count(),
            'lowStockCount' => $request->user()->hasPermission('Warehouse Stock', 'view') ? WarehouseStock::whereHas('warehouse')->whereHas('item', fn ($q) => $q->where('reorder_level', '>', 0)->whereColumn('warehouse_stocks.quantity', '<=', 'items.reorder_level'))->count() : null,
            'stockValue' => $request->user()->hasPermission('Warehouse Stock', 'view') ? round((float) WarehouseStock::whereHas('warehouse')->sum('total_value'), 2) : null,
        ] + $this->filterOptions());
    }

    public function create(): View
    {
        return view('admin.inventory.items.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $item = Item::create($this->validated($request));

        ActivityLog::record($request, 'Inventory', 'Created item', '[Item #'.$item->id.'] '.$item->label());

        return $this->saved($request, $item)
            ->with('status', 'Item "'.$item->name.'" created successfully.');
    }

    public function show(Item $item): View
    {
        return view('admin.inventory.items.show', Workspace::data($item));
    }

    public function edit(Item $item): View
    {
        return view('admin.inventory.items.edit', Workspace::data($item) + $this->formOptions());
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $item->update($this->validated($request, $item));

        ActivityLog::record($request, 'Inventory', 'Updated item', '[Item #'.$item->id.'] '.$item->label());

        return $this->saved($request, $item)
            ->with('status', 'Item "'.$item->name.'" updated successfully.');
    }

    /**
     * Items holding stock or history are deactivated, never deleted.
     */
    public function destroy(Request $request, Item $item): RedirectResponse
    {
        $label = $item->label();

        // Integrity-only existence checks must include hidden warehouses/documents.
        $hasHistory = collect(['warehouse_stocks', 'stock_ledger_entries', 'purchase_request_lines', 'purchase_order_lines', 'goods_receipt_lines', 'stock_issue_lines', 'stock_transfer_lines', 'stock_adjustments'])
            ->contains(fn ($table) => DB::table($table)->where('item_id', $item->id)->exists());
        if ($hasHistory) {
            $item->update(['status' => 'inactive']);

            ActivityLog::record($request, 'Inventory', 'Deactivated item', '[Item #'.$item->id.'] '.$label);

            return redirect()->route('admin.inventory.items.index')
                ->with('status', 'Item "'.$item->name.'" has stock or movement history, so it was deactivated instead of deleted.');
        }

        $item->delete();

        ActivityLog::record($request, 'Inventory', 'Deleted item', $label);

        return redirect()->route('admin.inventory.items.index')
            ->with('status', 'Item "'.$item->name.'" deleted successfully.');
    }

    private function validated(Request $request, ?Item $item = null): array
    {
        if (! $request->user()->hasPermission('Chart of Accounts', 'view')) {
            abort_if($request->exists('inventory_account_id') || $request->exists('expense_account_id'), 403);
        }
        $data = $request->validate([
            'item_code' => ['required', 'string', 'max:50', 'unique:items,item_code'.($item ? ','.$item->id : '')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'item_category_id' => ['nullable', 'exists:item_categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'valuation_method' => ['required', 'in:average,fifo'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'maximum_stock' => ['required', 'numeric', 'min:0'],
            'preferred_supplier_id' => ['nullable', 'exists:suppliers,id'],
            'inventory_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'expense_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'vat_applicable' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $data['vat_applicable'] = $request->boolean('vat_applicable');

        return $data;
    }

    private function filterOptions(): array
    {
        return [
            'categories' => ItemCategory::orderBy('code')->get(),
            'units' => Unit::orderBy('code')->get(),
        ];
    }

    private function saved(Request $request, Item $item): RedirectResponse
    {
        return SaveAction::redirect($request, ['stay' => route('admin.inventory.items.edit', [$item, 'return_to' => SaveAction::returnTo($request)]), 'close' => route('admin.inventory.items.index')]
            + ($request->user()->hasPermission('Items', 'create') ? ['new' => route('admin.inventory.items.create')] : []));
    }

    private function formOptions(): array
    {
        return $this->filterOptions() + [
            'suppliers' => Supplier::orderBy('name')->get(),
            'inventoryAccounts' => ChartOfAccount::where('account_type', 'asset')->where('status', 'active')->orderBy('account_code')->get(),
            'expenseAccounts' => ChartOfAccount::where('account_type', 'expense')->where('status', 'active')->orderBy('account_code')->get(),
            'valuationMethods' => Item::VALUATION_METHODS,
        ];
    }
}
