@extends('layouts.admin')

@section('title', 'Supplier Bill')
@section('breadcrumb', 'Accounting / Accounts Payable / Supplier Bill')

@section('content')
    <x-admin.page-header :title="'Bill: '.$bill->bill_number" :description="$bill->supplier->name.' · '.$bill->bill_date->toDateString()">
        @if ($bill->isEditable())
            <a class="btn outline" href="{{ route('admin.accounting.accounts-payable.edit', $bill) }}">Edit</a>
        @endif
        @if ($bill->status === 'draft')
            <form method="POST" action="{{ route('admin.accounting.accounts-payable.approve', $bill) }}">
                @csrf
                <button type="submit" class="btn primary">Approve &amp; Post</button>
            </form>
        @elseif (in_array($bill->status, ['unpaid', 'partially_paid']))
            <a class="btn primary" href="{{ route('admin.accounting.accounts-payable.payment', $bill) }}">Record Payment</a>
        @endif
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($bill->taxable_amount, 2)" label="Taxable Amount"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($bill->vat_amount, 2)" label="Input VAT"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($bill->total_amount, 2)" label="Total Amount"/>
        <x-admin.metric-card color="red" :value="'SAR '.number_format($bill->balance_amount, 2)" label="Outstanding Balance"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Bill Information" class="detail-table">
            <tbody>
                <tr><th>Supplier</th><td>{{ $bill->supplier->name }}</td></tr>
                <tr><th>Bill Number</th><td>{{ $bill->bill_number }}</td></tr>
                <tr><th>Reference</th><td>{{ $bill->reference_number ?? '-' }}</td></tr>
                <tr><th>Bill Date</th><td>{{ $bill->bill_date->toDateString() }}</td></tr>
                <tr><th>Due Date</th><td>{{ $bill->due_date?->toDateString() ?? '-' }}</td></tr>
                <tr><th>Project / Site</th><td>{{ $bill->project?->name ?? '-' }}@if($bill->site) / {{ $bill->site->name }}@endif</td></tr>
                <tr><th>Cost Center</th><td>{{ $bill->costCenter ? $bill->costCenter->code.' - '.$bill->costCenter->name : '-' }}</td></tr>
                <tr><th>VAT Rate</th><td>{{ number_format($bill->vat_rate, 2) }}%</td></tr>
                <tr><th>Paid Amount</th><td>SAR {{ number_format($bill->paid_amount, 2) }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$bill->status"/></td></tr>
                <tr><th>Notes</th><td>{{ $bill->notes ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <x-admin.data-table title="Accounting Entry">
                @if ($bill->journalEntry)
                    <thead>
                        <tr><th>Account</th><th>Debit</th><th>Credit</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($bill->journalEntry->lines as $line)
                            <tr>
                                <td>{{ $line->account->label() }}</td>
                                <td>{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                                <td>{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <x-slot:footer>
                        <span class="small">Journal</span>
                        <a class="btn sm primary" href="{{ route('admin.accounting.journal-entries.show', $bill->journalEntry) }}">{{ $bill->journalEntry->journal_number }}</a>
                    </x-slot:footer>
                @else
                    <tbody>
                        <tr><td class="table-empty">No accounting entry yet. Approve the bill to post it.</td></tr>
                    </tbody>
                @endif
            </x-admin.data-table>

            <x-admin.data-table title="Payments">
                <thead>
                    <tr><th>Date</th><th>Account</th><th>Amount</th><th>Reference</th></tr>
                </thead>
                <tbody>
                    @forelse ($bill->payments as $payment)
                        <tr>
                            <td>{{ $payment->payment_date->toDateString() }}</td>
                            <td>{{ $payment->paymentAccount?->label() ?? '-' }}</td>
                            <td>SAR {{ number_format($payment->amount, 2) }}</td>
                            <td>{{ $payment->reference_number ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">No payments recorded against this bill.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>
    </div>

    <x-admin.data-table title="Bill Lines">
        <thead>
            <tr><th>Description</th><th>Category</th><th>Account</th><th>Qty</th><th>Unit Price</th><th>Taxable</th><th>VAT</th><th>Total</th></tr>
        </thead>
        <tbody>
            @forelse ($bill->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->expenseCategory?->name ?? '-' }}</td>
                    <td>{{ $line->account?->label() ?? '-' }}</td>
                    <td>{{ number_format($line->quantity, 2) }}</td>
                    <td>{{ number_format($line->unit_price, 2) }}</td>
                    <td>{{ number_format($line->taxable_amount, 2) }}</td>
                    <td>{{ number_format($line->vat_amount, 2) }}</td>
                    <td><strong>{{ number_format($line->total_amount, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No lines on this bill.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
