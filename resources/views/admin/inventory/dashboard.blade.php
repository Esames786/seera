@extends('layouts.admin')

@section('title', 'Inventory Dashboard')
@section('breadcrumb', 'Inventory / Inventory Dashboard')

@section('content')
    <x-admin.page-header title="Inventory Dashboard" description="Stock value, low stock, purchasing pipeline and recent warehouse movement">
        <a class="btn outline" href="{{ route('admin.inventory.purchase-requests.create') }}">+ Purchase Request</a>
        <a class="btn primary" href="{{ route('admin.inventory.goods-receipts.create') }}">+ Goods Receipt</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalItems" label="Total Items"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($stockValue, 2)" label="Stock Value"/>
        <x-admin.metric-card color="red" :value="$lowStockCount" label="Low Stock"/>
        <x-admin.metric-card color="yellow" :value="$pendingRequests" label="Pending PRs"/>
    </div>

    <div class="card-grid">
        <x-admin.metric-card color="cyan" :value="$openOrders" label="Open POs"/>
        <x-admin.metric-card color="yellow" :value="$pendingReceipts" label="Pending GRNs"/>
        <x-admin.metric-card color="blue" :value="$openTransfers" label="Open Transfers"/>
        <x-admin.metric-card color="red" :value="$unpostedStock" label="Unposted Stock Documents"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Low Stock Alerts">
            <x-slot:headerActions>
                <a class="btn sm primary" href="{{ route('admin.inventory.reports.low-stock') }}">Full Report</a>
            </x-slot:headerActions>
            <thead>
                <tr><th>Item</th><th>Warehouse</th><th>On Hand</th><th>Reorder Level</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($lowStockRows as $row)
                    <tr>
                        <td><a href="{{ route('admin.inventory.items.show', $row->item) }}" style="color:var(--blue);font-weight:700">{{ $row->item->label() }}</a></td>
                        <td>{{ $row->warehouse->name }}</td>
                        <td>{{ rtrim(rtrim(number_format($row->quantity, 3), '0'), '.') }} {{ $row->item->unit?->code }}</td>
                        <td>{{ rtrim(rtrim(number_format($row->item->reorder_level, 3), '0'), '.') }}</td>
                        <td><x-admin.status-badge :status="(float) $row->quantity <= 0 ? 'absent' : 'late'"/></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-empty">No items are below their reorder level.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Warehouse Stock Summary">
            <x-slot:headerActions>
                <a class="btn sm primary" href="{{ route('admin.inventory.stock.index') }}">Stock On Hand</a>
            </x-slot:headerActions>
            <thead>
                <tr><th>Warehouse</th><th>Stocked Items</th><th>Stock Value</th></tr>
            </thead>
            <tbody>
                @forelse ($warehouseSummary as $warehouse)
                    <tr>
                        <td>{{ $warehouse->name }}</td>
                        <td>{{ $warehouse->stocks_count }}</td>
                        <td>SAR {{ number_format($warehouse->stock_value ?? 0, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="table-empty">No warehouses yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    </div>

    <x-admin.data-table title="Recent Stock Movement">
        <x-slot:headerActions>
            <a class="btn sm primary" href="{{ route('admin.inventory.stock-ledger') }}">Open Stock Ledger</a>
        </x-slot:headerActions>
        <thead>
            <tr><th>Date</th><th>Reference</th><th>Type</th><th>Item</th><th>Warehouse</th><th>In</th><th>Out</th><th>Balance</th><th>Value</th></tr>
        </thead>
        <tbody>
            @forelse ($recentMovements as $entry)
                <tr>
                    <td>{{ $entry->movement_date->toDateString() }}</td>
                    <td>{{ $entry->reference_number ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$entry->movement_type"/></td>
                    <td>{{ $entry->item->label() }}</td>
                    <td>{{ $entry->warehouse->name }}</td>
                    <td>{{ (float) $entry->in_quantity > 0 ? rtrim(rtrim(number_format($entry->in_quantity, 3), '0'), '.') : '-' }}</td>
                    <td>{{ (float) $entry->out_quantity > 0 ? rtrim(rtrim(number_format($entry->out_quantity, 3), '0'), '.') : '-' }}</td>
                    <td>{{ rtrim(rtrim(number_format($entry->balance_quantity, 3), '0'), '.') }}</td>
                    <td>SAR {{ number_format($entry->value, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No stock movement recorded yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
