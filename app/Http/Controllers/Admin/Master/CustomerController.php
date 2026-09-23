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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'customerTypes' => Customer::typeOptions()->merge(Customer::distinct()->pluck('type'))->unique(),
            'totalCustomers' => Customer::count(),
            'activeCustomers' => Customer::where('status', 'active')->count(),
            'totalReceivable' => Customer::sum('opening_receivable'),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.customers.create', $this->formOptions());
    }

    /** Also serves the "+ New" dialog on the project form (JSON). */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $related = $this->validatedRelated($request);
        $customer = DB::transaction(function () use ($request, $data, $related) {
            $customer = Customer::create($data);
            $this->saveRelated($request, $customer, $related);

            return $customer;
        });

        ActivityLog::record($request, 'Customers', 'Created customer', $customer->name);

        if ($request->wantsJson()) {
            return response()->json(['id' => $customer->id, 'label' => $customer->name, 'code' => $customer->code], 201);
        }

        return $this->savedResponse($request, $customer, 'Customer "'.$customer->name.'" created successfully.');
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
        $customer->load(['contacts.site', 'notes.user']);

        return view('admin.master.customers.edit', ['customer' => $customer] + $this->formOptions($customer));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $this->validated($request, $customer);
        $related = $this->validatedRelated($request, $customer);
        DB::transaction(function () use ($request, $customer, $data, $related) {
            $customer->update($data);
            $this->saveRelated($request, $customer, $related);
        });

        ActivityLog::record($request, 'Customers', 'Updated customer', $customer->name);

        return $this->savedResponse($request, $customer, 'Customer "'.$customer->name.'" updated successfully.');
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

        $this->validateSite($customer, $data['site_id'] ?? null, 'site_id');

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

        return redirect()->route('admin.master.customers.edit', $customer)->with('status', 'Contact "'.$name.'" removed.');
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

        return redirect()->route('admin.master.customers.edit', $customer)->with('status', 'Note removed.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:customers,code'.($customer ? ','.$customer->id : '')],
            'type' => ['required', Rule::in(Customer::typeOptions($customer?->type)->all())],
            'allowed_payment_types' => ['sometimes', 'required', Rule::in(Customer::PAYMENT_TYPES)],
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

    private function formOptions(?Customer $customer = null): array
    {
        return [
            'ratings' => Customer::RATINGS,
            'customerTypes' => Customer::typeOptions($customer?->type),
            'sites' => $customer ? Site::whereIn('project_id', $customer->projects()->pluck('id'))->orderBy('name')->get() : collect(),
        ];
    }

    private function validatedRelated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'new_contacts' => ['sometimes', 'array:office,site'],
            'new_contacts.*' => ['array:name,title,phone,email,address,site_id'],
            'new_contacts.*.name' => ['nullable', 'string', 'max:255'],
            'new_contacts.*.title' => ['nullable', 'string', 'max:100'],
            'new_contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'new_contacts.*.email' => ['nullable', 'email', 'max:255'],
            'new_contacts.*.address' => ['nullable', 'string', 'max:255'],
            'new_contacts.*.site_id' => ['nullable', 'integer'],
            'new_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $contacts = [];
        foreach ($data['new_contacts'] ?? [] as $kind => $contact) {
            if (! collect($contact)->contains(fn ($value) => filled($value))) {
                continue;
            }
            if (blank($contact['name'] ?? null)) {
                throw ValidationException::withMessages(["new_contacts.$kind.name" => 'A contact name is required when entering contact details.']);
            }
            if ($kind === 'office') {
                $contact['site_id'] = null;
            }
            $this->validateSite($customer, $contact['site_id'] ?? null, "new_contacts.$kind.site_id");
            $contacts[] = $contact + ['location_type' => $kind];
        }
        $note = trim($data['new_note'] ?? '');
        if ($contacts !== [] || $note !== '') {
            abort_unless($request->user()->hasPermission('Customers', 'create'), 403);
        }

        return ['contacts' => $contacts, 'note' => $note];
    }

    private function validateSite(?Customer $customer, mixed $siteId, string $field): void
    {
        if (filled($siteId) && (! $customer || ! Site::whereKey($siteId)->whereIn('project_id', $customer->projects()->pluck('id'))->exists())) {
            throw ValidationException::withMessages([$field => 'Select a permitted site belonging to this customer.']);
        }
    }

    private function saveRelated(Request $request, Customer $customer, array $related): void
    {
        foreach ($related['contacts'] as $contact) {
            $created = $customer->contacts()->create($contact);
            ActivityLog::record($request, 'Customers', 'Added customer contact', $customer->name.': '.$created->name);
        }
        if ($related['note'] !== '') {
            $customer->notes()->create(['user_id' => $request->user()->id, 'note' => $related['note']]);
            ActivityLog::record($request, 'Customers', 'Added customer note', $customer->name);
        }
    }

    private function savedResponse(Request $request, Customer $customer, string $message): RedirectResponse
    {
        return ($request->input('_save_action') === 'stay'
            ? redirect()->route('admin.master.customers.edit', $customer)
            : redirect()->route('admin.master.customers.index'))
            ->with('status', $message);
    }
}
