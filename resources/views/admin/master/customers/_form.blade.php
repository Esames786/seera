@php /** @var \App\Models\Customer|null $customer */ $customer = $customer ?? null; @endphp

<form method="POST" action="{{ $customer ? route('admin.master.customers.update', $customer) : route('admin.master.customers.store') }}">
    @csrf
    @if ($customer) @method('PUT') @endif

    <x-admin.form-section title="Customer Information" columns="3">
        <div><label for="name">Customer Name *</label><input id="name" name="name" class="input" value="{{ old('name', $customer?->name) }}" required/></div>
        <div><label for="code">Customer Code *</label><input id="code" name="code" class="input" value="{{ old('code', $customer?->code) }}" placeholder="CUS-001" required/></div>
        <div>
            <label for="type">Customer Type</label>
            <select id="type" name="type" class="select">
                <option @selected(old('type', $customer?->type ?? 'Company') === 'Company')>Company</option>
                <option @selected(old('type', $customer?->type) === 'Individual')>Individual</option>
            </select>
        </div>
        <div><label for="vat_number">VAT Number</label><input id="vat_number" name="vat_number" class="input" value="{{ old('vat_number', $customer?->vat_number) }}" placeholder="300XXXXXXXXXXXX"/></div>
        <div><label for="cr_number">CR Number</label><input id="cr_number" name="cr_number" class="input" value="{{ old('cr_number', $customer?->cr_number) }}" placeholder="1010XXXXXX"/></div>
        <div><label for="opening_receivable">Opening Receivable (SAR)</label><input id="opening_receivable" name="opening_receivable" type="number" step="0.01" class="input" value="{{ old('opening_receivable', $customer?->opening_receivable ?? 0) }}"/></div>
        <div><label for="contact_person">Contact Person</label><input id="contact_person" name="contact_person" class="input" value="{{ old('contact_person', $customer?->contact_person) }}"/></div>
        <div><label for="phone">Phone</label><input id="phone" name="phone" class="input" value="{{ old('phone', $customer?->phone) }}" placeholder="+966..."/></div>
        <div><label for="email">Email</label><input id="email" name="email" type="email" class="input" value="{{ old('email', $customer?->email) }}"/></div>
        <div><label for="credit_limit">Credit Limit (SAR)</label><input id="credit_limit" name="credit_limit" type="number" step="0.01" min="0" class="input" value="{{ old('credit_limit', $customer?->credit_limit ?? 0) }}"/></div>
        <div><label for="linked_account">Linked Receivable Account</label><input id="linked_account" name="linked_account" class="input" value="{{ old('linked_account', $customer?->linked_account ?? 'Accounts Receivable - Customers') }}"/></div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                <option value="active" @selected(old('status', $customer?->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $customer?->status) === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="full"><label for="billing_address">Billing Address</label><textarea id="billing_address" name="billing_address" class="textarea" placeholder="Customer billing address for ZATCA invoices...">{{ old('billing_address', $customer?->billing_address) }}</textarea></div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.master.customers.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $customer ? 'Update Customer' : 'Save Customer' }}</button>
    </div>
</form>
