@extends('layouts.admin')

@section('title', 'Stock Transfers')
@section('breadcrumb', 'Inventory / Stock Transfers')

@section('content')
    <x-admin.page-header title="Stock Transfers" description="Move stock between warehouses. Dispatch removes from the source, receive adds to the destination.">
        <a class="btn primary" href="{{ route('admin.inventory.stock-transfers.create') }}">+ Add Stock Transfer</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$draftCount" label="Draft Transfers"/>
        <x-admin.metric-card color="blue" :value="$inTransitCount" label="In Transit"/>
        <x-admin.metric-card color="green" :value="$receivedCount" label="Received"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($transferValue, 2)" label="Transferred Value"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Transfer number..."/>
        <select class="select" style="width:190px" name="from_warehouse">
            <option value="">All Source Warehouses</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" @selected(request('from_warehouse') == $warehouse->id)>{{ $warehouse->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.stock-transfers.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Stock Transfers Listing">
        <thead>
            <tr><th>Transfer</th><th>Date</th><th>From</th><th>To</th><th>Lines</th><th>Value</th><th>Dispatched</th><th>Received</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($transfers as $transfer)
                <tr>
                    <td><a href="{{ route('admin.inventory.stock-transfers.show', $transfer) }}" style="color:var(--blue);font-weight:700">{{ $transfer->transfer_number }}</a></td>
                    <td>{{ $transfer->transfer_date->toDateString() }}</td>
                    <td>{{ $transfer->fromWarehouse->name }}</td>
                    <td>{{ $transfer->toWarehouse->name }}</td>
                    <td>{{ $transfer->lines_count }}</td>
                    <td><strong>SAR {{ number_format($transfer->total_cost, 2) }}</strong></td>
                    <td>{{ $transfer->dispatch_date?->toDateString() ?? '-' }}</td>
                    <td>{{ $transfer->receive_date?->toDateString() ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$transfer->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.inventory.stock-transfers.show', $transfer)"
                            :edit="$transfer->isEditable() ? route('admin.inventory.stock-transfers.edit', $transfer) : null"
                            :delete="$transfer->isEditable() ? route('admin.inventory.stock-transfers.destroy', $transfer) : null"
                            :name="$transfer->transfer_number">
                            @if ($transfer->status === 'draft')
                                <form method="POST" action="{{ route('admin.inventory.stock-transfers.dispatch', $transfer) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Dispatch</button>
                                </form>
                            @elseif ($transfer->status === 'dispatched')
                                <form method="POST" action="{{ route('admin.inventory.stock-transfers.receive', $transfer) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Receive</button>
                                </form>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">No stock transfers match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $transfers->firstItem() ?? 0 }}-{{ $transfers->lastItem() ?? 0 }} of {{ $transfers->total() }}</span>
            {{ $transfers->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    <div class="note">
        Stock leaves the source warehouse at dispatch and arrives at the destination on receive, at the dispatched unit cost. Both steps write stock ledger entries.
    </div>
@endsection
