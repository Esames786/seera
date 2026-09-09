@php
    /** @var \App\Models\Supplier|null $supplier */
    $supplier = $supplier ?? null;
    $defaultTerm = $paymentTerms->firstWhere('name', $supplier?->payment_terms ?? 'Cash') ?? $paymentTerms->first();
@endphp

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
            <div class="label-row">
                <label for="payment_term_id">Payment Terms</label>
                <x-admin.quick-create id="qc-payment-term" target="payment_term_id" :url="route('admin.master.payment-terms.store')" title="New Payment Term" permission="Suppliers" submit="Add Term">
                    <div><label for="qc-term-name">Name *</label><input id="qc-term-name" name="name" class="input" placeholder="e.g. 45 Days" required/></div>
                    <div><label for="qc-term-days">Days from bill date *</label><input id="qc-term-days" name="days" type="number" min="0" max="365" class="input" value="30" required/></div>
                    <div class="full"><label for="qc-term-description">Description</label><input id="qc-term-description" name="description" class="input" placeholder="As agreed with the supplier"/></div>
                    <input type="hidden" name="status" value="active"/>
                </x-admin.quick-create>
            </div>
            <select id="payment_term_id" name="payment_term_id" class="select">
                <option value="">Not set</option>
                @foreach ($paymentTerms as $term)
                    <option value="{{ $term->id }}" @selected(old('payment_term_id', $supplier?->payment_term_id ?? $defaultTerm?->id) == $term->id)>{{ $term->label() }}</option>
                @endforeach
            </select>
            <div class="small" style="margin-top:4px">Bills without a due date fall due this many days after the bill date.</div>
        </div>
        <div>
            <div class="label-row">
                <label for="linked_account_id">Linked Payable Account</label>
                <x-admin.quick-create id="qc-payable-account" target="linked_account_id" :url="route('admin.accounting.chart-of-accounts.store')" title="New Payable Sub-Account" permission="Chart of Accounts" submit="Create Account">
                    <div class="full"><label for="qc-acc-name">Account Name *</label><input id="qc-acc-name" name="account_name" class="input" placeholder="e.g. Accounts Payable - Gulf Steel" required/></div>
                    <div><label for="qc-acc-code">Account Code</label><input id="qc-acc-code" name="account_code" class="input" placeholder="Auto: next under {{ $payableControl?->account_code ?? '2100' }}"/></div>
                    <div><label for="qc-acc-opening">Opening Balance (SAR)</label><input id="qc-acc-opening" name="opening_balance" type="number" step="0.01" class="input" value="0" required/></div>
                    <input type="hidden" name="parent_id" value="{{ $payableControl?->id }}"/>
                    <input type="hidden" name="account_type" value="liability"/>
                    <input type="hidden" name="normal_balance" value="credit"/>
                    <input type="hidden" name="status" value="active"/>
                    <div class="full help-box">Created as a liability sub-account under {{ $payableControl?->label() ?? 'Accounts Payable' }}, so the payables total on the dashboard still includes it.</div>
                </x-admin.quick-create>
            </div>
            <select id="linked_account_id" name="linked_account_id" class="select">
                @if ($payableAccounts->isEmpty())
                    <option value="">No payable account in the chart of accounts</option>
                @endif
                @foreach ($payableAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('linked_account_id', $supplier?->linked_account_id ?? $payableControl?->id) == $account->id)>{{ $account->label() }}</option>
                @endforeach
            </select>
            <div class="small" style="margin-top:4px">Bills, payments and goods receipts for this supplier post to this account.</div>
        </div>
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
