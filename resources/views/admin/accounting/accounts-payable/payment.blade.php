@extends('layouts.admin')

@section('title', 'Record Supplier Payment')
@section('breadcrumb', 'Accounting / Accounts Payable / Record Payment')

@section('content')
    <x-admin.page-header :title="'Record Payment: '.$bill->bill_number" :description="$bill->supplier->name.' · outstanding SAR '.number_format($bill->balance_amount, 2)">
        <a class="btn outline" href="{{ route('admin.accounting.accounts-payable.show', $bill) }}">Back to Bill</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($bill->total_amount, 2)" label="Bill Total"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($bill->paid_amount, 2)" label="Already Paid"/>
        <x-admin.metric-card color="red" :value="'SAR '.number_format($bill->balance_amount, 2)" label="Outstanding"/>
        <x-admin.metric-card color="blue" :value="$bill->supplier->allowed_payment_types ?? 'Both'" label="Supplier Accepts"/>
    </div>

    @if ($paymentAccounts->isEmpty())
        <div class="alert flash">
            <strong>No payment account available.</strong>
            @if (($bill->supplier->allowed_payment_types ?? 'Both') === 'Both')
                There is no active Cash in Hand (1110) or Bank Account (1120) in the chart of accounts.
            @else
                This supplier accepts {{ strtolower($bill->supplier->allowed_payment_types) }} payments only and there is no active {{ strtolower($bill->supplier->allowed_payment_types) }} account ({{ $bill->supplier->allowed_payment_types === 'Cash' ? '1110' : '1120' }}) in the chart of accounts.
            @endif
            Create or activate it under
            <a href="{{ route('admin.accounting.chart-of-accounts.index') }}" style="font-weight:700">Chart of Accounts</a>,
            or change the supplier's accepted payment types on the
            <a href="{{ route('admin.master.suppliers.edit', $bill->supplier) }}" style="font-weight:700">supplier form</a>.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.accounting.accounts-payable.payment.store', $bill) }}">
        @csrf
        {{-- One-time key so a double click or a retry cannot record this payment twice (F02). --}}
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}"/>

        <x-admin.form-section title="Payment Details" columns="3">
            <div><label for="payment_date">Payment Date *</label><input id="payment_date" name="payment_date" type="date" class="input" max="{{ now()->toDateString() }}" value="{{ old('payment_date', now()->toDateString()) }}" required/></div>
            <div>
                <label for="payment_account_id">Paid From (Cash / Bank Account) *</label>
                <select id="payment_account_id" name="payment_account_id" class="select" required @disabled($paymentAccounts->isEmpty())>
                    <option value="">Select cash or bank...</option>
                    @foreach ($paymentAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('payment_account_id', $paymentAccounts->count() === 1 ? $account->id : null) == $account->id)>{{ $account->label() }}</option>
                    @endforeach
                </select>
                @error('payment_account_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="payment_method">Payment Method *</label>
                <select id="payment_method" name="payment_method" class="select" required>
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method }}" @selected(old('payment_method', ($bill->supplier->allowed_payment_types ?? 'Both') === 'Cash' ? 'Cash' : 'Bank Transfer') === $method)>{{ $method }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="purpose">Purpose *</label>
                <select id="purpose" name="purpose" class="select" required>
                    @foreach ($purposes as $purpose)
                        <option value="{{ $purpose }}" @selected(old('purpose', 'Bill payment') === $purpose)>{{ $purpose }}</option>
                    @endforeach
                </select>
                <div class="small" style="margin-top:4px">Recorded on the payment and the bill for traceability; the ledger treatment is the same.</div>
            </div>
            <div><label for="amount">Payment Amount (SAR) *</label><input id="amount" name="amount" type="number" step="0.01" min="0.01" max="{{ $bill->balance_amount }}" class="input" value="{{ old('amount', $bill->balance_amount) }}" required/></div>
            <div><label for="reference_number">Reference Number</label><input id="reference_number" name="reference_number" class="input" value="{{ old('reference_number') }}" placeholder="Transfer / cheque number"/></div>
            <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes') }}</textarea></div>
        </x-admin.form-section>

        <div class="note">
            This payment posts: debit {{ $bill->supplier->linkedAccount?->label() ?? 'accounts payable' }} SAR {{ number_format($bill->balance_amount, 2) }}, credit the selected cash/bank account.
            @if ($bill->supplier->bank_name || $bill->supplier->iban)
                Supplier bank: {{ $bill->supplier->bank_name ?? '-' }} {{ $bill->supplier->iban ? '· IBAN '.$bill->supplier->iban : '' }}
            @endif
        </div>

        <div class="form-actions">
            <a class="btn outline" href="{{ route('admin.accounting.accounts-payable.show', $bill) }}">Cancel</a>
            <button type="submit" class="btn primary" @disabled($paymentAccounts->isEmpty())>Record Payment</button>
        </div>
    </form>
@endsection
