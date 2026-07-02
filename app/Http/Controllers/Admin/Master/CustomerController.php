<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::withCount('projects')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('vat_number', 'like', "%{$search}%"));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.master.customers.index', [
            'customers' => $customers,
            'totalCustomers' => Customer::count(),
            'activeCustomers' => Customer::where('status', 'active')->count(),
            'totalReceivable' => Customer::sum('opening_receivable'),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::create($this->validated($request));

        ActivityLog::record($request, 'Customers', 'Created customer', $customer->name);

        return redirect()->route('admin.master.customers.index')->with('status', 'Customer "'.$customer->name.'" created successfully.');
    }

    public function show(Customer $customer): View
    {
        $customer->load('projects.manager');

        return view('admin.master.customers.show', ['customer' => $customer]);
    }

    public function edit(Customer $customer): View
    {
        return view('admin.master.customers.edit', ['customer' => $customer]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request, $customer));

        ActivityLog::record($request, 'Customers', 'Updated customer', $customer->name);

        return redirect()->route('admin.master.customers.index')->with('status', 'Customer "'.$customer->name.'" updated successfully.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        if ($customer->projects()->exists()) {
            return back()->withErrors(['customer' => 'This customer still has projects attached.']);
        }

        $name = $customer->name;
        $customer->delete();

        ActivityLog::record($request, 'Customers', 'Deleted customer', $name);

        return redirect()->route('admin.master.customers.index')->with('status', 'Customer "'.$name.'" deleted successfully.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:customers,code'.($customer ? ','.$customer->id : '')],
            'type' => ['required', 'in:Company,Individual'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'cr_number' => ['nullable', 'string', 'max:50'],
            'opening_receivable' => ['nullable', 'numeric'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'linked_account' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
