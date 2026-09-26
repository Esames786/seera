@extends('layouts.admin')

@section('title', 'Purchase Order')
@section('breadcrumb', 'Inventory / Purchase Orders / Purchase Order')

@section('content')
    @php
        /** @var \App\Models\PurchaseOrder $order */
        $user = auth()->user();
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
        $canAttach = $order->acceptsAttachments() && $user->hasPermission('Purchase Orders', 'create');
        $canRemoveAttachment = $order->isEditable() && $user->hasPermission('Purchase Orders', 'delete');
        $canApprove = $order->isEditable() && $user->hasPermission('Purchase Orders', 'approve');
        $canEdit = $order->isEditable() && $user->hasPermission('Purchase Orders', 'edit');
        $receiptUrl = $summary['can_create_receipt']
            ? route('admin.inventory.goods-receipts.create', ['purchase_order' => $order->id, 'return_to' => route('admin.inventory.purchase-orders.show', $order, false).'#goods-receipts'])
            : null;
        $canViewSupplier = $user->hasPermission('Suppliers', 'view');
        $canManageSupplier = $user->hasPermission('Suppliers', 'edit');
        $canViewPayable = $user->hasPermission('Accounts Payable', 'view');
    @endphp

    <x-admin.page-header :title="'Purchase Order: '.$order->po_number" description="Read-only document view. Receiving, billing and payment are explicit actions on their own pages.">
        @if ($canEdit)
            <a class="btn outline" href="{{ route('admin.inventory.purchase-orders.edit', $order) }}">Edit</a>
        @endif
        @if ($canApprove)
            <form method="POST" action="{{ route('admin.inventory.purchase-orders.approve', $order) }}">
                @csrf
                <button type="submit" class="btn primary">Approve Order</button>
            </form>
        @endif
        @if ($receiptUrl)
            <a class="btn primary" href="{{ $receiptUrl }}">Create Goods Receipt</a>
        @endif
        <a class="btn outline" href="{{ route('admin.inventory.purchase-orders.index') }}">Back to Purchase Orders</a>
    </x-admin.page-header>

    @include('admin.inventory.purchase-orders._workspace-header', ['order' => $order, 'summary' => $summary])

    <nav class="tabs workspace-nav" aria-label="Purchase order sections">
        @foreach ($sections as $key => $label)
            <a class="tab" href="#{{ $key }}">{{ __($label) }}</a>
        @endforeach
    </nav>

    {{-- Overview --}}
    <div id="overview">
        <div class="card-grid">
            <x-admin.metric-card color="blue" :value="'SAR '.number_format($order->taxable_amount, 2)" label="Taxable Amount"/>
            <x-admin.metric-card color="yellow" :value="'SAR '.number_format($order->vat_amount, 2)" label="VAT Amount"/>
            <x-admin.metric-card color="cyan" :value="'SAR '.number_format($order->total_amount, 2)" label="Total Amount"/>
            <x-admin.metric-card color="green" :value="$summary['received_state']" label="Receiving"/>
        </div>

        <x-admin.data-table title="Order Information" class="detail-table">
            <tbody>
                <tr><th>PO Number</th><td>{{ $order->po_number }}</td></tr>
                <tr><th>Supplier</th><td>{{ $order->supplier->name }}</td></tr>
                <tr><th>PO Date</th><td>{{ $order->po_date->toDateString() }}</td></tr>
                <tr><th>Expected Delivery</th><td>{{ $order->expected_delivery_date?->toDateString() ?? '-' }}</td></tr>
                <tr><th>Project / Site</th><td>{{ $order->project?->name ?? '-' }}@if($order->site) / {{ $order->site->name }}@endif</td></tr>
                <tr><th>Deliver To Warehouse</th><td>{{ $order->warehouse?->name ?? '-' }}</td></tr>
                <tr><th>Source Purchase Request</th><td>{{ $order->purchaseRequest?->pr_number ?? '-' }}</td></tr>
                <tr><th>Default VAT Rate</th><td>{{ number_format($order->vat_rate, 2) }}%</td></tr>
                <tr><th>Line Discounts</th><td>SAR {{ number_format($order->discount_amount, 2) }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$order->status"/> <span class="small">{{ $summary['received_state'] }} · {{ $summary['billing_state'] }}</span></td></tr>
                <tr><th>Approved By</th><td>{{ $order->approver?->name ?? '-' }}@if($order->approved_at) · {{ $order->approved_at->format('Y-m-d H:i') }}@endif</td></tr>
                <tr><th>Notes</th><td>{{ $order->notes ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>
    </div>

    {{-- Order Lines --}}
    <x-admin.data-table title="Order Lines" subtitle="Prices excluding VAT (SAR). Received counts posted goods receipts; Invoiced counts approved supplier bills matched to them." id="lines">
        <thead>
            <tr><th>Item</th><th>Description</th><th>Unit</th><th>Ordered</th><th>Received</th><th>Still to receive</th><th>Invoiced</th><th>Received but not invoiced</th><th>Unit Price</th><th>Disc %</th><th>Taxable</th><th>VAT %</th><th>VAT</th><th>Total</th></tr>
        </thead>
        <tbody>
            @forelse ($matrix as $row)
                @php $line = $row['line']; @endphp
                <tr>
                    <td>{{ $line->item->label() }}</td>
                    <td class="small" style="max-width:260px;white-space:pre-line">{{ $line->description ?? '-' }}</td>
                    <td>{{ $line->item->unit?->code ?? '-' }}</td>
                    <td>{{ $qty($row['ordered']) }}</td>
                    <td>{{ $qty($row['received']) }}</td>
                    <td><strong>{{ $qty($row['outstanding']) }}</strong></td>
                    <td>{{ $qty($row['invoiced']) }}</td>
                    <td>{{ $qty($row['uninvoiced']) }}</td>
                    <td>SAR {{ number_format($line->unit_price, 2) }}</td>
                    <td>{{ (float) $line->discount_percent > 0 ? number_format($line->discount_percent, 2).'%' : '-' }}</td>
                    <td>{{ number_format($line->taxable_amount, 2) }}</td>
                    <td>{{ number_format($line->vat_rate, 2) }}%</td>
                    <td>{{ number_format($line->vat_amount, 2) }}</td>
                    <td><strong>{{ number_format($line->total_amount, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="14" class="table-empty">No lines on this order.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    {{-- Source Purchase Request --}}
    @if (isset($sections['purchase-request']))
        @php $pr = $order->purchaseRequest; @endphp
        <x-admin.data-table title="Source Purchase Request" class="detail-table" id="purchase-request">
            <tbody>
                @if ($pr)
                    <tr><th>PR Number</th><td><a href="{{ route('admin.inventory.purchase-requests.show', $pr) }}" style="font-weight:700">{{ $pr->pr_number }}</a></td></tr>
                    <tr><th>Requested By</th><td>{{ $pr->requester?->name ?? '-' }} · {{ $pr->request_date->toDateString() }}</td></tr>
                    <tr><th>Required Date</th><td>{{ $pr->required_date?->toDateString() ?? '-' }}</td></tr>
                    <tr><th>Priority</th><td>{{ ucfirst($pr->priority) }}</td></tr>
                    <tr><th>Request Status</th><td><x-admin.status-badge :status="$pr->status"/>@if($pr->approver) <span class="small">approved by {{ $pr->approver->name }}</span>@endif</td></tr>
                    <tr><th>Requested Lines</th><td>{{ $pr->lines->count() }} line{{ $pr->lines->count() === 1 ? '' : 's' }} · estimated SAR {{ number_format($pr->estimated_total, 2) }}</td></tr>
                    <tr><th>Reason</th><td>{{ $pr->reason ?? '-' }}</td></tr>
                @else
                    <tr><td class="table-empty">This order was raised directly, without a purchase request.</td></tr>
                @endif
            </tbody>
        </x-admin.data-table>
    @endif

    {{-- Supplier & Commercial --}}
    @if (isset($sections['supplier']))
        @php $supplier = $order->supplier; @endphp
        <x-admin.data-table title="Supplier & Commercial" class="detail-table" id="supplier">
            <tbody>
                <tr><th>Supplier</th><td>{{ $supplier->code }} — {{ $supplier->name }}</td></tr>
                <tr><th>Contact</th><td>{{ $supplier->contact_person ?? '-' }}{{ $supplier->phone ? ' · '.$supplier->phone : '' }}{{ $supplier->email ? ' · '.$supplier->email : '' }}</td></tr>
                <tr><th>VAT / CR Number</th><td>{{ $supplier->vat_number ?? '-' }} / {{ $supplier->cr_number ?? '-' }}</td></tr>
                <tr><th>Payment Terms</th><td>{{ $supplier->paymentTerm?->label() ?? $supplier->payment_terms ?? '-' }} · accepts {{ $supplier->allowed_payment_types ?? 'Both' }}</td></tr>
                <tr><th>Rating</th><td>{{ $supplier->rating ?? '-' }}</td></tr>
                @if ($canViewPayable)
                    <tr><th>Payable Account</th><td>{{ $supplier->linkedAccount?->label() ?? $supplier->linked_account ?? '-' }}</td></tr>
                @endif
                <tr><th>Supplier Status</th><td><x-admin.status-badge :status="$supplier->status"/></td></tr>
            </tbody>
            <x-slot:footer>
                @if ($canViewSupplier)<a class="btn sm outline" href="{{ route('admin.master.suppliers.show', $supplier) }}">View Supplier</a>@endif
                @if ($canManageSupplier)<a class="btn sm outline" href="{{ route('admin.master.suppliers.edit', $supplier) }}">Manage Supplier</a>@endif
            </x-slot:footer>
        </x-admin.data-table>
    @endif

    {{-- Quotations --}}
    <x-admin.data-table title="Supplier Quotations" subtitle="As received from the supplier" id="attachments">
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

    {{-- Paged sections: Goods Receipts, Billing & GRN Matching, Accounting, Activity --}}
    @foreach ($panels as $key => $data)
        @if ($key === 'goods-receipts' && $receiptUrl)
            <div class="form-actions" style="justify-content:flex-start;margin-bottom:-8px">
                <a class="btn sm primary" href="{{ $receiptUrl }}">Create Goods Receipt</a>
                <span class="small">Still to receive: {{ $qty($summary['outstanding']) }}</span>
            </div>
        @endif
        @include('admin.inventory.purchase-orders._workspace-panel', $data)
    @endforeach
@endsection
