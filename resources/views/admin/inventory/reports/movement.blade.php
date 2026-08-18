@extends('layouts.admin')

@section('title', 'Stock Movement Report')
@section('breadcrumb', 'Inventory / Reports / Stock Movement')

@section('content')
    <x-admin.page-header title="Stock Movement Report" description="Total in, total out and movement value per item">
        <a class="btn outline" href="{{ route('admin.inventory.stock-ledger') }}">Stock Ledger</a>
        <a class="btn outline" href="{{ route('admin.inventory.reports.index') }}">All Reports</a>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <input class="input" style="width:150px" type="date" name="from" value="{{ request('from') }}"/>
        <input class="input" style="width:150px" type="date" name="to" value="{{ request('to') }}"/>
        <select class="select" style="width:190px" name="warehouse">
            <option value="">All Warehouses</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" @selected(request('warehouse') == $warehouse->id)>{{ $warehouse->name }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.reports.movement') }}">Reset</a>
            <button type="button" class="btn outline" onclick="window.print()">Export PDF</button>
        </x-slot:actions>
    </x-admin.filter-bar>

    <div class="card-grid">
        <x-admin.metric-card color="green" :value="rtrim(rtrim(number_format($totalIn, 3), '0'), '.')" label="Total In"/>
        <x-admin.metric-card color="red" :value="rtrim(rtrim(number_format($totalOut, 3), '0'), '.')" label="Total Out"/>
        <x-admin.metric-card color="blue" :value="$rows->count()" label="Items Moved"/>
    </div>

    <x-admin.data-table title="Movement By Item">
        <thead>
            <tr><th>Code</th><th>Item</th><th>Total In</th><th>Total Out</th><th>Net Movement</th><th>Movement Value</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->item_code }}</td>
                    <td>{{ $row->item_name }}</td>
                    <td>{{ rtrim(rtrim(number_format($row->total_in, 3), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($row->total_out, 3), '0'), '.') }}</td>
                    <td><strong>{{ rtrim(rtrim(number_format((float) $row->total_in - (float) $row->total_out, 3), '0'), '.') }}</strong></td>
                    <td>SAR {{ number_format($row->total_value, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No stock movement in this period.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
