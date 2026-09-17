<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\LookupValue;
use App\Models\PaymentTerm;
use App\Models\Project;
use App\Models\Supplier;
use App\Support\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                    ->orWhere('vat_number', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%"));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', $request->string('rating')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.master.suppliers.index', [
            'suppliers' => $suppliers,
            'categories' => Supplier::query()->distinct()->orderBy('category')->pluck('category')->filter(),
            'ratings' => Supplier::RATINGS,
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
        [$data, $projectIds] = $this->validated($request);

        $supplier = DB::transaction(function () use ($data, $projectIds) {
            $supplier = Supplier::create($data);
            $supplier->projects()->sync($projectIds);

            return $supplier;
        });

        ActivityLog::record($request, 'Suppliers', 'Created supplier', $supplier->name);

        if ($request->wantsJson()) {
            return response()->json(['id' => $supplier->id, 'label' => $supplier->name, 'code' => $supplier->code], 201);
        }

        return redirect()->route('admin.master.suppliers.index')->with('status', 'Supplier "'.$supplier->name.'" created successfully.');
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load(['paymentTerm', 'linkedAccount', 'projects.customer']);

        return view('admin.master.suppliers.show', ['supplier' => $supplier]);
    }

    public function edit(Supplier $supplier): View
    {
        $supplier->load('projects');

        return view('admin.master.suppliers.edit', ['supplier' => $supplier] + $this->formOptions($supplier));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        [$data, $projectIds] = $this->validated($request, $supplier);

        DB::transaction(function () use ($supplier, $data, $projectIds) {
            $supplier->update($data);
            $supplier->projects()->sync($projectIds);
        });

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

    /**
     * @return array{0: array, 1: array<int, int>} Supplier attributes and linked project ids.
     */
    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:suppliers,code'.($supplier ? ','.$supplier->id : '')],
            'category' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'rating' => ['nullable', Rule::in(Supplier::RATINGS)],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'cr_number' => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['nullable', 'numeric'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:50'],
            'allowed_payment_types' => ['nullable', Rule::in(Supplier::PAYMENT_TYPES)],
            'payment_terms' => ['nullable', 'string', 'max:50'],
            'payment_term_id' => ['nullable', Rule::exists('payment_terms', 'id')->where('status', 'active')],
            'linked_account' => ['nullable', 'string', 'max:255'],
            // Only the payables control account or an account beneath it may be linked (CR-18).
            'linked_account_id' => ['nullable', Rule::in(ChartOfAccount::payableChoices()->pluck('id')->all())],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ]);

        if (blank($data['code'] ?? null)) {
            $data['code'] = $supplier ? $supplier->code : CodeGenerator::sequential('suppliers', 'code', 'SUP-');
        }

        $data['allowed_payment_types'] = $data['allowed_payment_types'] ?? 'Both';

        $projectIds = array_map('intval', $data['project_ids'] ?? []);
        unset($data['project_ids']);

        return [$data, $projectIds];
    }

    private function formOptions(?Supplier $supplier = null): array
    {
        return [
            'categories' => LookupValue::options('supplier_category', $supplier?->category),
            'ratings' => Supplier::RATINGS,
            'paymentTypes' => Supplier::PAYMENT_TYPES,
            'projects' => Project::orderBy('name')->get(),
            'paymentTerms' => PaymentTerm::active()->orderBy('days')->orderBy('name')->get(),
            'payableAccounts' => ChartOfAccount::payableChoices(),
            'payableControl' => ChartOfAccount::payableControl(),
        ];
    }
}
