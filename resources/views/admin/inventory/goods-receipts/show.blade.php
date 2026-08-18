@extends('layouts.admin')

@section('title', 'Goods Receipt')
@section('breadcrumb', 'Inventory / Goods Receipt Notes / Goods Receipt')

@section('content')
    <x-admin.page-header :title="'Goods Receipt: '.$grn->grn_number" :description="$grn->supplier->name.' into '.$grn->warehouse->name">
        @if ($grn->isEditable())
            <a class="btn outline" href="{{ route('admin.inventory.goods-receipts.edit', $grn) }}">Edit</a>
            <form method="POST" action="{{ route('admin.inventory.goods-receipts.post-stock', $grn) }}">
                @csrf
                <button type="submit" class="btn primary">Post Stock</button>
            </form>
        @endif
    </x-admin.page-header>

    @if ($grn->status === 'posted')
        <div class="alert success flash">This goods receipt is posted and read-only. Stock and the ledger have been updated.</div>
    @endif

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($grn->taxable_amount, 2)" label="Taxable Amount"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($grn->vat_amount, 2)" label="Input VAT"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($grn->total_amount, 2)" label="Total Amount"/>
        <x-admin.metric-card :color="$grn->stock_updated ? 'green' : 'yellow'" :value="$grn->stock_updated ? 'Updated' : 'Pending'" label="Stock Status"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Receipt Information" class="detail-table">
            <tbody>
                <tr><th>GRN Number</th><td>{{ $grn->grn_number }}</td></tr>
                <tr><th>Purchase Order</th><td>{{ $grn->purchaseOrder?->po_number ?? '-' }}</td></tr>
                <tr><th>Supplier</th><td>{{ $grn->supplier->name }}</td></tr>
                <tr><th>Warehouse</th><td>{{ $grn->warehouse->name }}</td></tr>
                <tr><th>Received Date</th><td>{{ $grn->received_date->toDateString() }}</td></tr>
                <tr><th>Received By</th><td>{{ $grn->receiver?->name ?? '-' }}</td></tr>
                <tr><th>Delivery Note</th><td>{{ $grn->delivery_note_number ?? '-' }}</td></tr>
                <tr><th>Invoice Number</th><td>{{ $grn->invoice_number ?? '-' }}</td></tr>
                <tr><th>VAT Rate</th><td>{{ number_format($grn->vat_rate, 2) }}%</td></tr>
                <tr><th>Stock Updated</th><td><x-admin.status-badge :status="$grn->stock_updated ? 'yes' : 'no'"/></td></tr>
                <tr><th>Accounting Posted</th><td><x-admin.status-badge :status="$grn->accounting_posted ? 'posted' : 'pending'"/></td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$grn->status"/></td></tr>
                <tr><th>Notes</th><td>{{ $grn->notes ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Accounting Entry">
            @if ($grn->journalEntry)
                <thead>
                    <tr><th>Account</th><th>Debit</th><th>Credit</th></tr>
                </thead>
                <tbody>
                    @foreach ($grn->journalEntry->lines as $line)
                        <tr>
                            <td>{{ $line->account->label() }}</td>
                            <td>{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                            <td>{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <x-slot:footer>
                    <span class="small">Journal</span>
                    <a class="btn sm primary" href="{{ route('admin.accounting.journal-entries.show', $grn->journalEntry) }}">{{ $grn->journalEntry->journal_number }}</a>
                </x-slot:footer>
            @else
                <tbody>
                    <tr><td class="table-empty">No accounting entry yet. Post the receipt to create it.</td></tr>
                </tbody>
            @endif
        </x-admin.data-table>
    </div>

    <x-admin.data-table title="Received Lines">
        <thead>
            <tr><th>Item</th><th>Unit</th><th>Ordered</th><th>Received</th><th>Accepted</th><th>Rejected</th><th>Unit Cost</th><th>Total Cost</th></tr>
        </thead>
        <tbody>
            @forelse ($grn->lines as $line)
                <tr>
                    <td>{{ $line->item->label() }}</td>
                    <td>{{ $line->item->unit?->code ?? '-' }}</td>
                    <td>{{ rtrim(rtrim(number_format($line->ordered_quantity, 3), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($line->received_quantity, 3), '0'), '.') }}</td>
                    <td><strong>{{ rtrim(rtrim(number_format($line->accepted_quantity, 3), '0'), '.') }}</strong></td>
                    <td>{{ rtrim(rtrim(number_format($line->rejected_quantity, 3), '0'), '.') }}</td>
                    <td>SAR {{ number_format($line->unit_cost, 2) }}</td>
                    <td><strong>SAR {{ number_format($line->total_cost, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No lines on this goods receipt.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
