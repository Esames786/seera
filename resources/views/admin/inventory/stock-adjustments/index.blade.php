@extends('layouts.admin')

@section('title', 'Stock Adjustments')
@section('breadcrumb', 'Inventory / Stock Adjustments')

@section('content')
    <x-admin.page-header title="Stock Adjustments" description="Correct a warehouse balance after a physical count. Stock only changes once an approved adjustment is posted.">
        <a class="btn primary" href="{{ route('admin.inventory.stock-adjustments.create') }}">+ Add Stock Adjustment</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$draftCount" label="Draft Adjustments"/>
        <x-admin.metric-card color="blue" :value="$approvedCount" label="Approved, Not Posted"/>
        <x-admin.metric-card color="green" :value="$postedCount" label="Posted"/>
        <x-admin.metric-card color="red" :value="'SAR '.number_format($lossValue, 2)" label="Posted Loss Value"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:230px" type="search" name="search" value="{{ request('search') }}" placeholder="Adjustment number or item..."/>
        <select class="select" style="width:180px" name="warehouse">
            <option value="">All Warehouses</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" @selected(request('warehouse') == $warehouse->id)>{{ $warehouse->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.stock-adjustments.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Stock Adjustments Listing">
        <thead>
            <tr><th>Adjustment</th><th>Date</th><th>Warehouse</th><th>Item</th><th>Current</th><th>Adjusted</th><th>Difference</th><th>Value</th><th>Accounting</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($adjustments as $adjustment)
                <tr>
                    <td><a href="{{ route('admin.inventory.stock-adjustments.show', $adjustment) }}" style="color:var(--blue);font-weight:700">{{ $adjustment->adjustment_number }}</a></td>
                    <td>{{ $adjustment->adjustment_date->toDateString() }}</td>
                    <td>{{ $adjustment->warehouse->name }}</td>
                    <td>{{ $adjustment->item->label() }}</td>
                    <td>{{ rtrim(rtrim(number_format($adjustment->current_quantity, 3), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($adjustment->adjusted_quantity, 3), '0'), '.') }}</td>
                    <td>
                        <strong>{{ rtrim(rtrim(number_format($adjustment->difference_quantity, 3), '0'), '.') }}</strong>
                        <span class="badge {{ $adjustment->isLoss() ? 'red' : 'green' }}">{{ $adjustment->isLoss() ? 'Loss' : 'Gain' }}</span>
                    </td>
                    <td>SAR {{ number_format($adjustment->adjustment_value, 2) }}</td>
                    <td><x-admin.status-badge :status="$adjustment->accounting_posted ? 'posted' : 'pending'"/></td>
                    <td><x-admin.status-badge :status="$adjustment->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.inventory.stock-adjustments.show', $adjustment)"
                            :edit="$adjustment->isEditable() ? route('admin.inventory.stock-adjustments.edit', $adjustment) : null"
                            :delete="$adjustment->isEditable() ? route('admin.inventory.stock-adjustments.destroy', $adjustment) : null"
                            :name="$adjustment->adjustment_number">
                            @if ($adjustment->status === 'draft')
                                <form method="POST" action="{{ route('admin.inventory.stock-adjustments.approve', $adjustment) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Approve</button>
                                </form>
                            @elseif ($adjustment->status === 'approved')
                                <form method="POST" action="{{ route('admin.inventory.stock-adjustments.post', $adjustment) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Post</button>
                                </form>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">No stock adjustments match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $adjustments->firstItem() ?? 0 }}-{{ $adjustments->lastItem() ?? 0 }} of {{ $adjustments->total() }}</span>
            {{ $adjustments->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    <div class="note">
        A posted loss debits inventory adjustment expense and credits the inventory asset. A gain reverses those two sides.
    </div>
@endsection
