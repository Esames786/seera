@php
    /** @var \App\Models\Supplier|null $supplier */
    $supplier = $supplier ?? null;
    $workspace = $workspace ?? false;   // inside the workspace, projects are managed in their own panel
    $defaultTerm = $paymentTerms->firstWhere('name', $supplier?->payment_terms ?? 'Cash') ?? $paymentTerms->first();
    $selectedProjects = collect(old('project_ids', $supplier?->projects->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
@endphp

<form method="POST" action="{{ $supplier ? route('admin.master.suppliers.update', $supplier) : route('admin.master.suppliers.store') }}" id="profile">
    @csrf
    @if ($supplier) @method('PUT') @endif

    <x-admin.form-section title="Supplier Information" columns="3">
        <div><label for="name">{{ __('Supplier Name *') }}</label><input id="name" name="name" class="input" value="{{ old('name', $supplier?->name) }}" required/></div>
        <div><label for="code">{{ __('Supplier Code') }}</label><input id="code" name="code" class="input" value="{{ old('code', $supplier?->code) }}" placeholder="{{ __('Auto: SUP-001') }}"/></div>
        <div>
            <div class="label-row">
                <label for="category">{{ __('Supplier Category') }}</label>
                <x-admin.quick-create id="qc-supplier-category" target="category" :url="route('admin.master.lookup-values.store')" title="New Supplier Category" permission="Suppliers" submit="Add Category">
                    <div class="full"><label for="qc-cat-value">{{ __('Category Name *') }}</label><input id="qc-cat-value" name="value" class="input" placeholder="{{ __('e.g. Scaffolding, Transport') }}" required/></div>
                    <input type="hidden" name="type" value="supplier_category"/>
                </x-admin.quick-create>
            </div>
            <select id="category" name="category" class="select">
                <option value="">{{ __('Select...') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(old('category', $supplier?->category) === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="city">{{ __('City / Location') }}</label><input id="city" name="city" class="input" value="{{ old('city', $supplier?->city) }}" placeholder="{{ __('Riyadh, Jeddah, Dammam...') }}"/></div>
        <x-admin.rating-picker label="Supplier Rating" :value="$supplier?->rating"/>
        <div><label for="vat_number">{{ __('VAT Number') }}</label><input id="vat_number" name="vat_number" class="input" value="{{ old('vat_number', $supplier?->vat_number) }}" placeholder="{{ __('300XXXXXXXXXXXX') }}"/></div>
        <div><label for="cr_number">{{ __('CR Number') }}</label><input id="cr_number" name="cr_number" class="input" value="{{ old('cr_number', $supplier?->cr_number) }}" placeholder="{{ __('1010XXXXXX') }}"/></div>
        <div><label for="opening_balance">{{ __('Opening Balance (SAR)') }}</label><input id="opening_balance" name="opening_balance" type="number" step="0.01" class="input" value="{{ old('opening_balance', $supplier?->opening_balance ?? 0) }}"/></div>
        <div><label for="contact_person">{{ __('Contact Person') }}</label><input id="contact_person" name="contact_person" class="input" value="{{ old('contact_person', $supplier?->contact_person) }}"/></div>
        <div><label for="phone">{{ __('Phone') }}</label><input id="phone" name="phone" class="input" value="{{ old('phone', $supplier?->phone) }}" placeholder="{{ __('+966...') }}"/></div>
        <div><label for="email">{{ __('Email') }}</label><input id="email" name="email" type="email" class="input" value="{{ old('email', $supplier?->email) }}"/></div>
        <div>
            <label for="status">{{ __('Status *') }}</label>
            <select id="status" name="status" class="select" required>
                <option value="active" @selected(old('status', $supplier?->status ?? 'active') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(old('status', $supplier?->status) === 'inactive')>{{ __('Inactive') }}</option>
            </select>
        </div>
        <div class="full"><label for="address">{{ __('Address') }}</label><textarea id="address" name="address" class="textarea" placeholder="{{ __('Supplier address...') }}">{{ old('address', $supplier?->address) }}</textarea></div>
    </x-admin.form-section>

    <x-admin.form-section title="Payment & Banking" columns="3">
        <div>
            <div class="label-row">
                <label for="payment_term_id">{{ __('Payment Terms') }}</label>
                <x-admin.quick-create id="qc-payment-term" target="payment_term_id" :url="route('admin.master.payment-terms.store')" title="New Payment Term" permission="Suppliers" submit="Add Term">
                    <div><label for="qc-term-name">{{ __('Name *') }}</label><input id="qc-term-name" name="name" class="input" placeholder="{{ __('e.g. 45 Days') }}" required/></div>
                    <div><label for="qc-term-days">{{ __('Days from bill date *') }}</label><input id="qc-term-days" name="days" type="number" min="0" max="365" class="input" value="30" required/></div>
                    <div class="full"><label for="qc-term-description">{{ __('Description') }}</label><input id="qc-term-description" name="description" class="input" placeholder="{{ __('As agreed with the supplier') }}"/></div>
                    <input type="hidden" name="status" value="active"/>
                </x-admin.quick-create>
            </div>
            <select id="payment_term_id" name="payment_term_id" class="select">
                <option value="">{{ __('Not set') }}</option>
                @foreach ($paymentTerms as $term)
                    <option value="{{ $term->id }}" @selected(old('payment_term_id', $supplier?->payment_term_id ?? $defaultTerm?->id) == $term->id)>{{ $term->label() }}</option>
                @endforeach
            </select>
            <div class="small" style="margin-top:4px">{{ __('Bills without a due date fall due this many days after the bill date.') }}</div>
        </div>
        <div>
            <label for="allowed_payment_types">{{ __('Accepted Payment Types') }}</label>
            <select id="allowed_payment_types" name="allowed_payment_types" class="select">
                @foreach ($paymentTypes as $type)
                    <option value="{{ $type }}" @selected(old('allowed_payment_types', $supplier?->allowed_payment_types ?? 'Both') === $type)>{{ __($type) }}</option>
                @endforeach
            </select>
            <div class="small" style="margin-top:4px">{{ __('Limits the cash / bank accounts offered when recording a payment to this supplier.') }}</div>
        </div>
        <div>
            <div class="label-row">
                <label for="linked_account_id">{{ __('Linked Payable Account') }}</label>
                <x-admin.quick-create id="qc-payable-account" target="linked_account_id" :url="route('admin.accounting.chart-of-accounts.store')" title="New Payable Sub-Account" permission="Chart of Accounts" submit="Create Account">
                    <div class="full"><label for="qc-acc-name">{{ __('Account Name *') }}</label><input id="qc-acc-name" name="account_name" class="input" placeholder="{{ __('e.g. Accounts Payable - Gulf Steel') }}" required/></div>
                    <div><label for="qc-acc-code">{{ __('Account Code') }}</label><input id="qc-acc-code" name="account_code" class="input" placeholder="{{ __('Auto: next under') }} {{ $payableControl?->account_code ?? '2100' }}"/></div>
                    <div><label for="qc-acc-opening">{{ __('Opening Balance (SAR)') }}</label><input id="qc-acc-opening" name="opening_balance" type="number" step="0.01" class="input" value="0" required/></div>
                    <input type="hidden" name="parent_id" value="{{ $payableControl?->id }}"/>
                    <input type="hidden" name="account_type" value="liability"/>
                    <input type="hidden" name="normal_balance" value="credit"/>
                    <input type="hidden" name="status" value="active"/>
                    <div class="full help-box">{{ __('Created as a liability sub-account under') }} {{ $payableControl?->label() ?? 'Accounts Payable' }}{{ __(', so the payables total on the dashboard still includes it.') }}</div>
                </x-admin.quick-create>
            </div>
            <select id="linked_account_id" name="linked_account_id" class="select">
                @if ($payableAccounts->isEmpty())
                    <option value="">{{ __('No payable account in the chart of accounts') }}</option>
                @endif
                @foreach ($payableAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('linked_account_id', $supplier?->linked_account_id ?? $payableControl?->id) == $account->id)>{{ $account->label() }}</option>
                @endforeach
            </select>
            <div class="small" style="margin-top:4px">{{ __('Bills, payments and goods receipts for this supplier post to this account.') }}</div>
        </div>
        <div><label for="bank_name">{{ __('Bank Name') }}</label><input id="bank_name" name="bank_name" class="input" value="{{ old('bank_name', $supplier?->bank_name) }}" placeholder="{{ __('Al Rajhi Bank') }}"/></div>
        <div><label for="bank_account_name">{{ __('Account Holder Name') }}</label><input id="bank_account_name" name="bank_account_name" class="input" value="{{ old('bank_account_name', $supplier?->bank_account_name) }}"/></div>
        <div><label for="iban">{{ __('IBAN') }}</label><input id="iban" name="iban" class="input" value="{{ old('iban', $supplier?->iban) }}" placeholder="{{ __('SA00 0000 0000 0000 0000 0000') }}"/></div>
    </x-admin.form-section>

    @unless ($workspace)
        <x-admin.form-section title="Projects this Supplier Works For">
            <input type="hidden" name="project_ids_submitted" value="1"/>
            @if ($projects->isEmpty())
                <div class="small">{{ __('No projects yet. Suppliers can be linked once projects exist.') }}</div>
            @else
                <div class="form-grid three">
                    @foreach ($projects as $project)
                        <label class="check-line" style="margin:0">
                            <input class="checkbox" type="checkbox" name="project_ids[]" value="{{ $project->id }}" @checked($selectedProjects->contains($project->id))/>
                            {{ $project->code }} — {{ $project->name }}
                        </label>
                    @endforeach
                </div>
                <div class="small" style="margin-top:10px">{{ __("Linked suppliers are listed on each project's page.") }}</div>
            @endif
        </x-admin.form-section>
    @endunless

    <x-admin.form-actions :cancel="route('admin.master.suppliers.index')" :save-new="true"/>
</form>
