@extends('layouts.admin')

@section('title', 'Purchase Orders')
@section('breadcrumb', 'Inventory / Purchase Orders')

@section('content')
    <x-admin.page-header title="Purchase Orders" description="Supplier orders raised from approved purchase requests, tracked through receiving">
        <a class="btn primary" href="{{ route('admin.inventory.purchase-orders.create') }}">+ Add Purchase Order</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$draftCount" label="Draft Orders"/>
        <x-admin.metric-card color="blue" :value="$openCount" label="Open For Receiving"/>
        <x-admin.metric-card color="green" :value="$receivedCount" label="Fully Received"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($openValue, 2)" label="Open Order Value"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="PO number or supplier..."/>
        <select class="select" style="width:190px" name="supplier">
            <option value="">All Suppliers</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected(request('supplier') == $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:170px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.purchase-orders.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Purchase Orders Listing">
        <thead>
            <tr><th>PO Number</th><th>Supplier</th><th>PO Date</th><th>Expected</th><th>Project</th><th>Warehouse</th><th>Lines</th><th>Taxable</th><th>VAT</th><th>Total</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td><a href="{{ route('admin.inventory.purchase-orders.show', $order) }}" style="color:var(--blue);font-weight:700">{{ $order->po_number }}</a></td>
                    <td>{{ $order->supplier->name }}</td>
                    <td>{{ $order->po_date->toDateString() }}</td>
                    <td>{{ $order->expected_delivery_date?->toDateString() ?? '-' }}</td>
                    <td>{{ $order->project?->name ?? '-' }}</td>
                    <td>{{ $order->warehouse?->name ?? '-' }}</td>
                    <td>{{ $order->lines_count }}</td>
                    <td>{{ number_format($order->taxable_amount, 2) }}</td>
                    <td>{{ number_format($order->vat_amount, 2) }}</td>
                    <td><strong>{{ number_format($order->total_amount, 2) }}</strong></td>
                    <td><x-admin.status-badge :status="$order->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.inventory.purchase-orders.show', $order)"
                            :edit="$order->isEditable() ? route('admin.inventory.purchase-orders.edit', $order) : null"
                            :delete="$order->isEditable() ? route('admin.inventory.purchase-orders.destroy', $order) : null"
                            :name="$order->po_number">
                            @if ($order->status === 'draft')
                                <form method="POST" action="{{ route('admin.inventory.purchase-orders.approve', $order) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Approve</button>
                                </form>
                            @elseif ($order->canReceive())
                                <a class="btn sm warning" href="{{ route('admin.inventory.goods-receipts.create', ['purchase_order' => $order->id]) }}">Receive</a>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="12" class="table-empty">No purchase orders match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $orders->firstItem() ?? 0 }}-{{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }}</span>
            {{ $orders->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
