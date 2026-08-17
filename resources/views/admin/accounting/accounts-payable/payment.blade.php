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
        <x-admin.metric-card color="blue" :value="$bill->payments->count()" label="Payments Made"/>
    </div>

    <form method="POST" action="{{ route('admin.accounting.accounts-payable.payment.store', $bill) }}">
        @csrf

        <x-admin.form-section title="Payment Details" columns="3">
            <div><label for="payment_date">Payment Date *</label><input id="payment_date" name="payment_date" type="date" class="input" value="{{ old('payment_date', now()->toDateString()) }}" required/></div>
            <div>
                <label for="payment_account_id">Payment Account *</label>
                <select id="payment_account_id" name="payment_account_id" class="select" required>
                    <option value="">Select cash or bank...</option>
                    @foreach ($paymentAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('payment_account_id') == $account->id)>{{ $account->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div><label for="amount">Payment Amount (SAR) *</label><input id="amount" name="amount" type="number" step="0.01" min="0.01" max="{{ $bill->balance_amount }}" class="input" value="{{ old('amount', $bill->balance_amount) }}" required/></div>
            <div><label for="reference_number">Reference Number</label><input id="reference_number" name="reference_number" class="input" value="{{ old('reference_number') }}" placeholder="PAY-0505"/></div>
            <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes') }}</textarea></div>
        </x-admin.form-section>

        <div class="note">
            This payment posts: debit accounts payable SAR {{ number_format($bill->balance_amount, 2) }}, credit the selected cash/bank account.
        </div>

        <div class="form-actions">
            <a class="btn outline" href="{{ route('admin.accounting.accounts-payable.show', $bill) }}">Cancel</a>
            <button type="submit" class="btn primary">Record Payment</button>
        </div>
    </form>
@endsection
