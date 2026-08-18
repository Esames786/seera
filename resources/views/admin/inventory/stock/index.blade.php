@extends('layouts.admin')

@section('title', 'Stock On Hand')
@section('breadcrumb', 'Inventory / Stock On Hand')

@section('content')
    <x-admin.page-header title="Stock On Hand" description="Item balances per warehouse with average cost and stock value">
        <a class="btn outline" href="{{ route('admin.inventory.stock-ledger') }}">Stock Ledger</a>
        <a class="btn primary" href="{{ route('admin.inventory.reports.stock-valuation') }}">Valuation Report</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($totalValue, 2)" label="Total Stock Value"/>
        <x-admin.metric-card color="blue" :value="rtrim(rtrim(number_format($totalQuantity, 3), '0'), '.')" label="Total Quantity"/>
        <x-admin.metric-card color="green" :value="$stockedItems" label="Stocked Items"/>
        <x-admin.metric-card color="red" :value="$lowStockCount" label="Low Stock Rows"/>
    </div>

    <x-admin.data-table title="Warehouse Stock Summary">
        <thead>
            <tr><th>Warehouse</th><th>Stocked Items</th><th>Total Quantity</th><th>Stock Value</th></tr>
        </thead>
        <tbody>
            @forelse ($warehouseSummary as $warehouse)
                <tr>
                    <td>{{ $warehouse->name }}</td>
                    <td>{{ $warehouse->stocks_count }}</td>
                    <td>{{ rtrim(rtrim(number_format($warehouse->stock_quantity ?? 0, 3), '0'), '.') }}</td>
                    <td>SAR {{ number_format($warehouse->stock_value ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="table-empty">No warehouses yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Item code or name..."/>
        <select class="select" style="width:180px" name="warehouse">
            <option value="">All Warehouses</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" @selected(request('warehouse') == $warehouse->id)>{{ $warehouse->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:180px" name="category">
            <option value="">All Categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="low_stock">
            <option value="">All Stock Levels</option>
            <option value="1" @selected(request('low_stock'))>Low stock only</option>
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.stock.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Stock On Hand">
        <thead>
            <tr><th>Item</th><th>Warehouse</th><th>Project / Site</th><th>On Hand</th><th>Reserved</th><th>Available</th><th>Reorder</th><th>Avg Cost</th><th>Stock Value</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($stocks as $stock)
                <tr>
                    <td><a href="{{ route('admin.inventory.items.show', $stock->item) }}" style="color:var(--blue);font-weight:700">{{ $stock->item->label() }}</a></td>
                    <td>{{ $stock->warehouse->name }}</td>
                    <td>{{ $stock->warehouse->project?->name ?? 'Head Office' }}@if($stock->warehouse->site) / {{ $stock->warehouse->site->name }}@endif</td>
                    <td>{{ rtrim(rtrim(number_format($stock->quantity, 3), '0'), '.') }} {{ $stock->item->unit?->code }}</td>
                    <td>{{ rtrim(rtrim(number_format($stock->reserved_quantity, 3), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($stock->availableQuantity(), 3), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($stock->item->reorder_level, 3), '0'), '.') }}</td>
                    <td>SAR {{ number_format($stock->average_cost, 2) }}</td>
                    <td>SAR {{ number_format($stock->total_value, 2) }}</td>
                    <td><x-admin.status-badge :status="$stock->isLowStock() ? 'late' : 'present'"/></td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">No stock rows match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $stocks->firstItem() ?? 0 }}-{{ $stocks->lastItem() ?? 0 }} of {{ $stocks->total() }}</span>
            {{ $stocks->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
