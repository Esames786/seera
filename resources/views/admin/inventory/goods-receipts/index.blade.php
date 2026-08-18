@extends('layouts.admin')

@section('title', 'Goods Receipt Notes')
@section('breadcrumb', 'Inventory / Goods Receipt Notes')

@section('content')
    <x-admin.page-header title="Goods Receipt Notes" description="Receiving against purchase orders. Posting a GRN increases stock and posts to accounting.">
        <a class="btn primary" href="{{ route('admin.inventory.goods-receipts.create') }}">+ Add Goods Receipt</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$draftCount" label="Draft GRNs"/>
        <x-admin.metric-card color="green" :value="$postedCount" label="Posted GRNs"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($receivedValue, 2)" label="Received Value"/>
        <x-admin.metric-card color="red" :value="$awaitingAccounting" label="Awaiting Accounting"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:230px" type="search" name="search" value="{{ request('search') }}" placeholder="GRN, delivery note or supplier..."/>
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
            <a class="btn outline" href="{{ route('admin.inventory.goods-receipts.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Goods Receipts Listing">
        <thead>
            <tr><th>GRN Number</th><th>PO</th><th>Supplier</th><th>Warehouse</th><th>Received</th><th>Lines</th><th>Total</th><th>Stock</th><th>Accounting</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($receipts as $grn)
                <tr>
                    <td><a href="{{ route('admin.inventory.goods-receipts.show', $grn) }}" style="color:var(--blue);font-weight:700">{{ $grn->grn_number }}</a></td>
                    <td>{{ $grn->purchaseOrder?->po_number ?? '-' }}</td>
                    <td>{{ $grn->supplier->name }}</td>
                    <td>{{ $grn->warehouse->name }}</td>
                    <td>{{ $grn->received_date->toDateString() }}</td>
                    <td>{{ $grn->lines_count }}</td>
                    <td><strong>SAR {{ number_format($grn->total_amount, 2) }}</strong></td>
                    <td><x-admin.status-badge :status="$grn->stock_updated ? 'yes' : 'no'"/></td>
                    <td><x-admin.status-badge :status="$grn->accounting_posted ? 'posted' : 'pending'"/></td>
                    <td><x-admin.status-badge :status="$grn->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.inventory.goods-receipts.show', $grn)"
                            :edit="$grn->isEditable() ? route('admin.inventory.goods-receipts.edit', $grn) : null"
                            :delete="$grn->isEditable() ? route('admin.inventory.goods-receipts.destroy', $grn) : null"
                            :name="$grn->grn_number">
                            @if ($grn->status === 'draft')
                                <form method="POST" action="{{ route('admin.inventory.goods-receipts.post-stock', $grn) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Post Stock</button>
                                </form>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">No goods receipts match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $receipts->firstItem() ?? 0 }}-{{ $receipts->lastItem() ?? 0 }} of {{ $receipts->total() }}</span>
            {{ $receipts->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    <div class="note">
        Posting a GRN debits the inventory asset and input VAT, credits accounts payable, and writes one stock ledger entry per accepted line.
    </div>
@endsection
