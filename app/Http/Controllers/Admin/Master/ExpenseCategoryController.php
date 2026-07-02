<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = ExpenseCategory::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.master.expense-categories.index', [
            'categories' => $categories,
            'totalCategories' => ExpenseCategory::count(),
            'mobileCategories' => ExpenseCategory::where('mobile_visible', true)->count(),
            'approvalCategories' => ExpenseCategory::where('approval_required', true)->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.expense-categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $category = ExpenseCategory::create($this->validated($request));

        ActivityLog::record($request, 'Expense Categories', 'Created expense category', $category->name);

        return redirect()->route('admin.master.expense-categories.index')->with('status', 'Expense category "'.$category->name.'" created successfully.');
    }

    public function show(ExpenseCategory $expense_category): View
    {
        return view('admin.master.expense-categories.show', ['category' => $expense_category]);
    }

    public function edit(ExpenseCategory $expense_category): View
    {
        return view('admin.master.expense-categories.edit', ['category' => $expense_category]);
    }

    public function update(Request $request, ExpenseCategory $expense_category): RedirectResponse
    {
        $expense_category->update($this->validated($request, $expense_category));

        ActivityLog::record($request, 'Expense Categories', 'Updated expense category', $expense_category->name);

        return redirect()->route('admin.master.expense-categories.index')->with('status', 'Expense category "'.$expense_category->name.'" updated successfully.');
    }

    public function destroy(Request $request, ExpenseCategory $expense_category): RedirectResponse
    {
        $name = $expense_category->name;
        $expense_category->delete();

        ActivityLog::record($request, 'Expense Categories', 'Deleted expense category', $name);

        return redirect()->route('admin.master.expense-categories.index')->with('status', 'Expense category "'.$name.'" deleted successfully.');
    }

    private function validated(Request $request, ?ExpenseCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:expense_categories,code'.($category ? ','.$category->id : '')],
            'linked_account' => ['nullable', 'string', 'max:255'],
            'approval_required' => ['nullable', 'boolean'],
            'mobile_visible' => ['nullable', 'boolean'],
            'payment_type' => ['required', 'in:Cash,Bank,Both'],
            'invoice_photo_required' => ['nullable', 'boolean'],
            'vat_treatment' => ['required', 'in:VAT 15%,Non-VAT'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
