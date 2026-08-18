@extends('layouts.admin')

@section('title', 'Purchase Request')
@section('breadcrumb', 'Inventory / Purchase Requests / Purchase Request')

@section('content')
    <x-admin.page-header :title="'Purchase Request: '.$pr->pr_number" :description="($pr->project?->name ?? 'No project').' - requested '.$pr->request_date->toDateString()">
        @if ($pr->isEditable())
            <a class="btn outline" href="{{ route('admin.inventory.purchase-requests.edit', $pr) }}">Edit</a>
            <form method="POST" action="{{ route('admin.inventory.purchase-requests.approve', $pr) }}">
                @csrf
                <button type="submit" class="btn primary">Approve</button>
            </form>
        @elseif ($pr->status === 'approved')
            <a class="btn primary" href="{{ route('admin.inventory.purchase-orders.create', ['purchase_request' => $pr->id]) }}">Create Purchase Order</a>
        @endif
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$pr->lines->count()" label="Requested Lines"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($pr->estimated_total, 2)" label="Estimated Total"/>
        <x-admin.metric-card color="yellow" :value="ucfirst($pr->priority)" label="Priority"/>
        <x-admin.metric-card color="green" :value="ucfirst($pr->status)" label="Status"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Request Information" class="detail-table">
            <tbody>
                <tr><th>PR Number</th><td>{{ $pr->pr_number }}</td></tr>
                <tr><th>Request Date</th><td>{{ $pr->request_date->toDateString() }}</td></tr>
                <tr><th>Required Date</th><td>{{ $pr->required_date?->toDateString() ?? '-' }}</td></tr>
                <tr><th>Requested By</th><td>{{ $pr->requester?->name ?? '-' }}</td></tr>
                <tr><th>Project / Site</th><td>{{ $pr->project?->name ?? '-' }}@if($pr->site) / {{ $pr->site->name }}@endif</td></tr>
                <tr><th>Warehouse</th><td>{{ $pr->warehouse?->name ?? '-' }}</td></tr>
                <tr><th>Priority</th><td>{{ ucfirst($pr->priority) }}</td></tr>
                <tr><th>Reason</th><td>{{ $pr->reason ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$pr->status"/></td></tr>
                <tr><th>Approved By</th><td>{{ $pr->approver?->name ?? '-' }}</td></tr>
                <tr><th>Approved At</th><td>{{ $pr->approved_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
                <tr><th>Rejection Reason</th><td>{{ $pr->rejection_reason ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            @if (in_array($pr->status, ['draft', 'pending']))
                <div class="form-section">
                    <div class="section-title">Reject Request</div>
                    <div class="section-body">
                        <form method="POST" action="{{ route('admin.inventory.purchase-requests.reject', $pr) }}">
                            @csrf
                            <label for="rejection_reason">Rejection Reason *</label>
                            <textarea id="rejection_reason" name="rejection_reason" class="textarea" required></textarea>
                            <div class="form-actions" style="margin-top:12px">
                                <button type="submit" class="btn danger">Reject Request</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <x-admin.data-table title="Purchase Orders From This Request">
                <thead>
                    <tr><th>PO Number</th><th>Supplier</th><th>Total</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($pr->purchaseOrders as $order)
                        <tr>
                            <td><a href="{{ route('admin.inventory.purchase-orders.show', $order) }}" style="color:var(--blue);font-weight:700">{{ $order->po_number }}</a></td>
                            <td>{{ $order->supplier->name }}</td>
                            <td>SAR {{ number_format($order->total_amount, 2) }}</td>
                            <td><x-admin.status-badge :status="$order->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">No purchase order raised from this request yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>
    </div>

    <x-admin.data-table title="Requested Items">
        <thead>
            <tr><th>Item</th><th>Description</th><th>Quantity</th><th>Unit</th><th>Est. Unit Cost</th><th>Est. Total</th><th>Budget Line</th></tr>
        </thead>
        <tbody>
            @forelse ($pr->lines as $line)
                <tr>
                    <td>{{ $line->item?->label() ?? '-' }}</td>
                    <td>{{ $line->description ?? '-' }}</td>
                    <td>{{ rtrim(rtrim(number_format($line->quantity, 3), '0'), '.') }}</td>
                    <td>{{ $line->unit?->code ?? '-' }}</td>
                    <td>SAR {{ number_format($line->estimated_unit_cost, 2) }}</td>
                    <td><strong>SAR {{ number_format($line->estimated_total, 2) }}</strong></td>
                    <td>{{ $line->budget_line ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty">No lines on this request.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
