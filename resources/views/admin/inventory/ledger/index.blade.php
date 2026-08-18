@extends('layouts.admin')

@section('title', 'Stock Ledger')
@section('breadcrumb', 'Inventory / Stock Ledger')

@section('content')
    <x-admin.page-header title="Stock Ledger" description="Every warehouse movement in date order, from receipts, issues, transfers and adjustments">
        <a class="btn outline" href="{{ route('admin.inventory.stock.index') }}">Stock On Hand</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="green" :value="rtrim(rtrim(number_format($totalIn, 3), '0'), '.')" label="Total In"/>
        <x-admin.metric-card color="red" :value="rtrim(rtrim(number_format($totalOut, 3), '0'), '.')" label="Total Out"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($totalValue, 2)" label="Movement Value"/>
        <x-admin.metric-card color="blue" :value="$entries->total()" label="Ledger Entries"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:150px" type="date" name="from" value="{{ request('from') }}"/>
        <input class="input" style="width:150px" type="date" name="to" value="{{ request('to') }}"/>
        <select class="select" style="width:200px" name="item">
            <option value="">All Items</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" @selected(request('item') == $item->id)>{{ $item->label() }}</option>
            @endforeach
        </select>
        <select class="select" style="width:170px" name="warehouse">
            <option value="">All Warehouses</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" @selected(request('warehouse') == $warehouse->id)>{{ $warehouse->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="movement_type">
            <option value="">All Movements</option>
            @foreach ($movementTypes as $type)
                <option value="{{ $type }}" @selected(request('movement_type') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="project">
            <option value="">All Projects</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="site">
            <option value="">All Sites</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}" @selected(request('site') == $site->id)>{{ $site->name }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.stock-ledger') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Stock Ledger Entries">
        <thead>
            <tr><th>Date</th><th>Reference</th><th>Movement</th><th>Item</th><th>Warehouse</th><th>In Qty</th><th>Out Qty</th><th>Balance</th><th>Unit Cost</th><th>Value</th><th>Project / Site</th></tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->movement_date->toDateString() }}</td>
                    <td>{{ $entry->reference_number ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$entry->movement_type"/></td>
                    <td><a href="{{ route('admin.inventory.items.show', $entry->item) }}" style="color:var(--blue);font-weight:700">{{ $entry->item->label() }}</a></td>
                    <td>{{ $entry->warehouse->name }}</td>
                    <td>{{ (float) $entry->in_quantity > 0 ? rtrim(rtrim(number_format($entry->in_quantity, 3), '0'), '.') : '-' }}</td>
                    <td>{{ (float) $entry->out_quantity > 0 ? rtrim(rtrim(number_format($entry->out_quantity, 3), '0'), '.') : '-' }}</td>
                    <td><strong>{{ rtrim(rtrim(number_format($entry->balance_quantity, 3), '0'), '.') }}</strong></td>
                    <td>SAR {{ number_format($entry->unit_cost, 2) }}</td>
                    <td>SAR {{ number_format($entry->value, 2) }}</td>
                    <td>{{ $entry->project?->name ?? '-' }}@if($entry->site) / {{ $entry->site->name }}@endif</td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">No stock movement matches the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $entries->firstItem() ?? 0 }}-{{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }}</span>
            {{ $entries->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
