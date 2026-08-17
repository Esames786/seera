@extends('layouts.admin')

@section('title', 'Journal Entry')
@section('breadcrumb', 'Accounting / Journal Entries / Journal Entry')

@section('content')
    <x-admin.page-header :title="'Journal Entry: '.$entry->journal_number" :description="$entry->source_module.' · '.$entry->journal_date->toDateString()">
        @if ($entry->isEditable())
            <a class="btn outline" href="{{ route('admin.accounting.journal-entries.edit', $entry) }}">Edit</a>
            <form method="POST" action="{{ route('admin.accounting.journal-entries.post', $entry) }}">
                @csrf
                <button type="submit" class="btn primary">Post to Ledger</button>
            </form>
            <form method="POST" action="{{ route('admin.accounting.journal-entries.cancel', $entry) }}">
                @csrf
                <button type="submit" class="btn danger">Cancel Entry</button>
            </form>
        @endif
    </x-admin.page-header>

    @if (! $entry->isBalanced())
        <div class="alert flash">
            This journal is out of balance by SAR {{ number_format(abs((float) $entry->total_debit - (float) $entry->total_credit), 2) }} and cannot be posted.
        </div>
    @endif

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($entry->total_debit, 2)" label="Total Debit"/>
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($entry->total_credit, 2)" label="Total Credit"/>
        <x-admin.metric-card :color="$entry->isBalanced() ? 'green' : 'red'" :value="$entry->isBalanced() ? 'Balanced' : 'Out of balance'" label="Balance Check"/>
        <x-admin.metric-card color="cyan" :value="$entry->lines->count()" label="Journal Lines"/>
    </div>

    <x-admin.data-table title="Journal Information" class="detail-table">
        <tbody>
            <tr><th>Journal Number</th><td>{{ $entry->journal_number }}</td></tr>
            <tr><th>Journal Date</th><td>{{ $entry->journal_date->toDateString() }}</td></tr>
            <tr><th>Reference Number</th><td>{{ $entry->reference_number ?? '-' }}</td></tr>
            <tr><th>Source Module</th><td>{{ $entry->source_module }}</td></tr>
            <tr><th>Cost Center</th><td>{{ $entry->costCenter ? $entry->costCenter->code.' - '.$entry->costCenter->name : '-' }}</td></tr>
            <tr><th>Description</th><td>{{ $entry->description ?? '-' }}</td></tr>
            <tr><th>Status</th><td><x-admin.status-badge :status="$entry->status"/></td></tr>
            <tr><th>Created By</th><td>{{ $entry->creator?->name ?? 'System' }}</td></tr>
            <tr><th>Posted By</th><td>{{ $entry->poster?->name ?? '-' }}</td></tr>
            <tr><th>Posted At</th><td>{{ $entry->posted_at?->format('Y-m-d H:i') ?? 'Not posted' }}</td></tr>
        </tbody>
    </x-admin.data-table>

    <x-admin.data-table title="Journal Lines">
        <thead>
            <tr><th>Account</th><th>Description</th><th>Cost Center</th><th>Project</th><th>Site</th><th>Debit</th><th>Credit</th></tr>
        </thead>
        <tbody>
            @forelse ($entry->lines as $line)
                <tr>
                    <td><a href="{{ route('admin.accounting.chart-of-accounts.show', $line->account) }}" style="color:var(--blue);font-weight:700">{{ $line->account->label() }}</a></td>
                    <td>{{ $line->description ?? '-' }}</td>
                    <td>{{ $line->costCenter?->code ?? '-' }}</td>
                    <td>{{ $line->project?->name ?? '-' }}</td>
                    <td>{{ $line->site?->name ?? '-' }}</td>
                    <td>{{ (float) $line->debit > 0 ? 'SAR '.number_format($line->debit, 2) : '-' }}</td>
                    <td>{{ (float) $line->credit > 0 ? 'SAR '.number_format($line->credit, 2) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty">No lines on this journal entry.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Total</span>
            <span><strong>Debit SAR {{ number_format($entry->total_debit, 2) }} &nbsp; | &nbsp; Credit SAR {{ number_format($entry->total_credit, 2) }}</strong></span>
        </x-slot:footer>
    </x-admin.data-table>
@endsection
