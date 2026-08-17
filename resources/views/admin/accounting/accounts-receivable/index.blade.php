@extends('layouts.admin')

@section('title', 'Accounts Receivable')
@section('breadcrumb', 'Accounting / Accounts Receivable')

@section('content')
    <x-admin.page-header title="Accounts Receivable" description="Customer invoices, output VAT, ZATCA status and receipts">
        <a class="btn primary" href="{{ route('admin.accounting.accounts-receivable.create') }}">+ Add Customer Invoice</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($totalReceivable, 2)" label="Outstanding Receivable"/>
        <x-admin.metric-card color="yellow" :value="$overdueCount" label="Overdue Invoices"/>
        <x-admin.metric-card color="cyan" :value="$draftCount" label="Draft Invoices"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($receivedThisMonth, 2)" label="Received This Month"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Invoice number or customer..."/>
        <select class="select" style="width:180px" name="customer">
            <option value="">All Customers</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected(request('customer') == $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:160px" name="status">
            <option value="">All Payment Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </select>
        <select class="select" style="width:170px" name="zatca">
            <option value="">All ZATCA Status</option>
            @foreach ($zatcaStatuses as $status)
                <option value="{{ $status }}" @selected(request('zatca') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Customer Invoices">
        <thead>
            <tr>
                <th>Invoice</th><th>Customer</th><th>Invoice Date</th><th>Due Date</th>
                <th>Taxable</th><th>VAT</th><th>Total</th><th>Received</th><th>Balance</th>
                <th>ZATCA</th><th>Payment</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td><a href="{{ route('admin.accounting.accounts-receivable.show', $invoice) }}" style="color:var(--blue);font-weight:700">{{ $invoice->invoice_number }}</a></td>
                    <td>{{ $invoice->customer->name }}</td>
                    <td>{{ $invoice->invoice_date->toDateString() }}</td>
                    <td>
                        {{ $invoice->due_date?->toDateString() ?? '-' }}
                        @if ($invoice->due_date && $invoice->due_date->isPast() && in_array($invoice->payment_status, ['unpaid', 'partially_paid']))
                            <span class="badge red">Overdue</span>
                        @endif
                    </td>
                    <td>{{ number_format($invoice->taxable_amount, 2) }}</td>
                    <td>{{ number_format($invoice->vat_amount, 2) }}</td>
                    <td>{{ number_format($invoice->total_amount, 2) }}</td>
                    <td>{{ number_format($invoice->received_amount, 2) }}</td>
                    <td><strong>{{ number_format($invoice->balance_amount, 2) }}</strong></td>
                    <td><x-admin.status-badge :status="$invoice->zatca_status"/></td>
                    <td><x-admin.status-badge :status="$invoice->payment_status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.accounting.accounts-receivable.show', $invoice)"
                            :edit="$invoice->isEditable() ? route('admin.accounting.accounts-receivable.edit', $invoice) : null"
                            :delete="$invoice->isEditable() ? route('admin.accounting.accounts-receivable.destroy', $invoice) : null"
                            :name="$invoice->invoice_number">
                            @if ($invoice->payment_status === 'draft')
                                <form method="POST" action="{{ route('admin.accounting.accounts-receivable.approve', $invoice) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Approve</button>
                                </form>
                            @elseif (in_array($invoice->payment_status, ['unpaid', 'partially_paid']))
                                <a class="btn sm warning" href="{{ route('admin.accounting.accounts-receivable.receipt', $invoice) }}">Receipt</a>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="12" class="table-empty">No invoices match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $invoices->firstItem() ?? 0 }}-{{ $invoices->lastItem() ?? 0 }} of {{ $invoices->total() }}</span>
            {{ $invoices->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    <x-admin.data-table title="Recent Customer Receipts">
        <thead>
            <tr><th>Date</th><th>Customer</th><th>Invoice</th><th>Amount</th><th>Reference</th></tr>
        </thead>
        <tbody>
            @forelse ($recentReceipts as $receipt)
                <tr>
                    <td>{{ $receipt->receipt_date->toDateString() }}</td>
                    <td>{{ $receipt->customer->name }}</td>
                    <td>{{ $receipt->invoice?->invoice_number ?? '-' }}</td>
                    <td>SAR {{ number_format($receipt->amount, 2) }}</td>
                    <td>{{ $receipt->reference_number ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="table-empty">No receipts recorded yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <div class="note">
        Approving an invoice posts: debit accounts receivable, credit revenue and output VAT, and creates the ZATCA invoice record. Recording a receipt posts: debit cash/bank, credit accounts receivable.
    </div>
@endsection
