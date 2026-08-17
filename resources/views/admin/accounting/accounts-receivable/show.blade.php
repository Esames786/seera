@extends('layouts.admin')

@section('title', 'Customer Invoice')
@section('breadcrumb', 'Accounting / Accounts Receivable / Customer Invoice')

@section('content')
    <x-admin.page-header :title="'Invoice: '.$invoice->invoice_number" :description="$invoice->customer->name.' · '.$invoice->invoice_date->toDateString()">
        @if ($invoice->isEditable())
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.edit', $invoice) }}">Edit</a>
        @endif
        @if ($invoice->payment_status === 'draft')
            <form method="POST" action="{{ route('admin.accounting.accounts-receivable.approve', $invoice) }}">
                @csrf
                <button type="submit" class="btn primary">Approve &amp; Post</button>
            </form>
        @elseif (in_array($invoice->payment_status, ['unpaid', 'partially_paid']))
            <a class="btn primary" href="{{ route('admin.accounting.accounts-receivable.receipt', $invoice) }}">Record Receipt</a>
        @endif
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($invoice->taxable_amount, 2)" label="Taxable Amount"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($invoice->vat_amount, 2)" label="Output VAT"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($invoice->total_amount, 2)" label="Total Amount"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($invoice->balance_amount, 2)" label="Outstanding Balance"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Invoice Information" class="detail-table">
            <tbody>
                <tr><th>Customer</th><td>{{ $invoice->customer->name }}</td></tr>
                <tr><th>Invoice Number</th><td>{{ $invoice->invoice_number }}</td></tr>
                <tr><th>Invoice Date</th><td>{{ $invoice->invoice_date->toDateString() }}</td></tr>
                <tr><th>Due Date</th><td>{{ $invoice->due_date?->toDateString() ?? '-' }}</td></tr>
                <tr><th>Project</th><td>{{ $invoice->project?->name ?? '-' }}</td></tr>
                <tr><th>Cost Center</th><td>{{ $invoice->costCenter ? $invoice->costCenter->code.' - '.$invoice->costCenter->name : '-' }}</td></tr>
                <tr><th>VAT Rate</th><td>{{ number_format($invoice->vat_rate, 2) }}%</td></tr>
                <tr><th>Received Amount</th><td>SAR {{ number_format($invoice->received_amount, 2) }}</td></tr>
                <tr><th>Payment Status</th><td><x-admin.status-badge :status="$invoice->payment_status"/></td></tr>
                <tr><th>ZATCA Status</th><td><x-admin.status-badge :status="$invoice->zatca_status"/></td></tr>
                <tr><th>Notes</th><td>{{ $invoice->notes ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <x-admin.data-table title="ZATCA Record">
                @if ($invoice->zatcaRecord)
                    <tbody>
                        <tr><th>UUID</th><td class="small">{{ $invoice->zatcaRecord->uuid }}</td></tr>
                        <tr><th>QR Code</th><td><x-admin.status-badge :status="$invoice->zatcaRecord->qrStatus()"/></td></tr>
                        <tr><th>XML</th><td><x-admin.status-badge :status="$invoice->zatcaRecord->xmlStatus()"/></td></tr>
                        <tr><th>Digital Signature</th><td><x-admin.status-badge :status="$invoice->zatcaRecord->digital_signature_status"/></td></tr>
                        <tr><th>Clearance</th><td><x-admin.status-badge :status="$invoice->zatcaRecord->clearance_status"/></td></tr>
                        <tr><th>Retry Count</th><td>{{ $invoice->zatcaRecord->retry_count }}</td></tr>
                    </tbody>
                    <x-slot:footer>
                        <span class="small">ZATCA</span>
                        <a class="btn sm primary" href="{{ route('admin.accounting.zatca.show', $invoice->zatcaRecord) }}">Open Record</a>
                    </x-slot:footer>
                @else
                    <tbody>
                        <tr><td class="table-empty">No ZATCA record yet. Approve the invoice to generate it.</td></tr>
                    </tbody>
                @endif
            </x-admin.data-table>

            <x-admin.data-table title="Accounting Entry">
                @if ($invoice->journalEntry)
                    <thead>
                        <tr><th>Account</th><th>Debit</th><th>Credit</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->journalEntry->lines as $line)
                            <tr>
                                <td>{{ $line->account->label() }}</td>
                                <td>{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                                <td>{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <x-slot:footer>
                        <span class="small">Journal</span>
                        <a class="btn sm primary" href="{{ route('admin.accounting.journal-entries.show', $invoice->journalEntry) }}">{{ $invoice->journalEntry->journal_number }}</a>
                    </x-slot:footer>
                @else
                    <tbody>
                        <tr><td class="table-empty">No accounting entry yet. Approve the invoice to post it.</td></tr>
                    </tbody>
                @endif
            </x-admin.data-table>
        </div>
    </div>

    <x-admin.data-table title="Invoice Lines">
        <thead>
            <tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Revenue Account</th><th>Cost Center</th><th>Taxable</th><th>VAT</th><th>Total</th></tr>
        </thead>
        <tbody>
            @forelse ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td>{{ number_format($line->quantity, 2) }}</td>
                    <td>{{ number_format($line->unit_price, 2) }}</td>
                    <td>{{ $line->revenueAccount?->label() ?? '-' }}</td>
                    <td>{{ $line->costCenter?->code ?? '-' }}</td>
                    <td>{{ number_format($line->taxable_amount, 2) }}</td>
                    <td>{{ number_format($line->vat_amount, 2) }}</td>
                    <td><strong>{{ number_format($line->total_amount, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No lines on this invoice.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <x-admin.data-table title="Receipts">
        <thead>
            <tr><th>Date</th><th>Account</th><th>Amount</th><th>Reference</th></tr>
        </thead>
        <tbody>
            @forelse ($invoice->receipts as $receipt)
                <tr>
                    <td>{{ $receipt->receipt_date->toDateString() }}</td>
                    <td>{{ $receipt->receiptAccount?->label() ?? '-' }}</td>
                    <td>SAR {{ number_format($receipt->amount, 2) }}</td>
                    <td>{{ $receipt->reference_number ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="table-empty">No receipts recorded against this invoice.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
