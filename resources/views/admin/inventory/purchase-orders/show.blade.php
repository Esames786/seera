@extends('layouts.admin')

@section('title', 'Purchase Order')
@section('breadcrumb', 'Inventory / Purchase Orders / Purchase Order')

@section('content')
    @php
        $canAttach = $order->acceptsAttachments() && auth()->user()->hasPermission('Purchase Orders', 'create');
        $canRemoveAttachment = $order->isEditable() && auth()->user()->hasPermission('Purchase Orders', 'delete');
    @endphp

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
                <tr><th>Default VAT Rate</th><td>{{ number_format($order->vat_rate, 2) }}%</td></tr>
                <tr><th>Line Discounts</th><td>SAR {{ number_format($order->discount_amount, 2) }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$order->status"/></td></tr>
                <tr><th>Approved By</th><td>{{ $order->approver?->name ?? '-' }}</td></tr>
                <tr><th>Notes</th><td>{{ $order->notes ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <x-admin.data-table title="Supplier Quotations" subtitle="As received from the supplier">
                <tbody>
                    @forelse ($order->attachments as $attachment)
                        <tr>
                            <td>
                                📎 <a href="{{ route('admin.inventory.purchase-orders.attachments.download', [$order, $attachment]) }}" style="color:var(--blue);font-weight:700">{{ $attachment->file_name }}</a>
                                <div class="small">{{ $attachment->humanSize() }} · {{ $attachment->uploader?->name ?? 'Unknown' }} · {{ $attachment->created_at->format('M d, Y H:i') }}</div>
                            </td>
                            <td style="width:90px;text-align:right">
                                @if ($canRemoveAttachment)
                                    <button type="button" class="btn sm danger js-delete" data-delete-url="{{ route('admin.inventory.purchase-orders.attachments.destroy', [$order, $attachment]) }}" data-delete-name="{{ $attachment->file_name }}">Remove</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="table-empty">No quotation attached yet.</td></tr>
                    @endforelse
                </tbody>
                @if ($canAttach)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.inventory.purchase-orders.attachments.store', $order) }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;width:100%">
                            @csrf
                            <input name="quotations[]" type="file" class="input" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" required style="flex:1"/>
                            <button type="submit" class="btn sm primary">Upload</button>
                        </form>
                    </x-slot:footer>
                @endif
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
    </div>

    <x-admin.data-table title="Order Lines" subtitle="Prices excluding VAT (SAR)">
        <thead>
            <tr><th>Item</th><th>Description</th><th>Unit</th><th>Ordered</th><th>Received</th><th>Outstanding</th><th>Unit Price</th><th>Disc %</th><th>Taxable</th><th>VAT %</th><th>VAT</th><th>Total</th></tr>
        </thead>
        <tbody>
            @forelse ($order->lines as $line)
                <tr>
                    <td>{{ $line->item->label() }}</td>
                    <td class="small" style="max-width:260px;white-space:pre-line">{{ $line->description ?? '-' }}</td>
                    <td>{{ $line->item->unit?->code ?? '-' }}</td>
                    <td>{{ rtrim(rtrim(number_format($line->quantity, 3), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($line->received_quantity, 3), '0'), '.') }}</td>
                    <td><strong>{{ rtrim(rtrim(number_format($line->outstandingQuantity(), 3), '0'), '.') }}</strong></td>
                    <td>SAR {{ number_format($line->unit_price, 2) }}</td>
                    <td>{{ (float) $line->discount_percent > 0 ? number_format($line->discount_percent, 2).'%' : '-' }}</td>
                    <td>{{ number_format($line->taxable_amount, 2) }}</td>
                    <td>{{ number_format($line->vat_rate, 2) }}%</td>
                    <td>{{ number_format($line->vat_amount, 2) }}</td>
                    <td><strong>{{ number_format($line->total_amount, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="12" class="table-empty">No lines on this order.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
