@extends('layouts.admin')

@section('title', 'Stock Transfer')
@section('breadcrumb', 'Inventory / Stock Transfers / Stock Transfer')

@section('content')
    <x-admin.page-header :title="'Stock Transfer: '.$transfer->transfer_number" :description="$transfer->fromWarehouse->name.' to '.$transfer->toWarehouse->name">
        @if ($transfer->isEditable())
            <a class="btn outline" href="{{ route('admin.inventory.stock-transfers.edit', $transfer) }}">Edit</a>
            <form method="POST" action="{{ route('admin.inventory.stock-transfers.dispatch', $transfer) }}">
                @csrf
                <button type="submit" class="btn primary">Dispatch Transfer</button>
            </form>
        @elseif ($transfer->status === 'dispatched')
            <form method="POST" action="{{ route('admin.inventory.stock-transfers.receive', $transfer) }}">
                @csrf
                <button type="submit" class="btn primary">Receive Transfer</button>
            </form>
        @endif
    </x-admin.page-header>

    @if ($transfer->status === 'dispatched')
        <div class="alert flash">Stock has left {{ $transfer->fromWarehouse->name }} and is in transit. Receive the transfer to add it to {{ $transfer->toWarehouse->name }}.</div>
    @elseif ($transfer->status === 'received')
        <div class="alert success flash">This transfer is complete and read-only. Stock is now in {{ $transfer->toWarehouse->name }}.</div>
    @endif

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$transfer->lines->count()" label="Transferred Lines"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($transfer->total_cost, 2)" label="Transfer Value"/>
        <x-admin.metric-card color="yellow" :value="$transfer->dispatch_date?->toDateString() ?? 'Not dispatched'" label="Dispatch Date"/>
        <x-admin.metric-card color="green" :value="$transfer->receive_date?->toDateString() ?? 'Not received'" label="Receive Date"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Transfer Information" class="detail-table">
            <tbody>
                <tr><th>Transfer Number</th><td>{{ $transfer->transfer_number }}</td></tr>
                <tr><th>Transfer Date</th><td>{{ $transfer->transfer_date->toDateString() }}</td></tr>
                <tr><th>From Warehouse</th><td>{{ $transfer->fromWarehouse->name }}</td></tr>
                <tr><th>To Warehouse</th><td>{{ $transfer->toWarehouse->name }}</td></tr>
                <tr><th>Requested By</th><td>{{ $transfer->requester?->name ?? '-' }}</td></tr>
                <tr><th>Dispatched By</th><td>{{ $transfer->dispatcher?->name ?? '-' }}</td></tr>
                <tr><th>Received By</th><td>{{ $transfer->receiver?->name ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$transfer->status"/></td></tr>
                <tr><th>Notes</th><td>{{ $transfer->notes ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Movement Trail">
            <thead>
                <tr><th>Step</th><th>Warehouse</th><th>Effect</th><th>Date</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Dispatch</td>
                    <td>{{ $transfer->fromWarehouse->name }}</td>
                    <td><span class="badge red">Stock out</span></td>
                    <td>{{ $transfer->dispatch_date?->toDateString() ?? 'Pending' }}</td>
                </tr>
                <tr>
                    <td>Receive</td>
                    <td>{{ $transfer->toWarehouse->name }}</td>
                    <td><span class="badge green">Stock in</span></td>
                    <td>{{ $transfer->receive_date?->toDateString() ?? 'Pending' }}</td>
                </tr>
            </tbody>
            <x-slot:footer>
                <span class="small">Ledger</span>
                <a class="btn sm primary" href="{{ route('admin.inventory.stock-ledger', ['movement_type' => 'transfer_out']) }}">Open Stock Ledger</a>
            </x-slot:footer>
        </x-admin.data-table>
    </div>

    <x-admin.data-table title="Transferred Items">
        <thead>
            <tr><th>Item</th><th>Unit</th><th>Quantity</th><th>Unit Cost</th><th>Total Cost</th></tr>
        </thead>
        <tbody>
            @forelse ($transfer->lines as $line)
                <tr>
                    <td>{{ $line->item->label() }}</td>
                    <td>{{ $line->item->unit?->code ?? '-' }}</td>
                    <td>{{ rtrim(rtrim(number_format($line->quantity, 3), '0'), '.') }}</td>
                    <td>SAR {{ number_format($line->unit_cost, 2) }}</td>
                    <td><strong>SAR {{ number_format($line->total_cost, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="5" class="table-empty">No lines on this transfer.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
