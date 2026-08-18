@extends('layouts.admin')

@section('title', 'Item Details')
@section('breadcrumb', 'Inventory / Materials / Item Details')

@section('content')
    <x-admin.page-header :title="$item->label()" :description="$item->category?->name ?? 'Uncategorised material'">
        <a class="btn outline" href="{{ route('admin.inventory.stock-ledger', ['item' => $item->id]) }}">Stock Ledger</a>
        <a class="btn primary" href="{{ route('admin.inventory.items.edit', $item) }}">Edit Item</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="rtrim(rtrim(number_format($onHand, 3), '0'), '.').' '.($item->unit?->code ?? '')" label="On Hand"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($item->average_cost, 2)" label="Average Cost"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($stockValue, 2)" label="Stock Value"/>
        <x-admin.metric-card :color="$item->isLowStock() ? 'red' : 'green'" :value="$item->isLowStock() ? 'Low' : 'OK'" label="Stock Level"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Item Information" class="detail-table">
            <tbody>
                <tr><th>Item Code</th><td>{{ $item->item_code }}</td></tr>
                <tr><th>Category</th><td>{{ $item->category?->name ?? '-' }}</td></tr>
                <tr><th>Unit</th><td>{{ $item->unit?->name ?? '-' }}</td></tr>
                <tr><th>Valuation Method</th><td>{{ strtoupper($item->valuation_method) }}</td></tr>
                <tr><th>Reorder Level</th><td>{{ rtrim(rtrim(number_format($item->reorder_level, 3), '0'), '.') }}</td></tr>
                <tr><th>Minimum / Maximum</th><td>{{ rtrim(rtrim(number_format($item->minimum_stock, 3), '0'), '.') }} / {{ rtrim(rtrim(number_format($item->maximum_stock, 3), '0'), '.') }}</td></tr>
                <tr><th>Preferred Supplier</th><td>{{ $item->preferredSupplier?->name ?? '-' }}</td></tr>
                <tr><th>Inventory Account</th><td>{{ $item->inventoryAccount?->label() ?? 'Default inventory asset' }}</td></tr>
                <tr><th>Expense Account</th><td>{{ $item->expenseAccount?->label() ?? 'Default material expense' }}</td></tr>
                <tr><th>VAT Applicable</th><td>{{ $item->vat_applicable ? 'Yes' : 'No' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$item->status"/></td></tr>
                <tr><th>Description</th><td>{{ $item->description ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Stock By Warehouse">
            <thead>
                <tr><th>Warehouse</th><th>On Hand</th><th>Reserved</th><th>Available</th><th>Avg Cost</th><th>Value</th></tr>
            </thead>
            <tbody>
                @forelse ($stocks as $stock)
                    <tr>
                        <td>{{ $stock->warehouse->name }}</td>
                        <td>{{ rtrim(rtrim(number_format($stock->quantity, 3), '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim(number_format($stock->reserved_quantity, 3), '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim(number_format($stock->availableQuantity(), 3), '0'), '.') }}</td>
                        <td>SAR {{ number_format($stock->average_cost, 2) }}</td>
                        <td>SAR {{ number_format($stock->total_value, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="table-empty">This item is not stocked in any warehouse yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    </div>

    <x-admin.data-table title="Recent Stock Movement">
        <thead>
            <tr><th>Date</th><th>Reference</th><th>Type</th><th>Warehouse</th><th>In</th><th>Out</th><th>Balance</th><th>Unit Cost</th><th>Value</th></tr>
        </thead>
        <tbody>
            @forelse ($movements as $entry)
                <tr>
                    <td>{{ $entry->movement_date->toDateString() }}</td>
                    <td>{{ $entry->reference_number ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$entry->movement_type"/></td>
                    <td>{{ $entry->warehouse->name }}</td>
                    <td>{{ (float) $entry->in_quantity > 0 ? rtrim(rtrim(number_format($entry->in_quantity, 3), '0'), '.') : '-' }}</td>
                    <td>{{ (float) $entry->out_quantity > 0 ? rtrim(rtrim(number_format($entry->out_quantity, 3), '0'), '.') : '-' }}</td>
                    <td>{{ rtrim(rtrim(number_format($entry->balance_quantity, 3), '0'), '.') }}</td>
                    <td>SAR {{ number_format($entry->unit_cost, 2) }}</td>
                    <td>SAR {{ number_format($entry->value, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No movement recorded for this item yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
