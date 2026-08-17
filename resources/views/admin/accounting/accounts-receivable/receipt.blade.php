@extends('layouts.admin')

@section('title', 'Record Customer Receipt')
@section('breadcrumb', 'Accounting / Accounts Receivable / Record Receipt')

@section('content')
    <x-admin.page-header :title="'Record Receipt: '.$invoice->invoice_number" :description="$invoice->customer->name.' · outstanding SAR '.number_format($invoice->balance_amount, 2)">
        <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.show', $invoice) }}">Back to Invoice</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($invoice->total_amount, 2)" label="Invoice Total"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($invoice->received_amount, 2)" label="Already Received"/>
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($invoice->balance_amount, 2)" label="Outstanding"/>
        <x-admin.metric-card color="yellow" :value="$invoice->receipts->count()" label="Receipts Recorded"/>
    </div>

    <form method="POST" action="{{ route('admin.accounting.accounts-receivable.receipt.store', $invoice) }}">
        @csrf

        <x-admin.form-section title="Receipt Details" columns="3">
            <div><label for="receipt_date">Receipt Date *</label><input id="receipt_date" name="receipt_date" type="date" class="input" value="{{ old('receipt_date', now()->toDateString()) }}" required/></div>
            <div>
                <label for="receipt_account_id">Bank / Cash Account *</label>
                <select id="receipt_account_id" name="receipt_account_id" class="select" required>
                    <option value="">Select cash or bank...</option>
                    @foreach ($receiptAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('receipt_account_id') == $account->id)>{{ $account->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div><label for="amount">Received Amount (SAR) *</label><input id="amount" name="amount" type="number" step="0.01" min="0.01" max="{{ $invoice->balance_amount }}" class="input" value="{{ old('amount', $invoice->balance_amount) }}" required/></div>
            <div><label for="reference_number">Reference Number</label><input id="reference_number" name="reference_number" class="input" value="{{ old('reference_number') }}" placeholder="RCPT-0705"/></div>
            <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes') }}</textarea></div>
        </x-admin.form-section>

        <div class="note">
            This receipt posts: debit the selected cash/bank account, credit accounts receivable.
        </div>

        <div class="form-actions">
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.show', $invoice) }}">Cancel</a>
            <button type="submit" class="btn primary">Record Receipt</button>
        </div>
    </form>
@endsection
