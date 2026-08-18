@extends('layouts.admin')

@section('title', 'Purchase Order')
@section('breadcrumb', 'Inventory / Purchase Orders / Purchase Order')

@section('content')
    <x-admin.page-header :title="'Purchase Order: '.$order->po_number" :description="$order->supplier->name.' - '.$order->po_date->toDateString()">
        @if ($order->isEditable())
            <a class="btn outline" href="{{ route('admin.inventory.purchase-orders.edit', $order) }}">Edit</a>
            <form method="POST" action="{{ route('admin.inventory.purchase-orders.approve', $order) }}">
                @csrf
                <button type="submit" class="btn primary">Approve Order</button>
            </form>
        @elseif ($order->canReceive())
            <a class="btn primary" href="{{ route('admin.inventory.goods-receipts.create', ['purchase_order' => $order->id]) }}">Create Goods Receipt</a>
        @endif
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($order->taxable_amount, 2)" label="Taxable Amount"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($order->vat_amount, 2)" label="VAT Amount"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($order->total_amount, 2)" label="Total Amount"/>
        <x-admin.metric-card color="green" :value="ucfirst(str_replace('_', ' ', $order->status))" label="Status"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Order Information" class="detail-table">
            <tbody>
                <tr><th>PO Number</th><td>{{ $order->po_number }}</td></tr>
                <tr><th>Supplier</th><td>{{ $order->supplier->name }}</td></tr>
                <tr><th>PO Date</th><td>{{ $order->po_date->toDateString() }}</td></tr>
                <tr><th>Expected Delivery</th><td>{{ $order->expected_delivery_date?->toDateString() ?? '-' }}</td></tr>
                <tr><th>Project / Site</th><td>{{ $order->project?->name ?? '-' }}@if($order->site) / {{ $order->site->name }}@endif</td></tr>
                <tr><th>Warehouse</th><td>{{ $order->warehouse?->name ?? '-' }}</td></tr>
                <tr><th>Source Request</th><td>{{ $order->purchaseRequest?->pr_number ?? '-' }}</td></tr>
                <tr><th>VAT Rate</th><td>{{ number_format($order->vat_rate, 2) }}%</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$order->status"/></td></tr>
                <tr><th>Approved By</th><td>{{ $order->approver?->name ?? '-' }}</td></tr>
                <tr><th>Notes</th><td>{{ $order->notes ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Goods Receipts Against This Order">
            <thead>
                <tr><th>GRN</th><th>Received</th><th>Total</th><th>Stock</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($order->goodsReceipts as $grn)
                    <tr>
                        <td><a href="{{ route('admin.inventory.goods-receipts.show', $grn) }}" style="color:var(--blue);font-weight:700">{{ $grn->grn_number }}</a></td>
                        <td>{{ $grn->received_date->toDateString() }}</td>
                        <td>SAR {{ number_format($grn->total_amount, 2) }}</td>
                        <td><x-admin.status-badge :status="$grn->stock_updated ? 'yes' : 'no'"/></td>
                        <td><x-admin.status-badge :status="$grn->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-empty">Nothing received against this order yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    </div>

    <x-admin.data-table title="Order Lines">
        <thead>
            <tr><th>Item</th><th>Unit</th><th>Ordered</th><th>Received</th><th>Outstanding</th><th>Unit Price</th><th>Taxable</th><th>VAT</th><th>Total</th></tr>
        </thead>
        <tbody>
            @forelse ($order->lines as $line)
                <tr>
                    <td>{{ $line->item->label() }}</td>
                    <td>{{ $line->item->unit?->code ?? '-' }}</td>
                    <td>{{ rtrim(rtrim(number_format($line->quantity, 3), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($line->received_quantity, 3), '0'), '.') }}</td>
                    <td><strong>{{ rtrim(rtrim(number_format($line->outstandingQuantity(), 3), '0'), '.') }}</strong></td>
                    <td>SAR {{ number_format($line->unit_price, 2) }}</td>
                    <td>{{ number_format($line->taxable_amount, 2) }}</td>
                    <td>{{ number_format($line->vat_amount, 2) }}</td>
                    <td><strong>{{ number_format($line->total_amount, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No lines on this order.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
