<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\PaymentTerm;
use App\Models\Supplier;
use App\Support\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('vat_number', 'like', "%{$search}%"));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.master.suppliers.index', [
            'suppliers' => $suppliers,
            'categories' => Supplier::query()->distinct()->orderBy('category')->pluck('category')->filter(),
            'totalSuppliers' => Supplier::count(),
            'activeSuppliers' => Supplier::where('status', 'active')->count(),
            'totalPayable' => Supplier::sum('opening_balance'),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.suppliers.create', $this->formOptions());
    }

    /** Also serves the "+ New" dialog on purchase forms (JSON). */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $supplier = Supplier::create($this->validated($request));

        ActivityLog::record($request, 'Suppliers', 'Created supplier', $supplier->name);

        if ($request->wantsJson()) {
            return response()->json(['id' => $supplier->id, 'label' => $supplier->name, 'code' => $supplier->code], 201);
        }

        return redirect()->route('admin.master.suppliers.index')->with('status', 'Supplier "'.$supplier->name.'" created successfully.');
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load(['paymentTerm', 'linkedAccount']);

        return view('admin.master.suppliers.show', ['supplier' => $supplier]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.master.suppliers.edit', ['supplier' => $supplier] + $this->formOptions());
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier));

        ActivityLog::record($request, 'Suppliers', 'Updated supplier', $supplier->name);

        return redirect()->route('admin.master.suppliers.index')->with('status', 'Supplier "'.$supplier->name.'" updated successfully.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $name = $supplier->name;
        $supplier->delete();

        ActivityLog::record($request, 'Suppliers', 'Deleted supplier', $name);

        return redirect()->route('admin.master.suppliers.index')->with('status', 'Supplier "'.$name.'" deleted successfully.');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:suppliers,code'.($supplier ? ','.$supplier->id : '')],
            'category' => ['nullable', 'string', 'max:100'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'cr_number' => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['nullable', 'numeric'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:50'],
            'payment_term_id' => ['nullable', Rule::exists('payment_terms', 'id')->where('status', 'active')],
            'linked_account' => ['nullable', 'string', 'max:255'],
            // Only the payables control account or an account beneath it may be linked (CR-18).
            'linked_account_id' => ['nullable', Rule::in(ChartOfAccount::payableChoices()->pluck('id')->all())],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        if (blank($data['code'] ?? null)) {
            $data['code'] = $supplier ? $supplier->code : CodeGenerator::sequential('suppliers', 'code', 'SUP-');
        }

        return $data;
    }

    private function formOptions(): array
    {
        return [
            'paymentTerms' => PaymentTerm::active()->orderBy('days')->orderBy('name')->get(),
            'payableAccounts' => ChartOfAccount::payableChoices(),
            'payableControl' => ChartOfAccount::payableControl(),
        ];
    }
}
