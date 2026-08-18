@extends('layouts.admin')

@section('title', 'Stock Adjustment')
@section('breadcrumb', 'Inventory / Stock Adjustments / Stock Adjustment')

@section('content')
    <x-admin.page-header :title="'Adjustment: '.$adjustment->adjustment_number" :description="$adjustment->item->label().' at '.$adjustment->warehouse->name">
        @if ($adjustment->status === 'draft')
            <a class="btn outline" href="{{ route('admin.inventory.stock-adjustments.edit', $adjustment) }}">Edit</a>
            <form method="POST" action="{{ route('admin.inventory.stock-adjustments.approve', $adjustment) }}">
                @csrf
                <button type="submit" class="btn primary">Approve</button>
            </form>
        @elseif ($adjustment->status === 'approved')
            <form method="POST" action="{{ route('admin.inventory.stock-adjustments.post', $adjustment) }}">
                @csrf
                <button type="submit" class="btn primary">Post Adjustment</button>
            </form>
        @endif
    </x-admin.page-header>

    @if ($adjustment->status === 'approved')
        <div class="alert flash">This adjustment is approved but not posted. Warehouse stock has not changed yet.</div>
    @elseif ($adjustment->status === 'posted')
        <div class="alert success flash">This adjustment is posted and read-only. Warehouse stock and the ledger have been updated.</div>
    @endif

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="rtrim(rtrim(number_format($adjustment->current_quantity, 3), '0'), '.')" label="System Quantity"/>
        <x-admin.metric-card color="cyan" :value="rtrim(rtrim(number_format($adjustment->adjusted_quantity, 3), '0'), '.')" label="Counted Quantity"/>
        <x-admin.metric-card :color="$adjustment->isLoss() ? 'red' : 'green'" :value="rtrim(rtrim(number_format($adjustment->difference_quantity, 3), '0'), '.')" label="Difference"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($adjustment->adjustment_value, 2)" label="Adjustment Value"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Adjustment Information" class="detail-table">
            <tbody>
                <tr><th>Adjustment Number</th><td>{{ $adjustment->adjustment_number }}</td></tr>
                <tr><th>Adjustment Date</th><td>{{ $adjustment->adjustment_date->toDateString() }}</td></tr>
                <tr><th>Warehouse</th><td>{{ $adjustment->warehouse->name }}</td></tr>
                <tr><th>Item</th><td>{{ $adjustment->item->label() }}</td></tr>
                <tr><th>Unit</th><td>{{ $adjustment->item->unit?->name ?? '-' }}</td></tr>
                <tr><th>Adjustment Type</th><td><span class="badge {{ $adjustment->isLoss() ? 'red' : 'green' }}">{{ ucfirst($adjustment->adjustment_type) }}</span></td></tr>
                <tr><th>Unit Cost</th><td>SAR {{ number_format($adjustment->unit_cost, 2) }}</td></tr>
                <tr><th>Reason</th><td>{{ $adjustment->reason ?? '-' }}</td></tr>
                <tr><th>Approved By</th><td>{{ $adjustment->approver?->name ?? '-' }}</td></tr>
                <tr><th>Approved At</th><td>{{ $adjustment->approved_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
                <tr><th>Accounting Posted</th><td><x-admin.status-badge :status="$adjustment->accounting_posted ? 'posted' : 'pending'"/></td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$adjustment->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <x-admin.data-table title="Current Warehouse Stock" class="detail-table">
                <tbody>
                    <tr><th>On Hand</th><td>{{ rtrim(rtrim(number_format($currentStock->quantity, 3), '0'), '.') }}</td></tr>
                    <tr><th>Reserved</th><td>{{ rtrim(rtrim(number_format($currentStock->reserved_quantity, 3), '0'), '.') }}</td></tr>
                    <tr><th>Average Cost</th><td>SAR {{ number_format($currentStock->average_cost, 2) }}</td></tr>
                    <tr><th>Stock Value</th><td>SAR {{ number_format($currentStock->total_value, 2) }}</td></tr>
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table title="Accounting Entry">
                @if ($adjustment->journalEntry)
                    <thead>
                        <tr><th>Account</th><th>Debit</th><th>Credit</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($adjustment->journalEntry->lines as $line)
                            <tr>
                                <td>{{ $line->account->label() }}</td>
                                <td>{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                                <td>{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <x-slot:footer>
                        <span class="small">Journal</span>
                        <a class="btn sm primary" href="{{ route('admin.accounting.journal-entries.show', $adjustment->journalEntry) }}">{{ $adjustment->journalEntry->journal_number }}</a>
                    </x-slot:footer>
                @else
                    <tbody>
                        <tr><td class="table-empty">No accounting entry yet. Post the adjustment to create it.</td></tr>
                    </tbody>
                @endif
            </x-admin.data-table>
        </div>
    </div>
@endsection
