@extends('layouts.admin')

@section('title', 'Low Stock Report')
@section('breadcrumb', 'Inventory / Reports / Low Stock')

@section('content')
    <x-admin.page-header title="Low Stock Report" description="Items at or below their reorder level, by warehouse">
        <a class="btn outline" href="{{ route('admin.inventory.reports.index') }}">All Reports</a>
        <a class="btn primary" href="{{ route('admin.inventory.purchase-requests.create') }}">+ Purchase Request</a>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <select class="select" style="width:190px" name="warehouse">
            <option value="">All Warehouses</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" @selected(request('warehouse') == $warehouse->id)>{{ $warehouse->name }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.reports.low-stock') }}">Reset</a>
            <button type="button" class="btn outline" onclick="window.print()">Export PDF</button>
        </x-slot:actions>
    </x-admin.filter-bar>

    <div class="card-grid">
        <x-admin.metric-card color="red" :value="$rows->count()" label="Low Stock Rows"/>
        <x-admin.metric-card color="yellow" :value="$outOfStock" label="Out Of Stock"/>
    </div>

    <x-admin.data-table title="Low Stock Items">
        <thead>
            <tr><th>Item</th><th>Warehouse</th><th>On Hand</th><th>Unit</th><th>Reorder Level</th><th>Minimum</th><th>Shortfall</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td><a href="{{ route('admin.inventory.items.show', $row->item) }}" style="color:var(--blue);font-weight:700">{{ $row->item->label() }}</a></td>
                    <td>{{ $row->warehouse->name }}</td>
                    <td>{{ rtrim(rtrim(number_format($row->quantity, 3), '0'), '.') }}</td>
                    <td>{{ $row->item->unit?->code ?? '-' }}</td>
                    <td>{{ rtrim(rtrim(number_format($row->item->reorder_level, 3), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($row->item->minimum_stock, 3), '0'), '.') }}</td>
                    <td><strong>{{ rtrim(rtrim(number_format(max((float) $row->item->reorder_level - (float) $row->quantity, 0), 3), '0'), '.') }}</strong></td>
                    <td><x-admin.status-badge :status="(float) $row->quantity <= 0 ? 'absent' : 'late'"/></td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No items are below their reorder level.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
