@php
    /** @var \App\Models\CustomerInvoice|null $invoice */
    $invoice = $invoice ?? null;
    $lineData = old('lines', $invoice?->lines->map(fn ($line) => [
        'description' => $line->description,
        'quantity' => (float) $line->quantity,
        'unit_price' => (float) $line->unit_price,
        'revenue_account_id' => $line->revenue_account_id,
        'cost_center_id' => $line->cost_center_id,
    ])->all() ?? []);
    $rows = max(count($lineData), 3);
@endphp

<form method="POST" action="{{ $invoice ? route('admin.accounting.accounts-receivable.update', $invoice) : route('admin.accounting.accounts-receivable.store') }}">
    @csrf
    @if ($invoice) @method('PUT') @endif

    <x-admin.form-section title="Invoice Information" columns="3">
        <div>
            <label for="customer_id">{{ __('Customer *') }}</label>
            <select id="customer_id" name="customer_id" class="select" required>
                <option value="">{{ __('Select...') }}</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(old('customer_id', $invoice?->customer_id) == $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="invoice_number">{{ __('Invoice Number') }}</label><input id="invoice_number" name="invoice_number" class="input" value="{{ old('invoice_number', $invoice?->invoice_number) }}" placeholder="{{ __('Auto-generated if left blank') }}"/></div>
        @php $canOverrideVat = (bool) (auth()->user()?->isSuperAdmin() ?? false); @endphp
        <div>
            <label for="vat_rate">{{ __('VAT Rate (%)') }} *</label>
            <input id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" class="input" value="{{ old('vat_rate', $invoice?->vat_rate ?? \App\Services\Accounting\SalesVatPolicy::STANDARD_RATE) }}" required @readonly(! $canOverrideVat)/>
            <div class="small">{{ $canOverrideVat ? __('Standard rate 15%. A different rate is a manual override and needs a reason; it is logged.') : __('Standard rate 15%. Only a Super Admin can override it.') }}</div>
            @error('vat_rate')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        @if ($canOverrideVat)
            <div class="full"><label for="vat_override_reason">{{ __('VAT override reason (Super Admin, required for a nonstandard rate)') }}</label><input id="vat_override_reason" name="vat_override_reason" class="input" maxlength="500" value="{{ old('vat_override_reason') }}"/>@error('vat_override_reason')<div class="field-error">{{ $message }}</div>@enderror</div>
        @endif
        <div><label for="invoice_date">{{ __('Invoice Date *') }}</label><input id="invoice_date" name="invoice_date" type="date" class="input" value="{{ old('invoice_date', $invoice?->invoice_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div><label for="due_date">{{ __('Due Date') }}</label><input id="due_date" name="due_date" type="date" class="input" value="{{ old('due_date', $invoice?->due_date?->toDateString() ?? now()->addDays(30)->toDateString()) }}"/></div>
        <div><label for="invoice_type">{{ __('zatca.invoice_type') }} *</label><select id="invoice_type" name="invoice_type" class="select" required>@foreach (\App\Models\CustomerInvoice::TYPES as $type)<option value="{{ $type }}" @selected(old('invoice_type', $invoice?->invoice_type ?? 'standard') === $type)>{{ __('zatca.type_'.$type) }}</option>@endforeach</select></div>
        <div><label for="supply_date">{{ __('zatca.supply_date') }}</label><input id="supply_date" name="supply_date" type="date" class="input" value="{{ old('supply_date', $invoice?->supply_date?->toDateString() ?? $invoice?->invoice_date?->toDateString() ?? now()->toDateString()) }}"/><small>{{ __('Required for a standard tax invoice (KSA-5).') }}</small></div>
        <div>
            <label for="project_id">{{ __('Project') }}</label>
            <select id="project_id" name="project_id" class="select">
                <option value="">{{ __('No project') }}</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $invoice?->project_id) == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="cost_center_id">{{ __('Cost Center') }}</label>
            <select id="cost_center_id" name="cost_center_id" class="select">
                <option value="">{{ __('No cost center') }}</option>
                @foreach ($costCenters as $costCenter)
                    <option value="{{ $costCenter->id }}" @selected(old('cost_center_id', $invoice?->cost_center_id) == $costCenter->id)>{{ $costCenter->code }} - {{ $costCenter->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="notes">{{ __('Notes') }}</label><textarea id="notes" name="notes" class="textarea">{{ old('notes', $invoice?->notes) }}</textarea></div>
    </x-admin.form-section>

    <x-admin.form-section title="Invoice Lines">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="min-width:240px">{{ __('Item / Description *') }}</th>
                        <th style="width:110px">{{ __('Qty') }}</th>
                        <th style="width:150px">{{ __('Unit Price') }}</th>
                        <th style="min-width:200px">{{ __('Revenue Account') }}</th>
                        <th style="min-width:150px">{{ __('Cost Center') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $rows; $i++)
                        @php $line = $lineData[$i] ?? []; @endphp
                        <tr>
                            <td><input name="lines[{{ $i }}][description]" class="input" value="{{ $line['description'] ?? '' }}"/></td>
                            <td><input name="lines[{{ $i }}][quantity]" type="number" step="0.01" min="0" class="input" value="{{ $line['quantity'] ?? 1 }}"/></td>
                            <td><input name="lines[{{ $i }}][unit_price]" type="number" step="0.01" min="0" class="input" value="{{ $line['unit_price'] ?? '' }}"/></td>
                            <td>
                                <select name="lines[{{ $i }}][revenue_account_id]" class="select" title="{{ __('The revenue account this line is credited to. Leave on the default for project work; pick Service Revenue for services.') }}">
                                    <option value="">{{ __('Default:') }} {{ $defaultRevenueAccount?->label() ?? '4100 - Project Revenue' }}</option>
                                    @foreach ($revenueAccounts as $account)
                                        <option value="{{ $account->id }}" @selected(($line['revenue_account_id'] ?? null) == $account->id)>{{ $account->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $i }}][cost_center_id]" class="select">
                                    <option value="">-</option>
                                    @foreach ($costCenters as $costCenter)
                                        <option value="{{ $costCenter->id }}" @selected(($line['cost_center_id'] ?? null) == $costCenter->id)>{{ $costCenter->code }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        <div class="small" style="margin-top:10px">
            {{ __('Taxable amount, VAT and total are calculated from quantity × unit price and the invoice VAT rate. Rows without a description or unit price are ignored.') }}
        </div>
    </x-admin.form-section>

    {{-- Save keeps the draft open on its detail page (Approve is a separate business action there). --}}
    <x-admin.form-actions :cancel="route('admin.accounting.accounts-receivable.index')" :save-new="true"/>
</form>
