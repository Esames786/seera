@extends('layouts.admin')

@section('title', 'Stock Valuation')
@section('breadcrumb', 'Inventory / Reports / Stock Valuation')

@section('content')
    <x-admin.page-header title="Stock Valuation Report" description="Quantity, average cost and value per item and warehouse">
        <a class="btn outline" href="{{ route('admin.inventory.reports.index') }}">All Reports</a>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <select class="select" style="width:190px" name="warehouse">
            <option value="">All Warehouses</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" @selected(request('warehouse') == $warehouse->id)>{{ $warehouse->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:190px" name="category">
            <option value="">All Categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.reports.stock-valuation') }}">Reset</a>
            <button type="button" class="btn outline" onclick="window.print()">Export PDF</button>
        </x-slot:actions>
    </x-admin.filter-bar>

    <div class="card-grid">
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($totalValue, 2)" label="Total Stock Value"/>
        <x-admin.metric-card color="blue" :value="rtrim(rtrim(number_format($totalQuantity, 3), '0'), '.')" label="Total Quantity"/>
        <x-admin.metric-card color="green" :value="$rows->count()" label="Stock Rows"/>
    </div>

    <x-admin.data-table title="Stock Valuation">
        <thead>
            <tr><th>Item</th><th>Category</th><th>Warehouse</th><th>Quantity</th><th>Unit</th><th>Average Cost</th><th>Stock Value</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->item->label() }}</td>
                    <td>{{ $row->item->category?->name ?? '-' }}</td>
                    <td>{{ $row->warehouse->name }}</td>
                    <td>{{ rtrim(rtrim(number_format($row->quantity, 3), '0'), '.') }}</td>
                    <td>{{ $row->item->unit?->code ?? '-' }}</td>
                    <td>SAR {{ number_format($row->average_cost, 2) }}</td>
                    <td><strong>SAR {{ number_format($row->total_value, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty">No stock to value for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span><strong>Total</strong></span>
            <span><strong>SAR {{ number_format($totalValue, 2) }}</strong></span>
        </x-slot:footer>
    </x-admin.data-table>
@endsection
