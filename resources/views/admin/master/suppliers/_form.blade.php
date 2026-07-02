@php /** @var \App\Models\Supplier|null $supplier */ $supplier = $supplier ?? null; @endphp

<form method="POST" action="{{ $supplier ? route('admin.master.suppliers.update', $supplier) : route('admin.master.suppliers.store') }}">
    @csrf
    @if ($supplier) @method('PUT') @endif

    <x-admin.form-section title="Supplier Information" columns="3">
        <div><label for="name">Supplier Name *</label><input id="name" name="name" class="input" value="{{ old('name', $supplier?->name) }}" required/></div>
        <div><label for="code">Supplier Code *</label><input id="code" name="code" class="input" value="{{ old('code', $supplier?->code) }}" placeholder="SUP-001" required/></div>
        <div>
            <label for="category">Supplier Category</label>
            <select id="category" name="category" class="select">
                <option value="">Select...</option>
                @foreach (['Materials', 'Fuel', 'Equipment', 'Services', 'Subcontractor'] as $category)
                    <option @selected(old('category', $supplier?->category) === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="vat_number">VAT Number</label><input id="vat_number" name="vat_number" class="input" value="{{ old('vat_number', $supplier?->vat_number) }}" placeholder="300XXXXXXXXXXXX"/></div>
        <div><label for="cr_number">CR Number</label><input id="cr_number" name="cr_number" class="input" value="{{ old('cr_number', $supplier?->cr_number) }}" placeholder="1010XXXXXX"/></div>
        <div><label for="opening_balance">Opening Balance (SAR)</label><input id="opening_balance" name="opening_balance" type="number" step="0.01" class="input" value="{{ old('opening_balance', $supplier?->opening_balance ?? 0) }}"/></div>
        <div><label for="contact_person">Contact Person</label><input id="contact_person" name="contact_person" class="input" value="{{ old('contact_person', $supplier?->contact_person) }}"/></div>
        <div><label for="phone">Phone</label><input id="phone" name="phone" class="input" value="{{ old('phone', $supplier?->phone) }}" placeholder="+966..."/></div>
        <div><label for="email">Email</label><input id="email" name="email" type="email" class="input" value="{{ old('email', $supplier?->email) }}"/></div>
        <div>
            <label for="payment_terms">Payment Terms</label>
            <select id="payment_terms" name="payment_terms" class="select">
                @foreach (['Cash', '15 Days', '30 Days', '60 Days'] as $terms)
                    <option @selected(old('payment_terms', $supplier?->payment_terms ?? 'Cash') === $terms)>{{ $terms }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="linked_account">Linked Payable Account</label><input id="linked_account" name="linked_account" class="input" value="{{ old('linked_account', $supplier?->linked_account ?? 'Accounts Payable - Suppliers') }}"/></div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                <option value="active" @selected(old('status', $supplier?->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $supplier?->status) === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="full"><label for="address">Address</label><textarea id="address" name="address" class="textarea" placeholder="Supplier address...">{{ old('address', $supplier?->address) }}</textarea></div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.master.suppliers.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $supplier ? 'Update Supplier' : 'Save Supplier' }}</button>
    </div>
</form>
