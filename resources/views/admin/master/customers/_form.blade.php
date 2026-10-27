@php /** @var \App\Models\Customer|null $customer */ $customer = $customer ?? null; @endphp

<form method="POST" action="{{ $customer ? route('admin.master.customers.update', $customer) : route('admin.master.customers.store') }}">
    @csrf
    @if ($customer) @method('PUT') @endif

    <x-admin.form-section :title="__('Customer Information')" columns="3">
        <div><label for="name">{{ __('Customer Name') }} *</label><input id="name" name="name" class="input" value="{{ old('name', $customer?->name) }}" required/></div>
        <div><label for="code">{{ __('Customer Code') }}</label><input id="code" name="code" class="input" value="{{ old('code', $customer?->code) }}" placeholder="Auto: CUS-001"/></div>
        <div>
            <div class="label-row">
                <label for="type">{{ __('ui.customer_type') }}</label>
                <x-admin.quick-create id="qc-customer-type" target="type" :url="route('admin.master.lookup-values.store')" :title="__('ui.new_type')" permission="Customers">
                    <input type="hidden" name="type" value="customer_type"/>
                    <div><label for="qc-customer-type-value">{{ __('ui.type_name') }}</label><input id="qc-customer-type-value" name="value" class="input" maxlength="100" required/></div>
                </x-admin.quick-create>
            </div>
            <select id="type" name="type" class="select">
                @foreach ($customerTypes as $type)
                    <option value="{{ $type }}" @selected(old('type', $customer?->type ?? 'Company') === $type)>{{ in_array($type, ['Company', 'Individual'], true) ? __($type) : $type }}</option>
                @endforeach
            </select>
        </div>
        <x-admin.rating-picker label="Customer Rating" :value="$customer?->rating"/>
        <div>
            <label for="allowed_payment_types">{{ __('ui.payment_types') }}</label>
            <select id="allowed_payment_types" name="allowed_payment_types" class="select">
                @foreach (\App\Models\Customer::PAYMENT_TYPES as $paymentType)
                    <option value="{{ $paymentType }}" @selected(old('allowed_payment_types', $customer?->allowed_payment_types ?? 'Both') === $paymentType)>{{ __('ui.'.strtolower($paymentType)) }}</option>
                @endforeach
            </select>
            <div class="small">{{ __('ui.payment_help') }}</div>
        </div>
        <div><label for="vat_number">{{ __('VAT Number') }}</label><input id="vat_number" name="vat_number" class="input" value="{{ old('vat_number', $customer?->vat_number) }}" placeholder="300XXXXXXXXXXXX"/></div>
        <div><label for="cr_number">{{ __('CR Number') }}</label><input id="cr_number" name="cr_number" class="input" value="{{ old('cr_number', $customer?->cr_number) }}" placeholder="1010XXXXXX"/></div>
        <div><label for="opening_receivable">{{ __('Opening Receivable (SAR)') }}</label><input id="opening_receivable" name="opening_receivable" type="number" step="0.01" class="input" value="{{ old('opening_receivable', $customer?->opening_receivable ?? 0) }}"/></div>
        <div><label for="contact_person">{{ __('Contact Person') }}</label><input id="contact_person" name="contact_person" class="input" value="{{ old('contact_person', $customer?->contact_person) }}"/></div>
        <div><label for="phone">{{ __('Phone') }}</label><input id="phone" name="phone" class="input" value="{{ old('phone', $customer?->phone) }}" placeholder="+966..."/></div>
        <div><label for="email">{{ __('Email') }}</label><input id="email" name="email" type="email" class="input" value="{{ old('email', $customer?->email) }}"/></div>
        <div><label for="credit_limit">{{ __('Credit Limit (SAR)') }}</label><input id="credit_limit" name="credit_limit" type="number" step="0.01" min="0" class="input" value="{{ old('credit_limit', $customer?->credit_limit ?? 0) }}"/></div>
        <div><label for="linked_account">{{ __('Linked Receivable Account') }}</label><input id="linked_account" name="linked_account" class="input" value="{{ old('linked_account', $customer?->linked_account ?? 'Accounts Receivable - Customers') }}"/></div>
        <div>
            <label for="status">{{ __('Status') }} *</label>
            <select id="status" name="status" class="select" required>
                <option value="active" @selected(old('status', $customer?->status ?? 'active') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(old('status', $customer?->status) === 'inactive')>{{ __('Inactive') }}</option>
            </select>
        </div>
        <div><label for="buyer_id_scheme">{{ __('Buyer ID type (if no VAT number)') }}</label><select id="buyer_id_scheme" name="buyer_id_scheme" class="select"><option value="">{{ __('None') }}</option>@foreach (\App\Models\Customer::BUYER_ID_SCHEMES as $scheme)<option value="{{ $scheme }}" @selected(old('buyer_id_scheme', $customer?->buyer_id_scheme) === $scheme)>{{ $scheme }}</option>@endforeach</select></div>
        <div><label for="buyer_id_value">{{ __('Buyer ID value') }}</label><input id="buyer_id_value" name="buyer_id_value" class="input" maxlength="60" value="{{ old('buyer_id_value', $customer?->buyer_id_value) }}" placeholder="{{ __('National ID / Iqama / CRN') }}"/></div>
        <div><label for="street">{{ __('Street (national address)') }}</label><input id="street" name="street" class="input" maxlength="191" value="{{ old('street', $customer?->street) }}"/></div>
        <div><label for="building_number">{{ __('Building number (4 digits)') }}</label><input id="building_number" name="building_number" class="input" maxlength="10" value="{{ old('building_number', $customer?->building_number) }}"/></div>
        <div><label for="district">{{ __('District') }}</label><input id="district" name="district" class="input" maxlength="191" value="{{ old('district', $customer?->district) }}"/></div>
        <div><label for="city">{{ __('City') }}</label><input id="city" name="city" class="input" maxlength="100" value="{{ old('city', $customer?->city) }}"/></div>
        <div><label for="postal_code">{{ __('Postal code (5 digits)') }}</label><input id="postal_code" name="postal_code" class="input" maxlength="10" value="{{ old('postal_code', $customer?->postal_code) }}"/></div>
        <div><label for="country_code">{{ __('Country code') }}</label><input id="country_code" name="country_code" class="input" maxlength="2" value="{{ old('country_code', $customer?->country_code ?? 'SA') }}"/></div>
        <div class="full"><label for="billing_address">{{ __('Billing Address') }}</label><textarea id="billing_address" name="billing_address" class="textarea" placeholder="{{ __('Customer billing address for ZATCA invoices...') }}">{{ old('billing_address', $customer?->billing_address) }}</textarea></div>
    </x-admin.form-section>

    @unless ($workspace ?? false)
        {{-- Inside the workspace, contacts and notes have their own panels. --}}
        @include('admin.master.customers._related-inputs')
    @endunless

    <x-admin.form-actions :cancel="route('admin.master.customers.index')" :save-new="true"/>
</form>
