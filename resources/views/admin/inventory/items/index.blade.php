@extends('layouts.admin')

@section('title', 'Materials / Items')
@section('breadcrumb', 'Inventory / Materials / Items')

@section('content')
    <x-admin.page-header title="Materials / Items" description="Item master with valuation, reorder levels and linked accounts">
        <a class="btn primary" href="{{ route('admin.inventory.items.create') }}">+ Add Item</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalItems" label="Total Items"/>
        <x-admin.metric-card color="green" :value="$activeItems" label="Active Items"/>
        <x-admin.metric-card color="red" :value="$lowStockCount" label="Low Stock Rows"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($stockValue, 2)" label="Stock Value"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Item code or name..."/>
        <select class="select" style="width:180px" name="category">
            <option value="">All Categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="unit">
            <option value="">All Units</option>
            @foreach ($units as $unit)
                <option value="{{ $unit->id }}" @selected(request('unit') == $unit->id)>{{ $unit->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            @foreach (['active', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="low_stock">
            <option value="">All Stock Levels</option>
            <option value="1" @selected(request('low_stock'))>Low stock only</option>
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.items.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Items Listing">
        <thead>
            <tr><th>Code</th><th>Item</th><th>Category</th><th>Unit</th><th>On Hand</th><th>Avg Cost</th><th>Stock Value</th><th>Reorder</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item->item_code }}</td>
                    <td><a href="{{ route('admin.inventory.items.show', $item) }}" style="color:var(--blue);font-weight:700">{{ $item->name }}</a></td>
                    <td>{{ $item->category?->name ?? '-' }}</td>
                    <td>{{ $item->unit?->code ?? '-' }}</td>
                    <td>
                        {{ rtrim(rtrim(number_format($item->on_hand ?? 0, 3), '0'), '.') }}
                        @if ((float) $item->reorder_level > 0 && (float) ($item->on_hand ?? 0) <= (float) $item->reorder_level)
                            <span class="badge red">Low</span>
                        @endif
                    </td>
                    <td>SAR {{ number_format($item->average_cost, 2) }}</td>
                    <td>SAR {{ number_format($item->stock_value ?? 0, 2) }}</td>
                    <td>{{ rtrim(rtrim(number_format($item->reorder_level, 3), '0'), '.') }}</td>
                    <td><x-admin.status-badge :status="$item->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.inventory.items.show', $item)"
                            :edit="route('admin.inventory.items.edit', $item)"
                            :delete="route('admin.inventory.items.destroy', $item)"
                            :name="$item->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">No items match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $items->firstItem() ?? 0 }}-{{ $items->lastItem() ?? 0 }} of {{ $items->total() }}</span>
            {{ $items->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
