<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = ItemCategory::with(['parent', 'inventoryAccount', 'expenseAccount'])
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('admin.inventory.categories.index', [
            'categories' => $categories,
            'totalCategories' => ItemCategory::count(),
            'activeCategories' => ItemCategory::where('status', 'active')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.inventory.categories.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $category = ItemCategory::create($this->validated($request));

        ActivityLog::record($request, 'Inventory', 'Created item category', $category->code.' - '.$category->name);

        return redirect()->route('admin.inventory.categories.index')
            ->with('status', 'Category "'.$category->name.'" created successfully.');
    }

    public function edit(ItemCategory $category): View
    {
        return view('admin.inventory.categories.edit', ['category' => $category] + $this->formOptions($category));
    }

    public function update(Request $request, ItemCategory $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        ActivityLog::record($request, 'Inventory', 'Updated item category', $category->code.' - '.$category->name);

        return redirect()->route('admin.inventory.categories.index')
            ->with('status', 'Category "'.$category->name.'" updated successfully.');
    }

    public function destroy(Request $request, ItemCategory $category): RedirectResponse
    {
        $label = $category->code.' - '.$category->name;

        if ($category->items()->exists() || $category->children()->exists()) {
            $category->update(['status' => 'inactive']);

            ActivityLog::record($request, 'Inventory', 'Deactivated item category', $label);

            return redirect()->route('admin.inventory.categories.index')
                ->with('status', 'Category "'.$category->name.'" has items or sub-categories, so it was deactivated instead of deleted.');
        }

        $category->delete();

        ActivityLog::record($request, 'Inventory', 'Deleted item category', $label);

        return redirect()->route('admin.inventory.categories.index')
            ->with('status', 'Category "'.$category->name.'" deleted successfully.');
    }

    private function validated(Request $request, ?ItemCategory $category = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:item_categories,code'.($category ? ','.$category->id : '')],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => array_filter(['nullable', 'exists:item_categories,id', $category ? 'not_in:'.$category->id : null]),
            'inventory_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'expense_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function formOptions(?ItemCategory $category = null): array
    {
        return [
            'parents' => ItemCategory::when($category, fn ($q) => $q->whereKeyNot($category->id))->orderBy('code')->get(),
            'inventoryAccounts' => ChartOfAccount::where('account_type', 'asset')->where('status', 'active')->orderBy('account_code')->get(),
            'expenseAccounts' => ChartOfAccount::where('account_type', 'expense')->where('status', 'active')->orderBy('account_code')->get(),
        ];
    }
}
