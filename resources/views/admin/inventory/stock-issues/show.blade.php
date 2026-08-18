@extends('layouts.admin')

@section('title', 'Stock Issue')
@section('breadcrumb', 'Inventory / Stock Issues / Stock Issue')

@section('content')
    <x-admin.page-header :title="'Stock Issue: '.$issue->issue_number" :description="$issue->warehouse->name.' to '.($issue->project?->name ?? 'internal use')">
        @if ($issue->isEditable())
            <a class="btn outline" href="{{ route('admin.inventory.stock-issues.edit', $issue) }}">Edit</a>
            <form method="POST" action="{{ route('admin.inventory.stock-issues.post', $issue) }}">
                @csrf
                <button type="submit" class="btn primary">Post Issue</button>
            </form>
        @endif
    </x-admin.page-header>

    @if ($issue->status === 'posted')
        <div class="alert success flash">This stock issue is posted and read-only. Warehouse stock has been reduced.</div>
    @endif

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$issue->lines->count()" label="Issued Lines"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($issue->total_cost, 2)" label="Total Cost"/>
        <x-admin.metric-card :color="$issue->accounting_posted ? 'green' : 'yellow'" :value="$issue->accounting_posted ? 'Posted' : 'Pending'" label="Accounting"/>
        <x-admin.metric-card color="green" :value="ucfirst($issue->status)" label="Status"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Issue Information" class="detail-table">
            <tbody>
                <tr><th>Issue Number</th><td>{{ $issue->issue_number }}</td></tr>
                <tr><th>Issue Date</th><td>{{ $issue->issue_date->toDateString() }}</td></tr>
                <tr><th>Warehouse</th><td>{{ $issue->warehouse->name }}</td></tr>
                <tr><th>Project / Site</th><td>{{ $issue->project?->name ?? '-' }}@if($issue->site) / {{ $issue->site->name }}@endif</td></tr>
                <tr><th>Requested By</th><td>{{ $issue->requester?->name ?? '-' }}</td></tr>
                <tr><th>Approved By</th><td>{{ $issue->approver?->name ?? '-' }}</td></tr>
                <tr><th>Purpose</th><td>{{ $issue->purpose ?? '-' }}</td></tr>
                <tr><th>Accounting Posted</th><td><x-admin.status-badge :status="$issue->accounting_posted ? 'posted' : 'pending'"/></td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$issue->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Accounting Entry">
            @if ($issue->journalEntry)
                <thead>
                    <tr><th>Account</th><th>Debit</th><th>Credit</th></tr>
                </thead>
                <tbody>
                    @foreach ($issue->journalEntry->lines as $line)
                        <tr>
                            <td>{{ $line->account->label() }}</td>
                            <td>{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                            <td>{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <x-slot:footer>
                    <span class="small">Journal</span>
                    <a class="btn sm primary" href="{{ route('admin.accounting.journal-entries.show', $issue->journalEntry) }}">{{ $issue->journalEntry->journal_number }}</a>
                </x-slot:footer>
            @else
                <tbody>
                    <tr><td class="table-empty">No accounting entry yet. Post the issue to create it.</td></tr>
                </tbody>
            @endif
        </x-admin.data-table>
    </div>

    <x-admin.data-table title="Issued Items">
        <thead>
            <tr><th>Item</th><th>Unit</th><th>Quantity</th><th>Unit Cost</th><th>Total Cost</th></tr>
        </thead>
        <tbody>
            @forelse ($issue->lines as $line)
                <tr>
                    <td>{{ $line->item->label() }}</td>
                    <td>{{ $line->item->unit?->code ?? '-' }}</td>
                    <td>{{ rtrim(rtrim(number_format($line->quantity, 3), '0'), '.') }}</td>
                    <td>SAR {{ number_format($line->unit_cost, 2) }}</td>
                    <td><strong>SAR {{ number_format($line->total_cost, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="5" class="table-empty">No lines on this stock issue.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
