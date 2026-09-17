<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerNote;
use App\Models\Site;
use App\Support\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', $request->string('rating')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.master.customers.index', [
            'customers' => $customers,
            'ratings' => Customer::RATINGS,
            'totalCustomers' => Customer::count(),
            'activeCustomers' => Customer::where('status', 'active')->count(),
            'totalReceivable' => Customer::sum('opening_receivable'),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.customers.create', ['ratings' => Customer::RATINGS]);
    }

    /** Also serves the "+ New" dialog on the project form (JSON). */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $customer = Customer::create($this->validated($request));

        ActivityLog::record($request, 'Customers', 'Created customer', $customer->name);

        if ($request->wantsJson()) {
            return response()->json(['id' => $customer->id, 'label' => $customer->name, 'code' => $customer->code], 201);
        }

        return redirect()->route('admin.master.customers.index')->with('status', 'Customer "'.$customer->name.'" created successfully.');
    }

    public function show(Customer $customer): View
    {
        $customer->load(['projects.manager', 'contacts.site', 'notes.user']);

        return view('admin.master.customers.show', [
            'customer' => $customer,
            'overdue' => $customer->overdueSummary(),
            'sites' => Site::whereIn('project_id', $customer->projects->pluck('id'))->orderBy('name')->get(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('admin.master.customers.edit', ['customer' => $customer, 'ratings' => Customer::RATINGS]);
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

    /** Office or site contact person (NR-08). */
    public function storeContact(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'location_type' => ['required', Rule::in(CustomerContact::LOCATION_TYPES)],
            'site_id' => ['nullable', 'exists:sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['location_type'] === 'office') {
            $data['site_id'] = null;
        }

        $contact = $customer->contacts()->create($data);

        ActivityLog::record($request, 'Customers', 'Added customer contact', $customer->name.': '.$contact->name);

        return redirect()->route('admin.master.customers.show', $customer)->with('status', 'Contact "'.$contact->name.'" added.');
    }

    public function destroyContact(Request $request, Customer $customer, CustomerContact $contact): RedirectResponse
    {
        abort_unless($contact->customer_id === $customer->id, 404);

        $name = $contact->name;
        $contact->delete();

        ActivityLog::record($request, 'Customers', 'Removed customer contact', $customer->name.': '.$name);

        return redirect()->route('admin.master.customers.show', $customer)->with('status', 'Contact "'.$name.'" removed.');
    }

    /** Shared note for colleagues (NR-09). */
    public function storeNote(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        $customer->notes()->create(['user_id' => $request->user()->id, 'note' => trim($data['note'])]);

        ActivityLog::record($request, 'Customers', 'Added customer note', $customer->name);

        return redirect()->route('admin.master.customers.show', $customer)->with('status', 'Note added.');
    }

    public function destroyNote(Request $request, Customer $customer, CustomerNote $note): RedirectResponse
    {
        abort_unless($note->customer_id === $customer->id, 404);

        $note->delete();

        ActivityLog::record($request, 'Customers', 'Removed customer note', $customer->name);

        return redirect()->route('admin.master.customers.show', $customer)->with('status', 'Note removed.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:customers,code'.($customer ? ','.$customer->id : '')],
            'type' => ['required', 'in:Company,Individual'],
            'rating' => ['nullable', Rule::in(Customer::RATINGS)],
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

        // The quick-create dialog leaves the code blank; the full form still asks for it.
        if (blank($data['code'] ?? null)) {
            $data['code'] = $customer ? $customer->code : CodeGenerator::sequential('customers', 'code', 'CUS-');
        }

        return $data;
    }
}
