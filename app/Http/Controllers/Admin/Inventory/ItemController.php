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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $items = Item::with(['category', 'unit', 'preferredSupplier'])
            ->withSum('stocks as on_hand', 'quantity')
            ->withSum('stocks as stock_value', 'total_value')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%"));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('item_category_id', $request->integer('category')))
            ->when($request->filled('unit'), fn ($q) => $q->where('unit_id', $request->integer('unit')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereHas('stocks', fn ($s) => $s->whereColumn('warehouse_stocks.quantity', '<=', 'items.reorder_level')))
            ->orderBy('item_code')
            ->paginate(15)
            ->withQueryString();

        return view('admin.inventory.items.index', [
            'items' => $items,
            'totalItems' => Item::count(),
            'activeItems' => Item::where('status', 'active')->count(),
            'lowStockCount' => WarehouseStock::lowStockCount(),
            'stockValue' => round((float) WarehouseStock::sum('total_value'), 2),
        ] + $this->filterOptions());
    }

    public function create(): View
    {
        return view('admin.inventory.items.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $item = Item::create($this->validated($request));

        ActivityLog::record($request, 'Inventory', 'Created item', $item->label());

        return redirect()->route('admin.inventory.items.index')
            ->with('status', 'Item "'.$item->name.'" created successfully.');
    }

    public function show(Item $item): View
    {
        $item->load(['category', 'unit', 'preferredSupplier', 'inventoryAccount', 'expenseAccount']);

        return view('admin.inventory.items.show', [
            'item' => $item,
            'stocks' => $item->stocks()->with('warehouse')->get(),
            'movements' => $item->ledgerEntries()->with('warehouse')->latest('movement_date')->latest('id')->limit(15)->get(),
            'onHand' => $item->totalQuantity(),
            'stockValue' => $item->totalValue(),
        ]);
    }

    public function edit(Item $item): View
    {
        return view('admin.inventory.items.edit', ['item' => $item] + $this->formOptions());
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $item->update($this->validated($request, $item));

        ActivityLog::record($request, 'Inventory', 'Updated item', $item->label());

        return redirect()->route('admin.inventory.items.index')
            ->with('status', 'Item "'.$item->name.'" updated successfully.');
    }

    /**
     * Items holding stock or history are deactivated, never deleted.
     */
    public function destroy(Request $request, Item $item): RedirectResponse
    {
        $label = $item->label();

        if ($item->ledgerEntries()->exists() || $item->totalQuantity() > 0) {
            $item->update(['status' => 'inactive']);

            ActivityLog::record($request, 'Inventory', 'Deactivated item', $label);

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
