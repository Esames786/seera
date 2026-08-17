@extends('layouts.admin')

@section('title', 'Cost Center')
@section('breadcrumb', 'Accounting / Cost Centers / Cost Center')

@section('content')
    <x-admin.page-header :title="$costCenter->code.' - '.$costCenter->name" :description="ucfirst($costCenter->type).' cost center'">
        <a class="btn outline" href="{{ route('admin.accounting.general-ledger', ['cost_center' => $costCenter->id]) }}">Open in Ledger</a>
        <a class="btn primary" href="{{ route('admin.accounting.cost-centers.edit', $costCenter) }}">Edit Cost Center</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($totalDebit, 2)" label="Total Debit"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($totalCredit, 2)" label="Total Credit"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($totalDebit - $totalCredit, 2)" label="Net Cost"/>
        <x-admin.metric-card color="yellow" :value="ucfirst($costCenter->status)" label="Status"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Cost Center Information" class="detail-table">
            <tbody>
                <tr><th>Code</th><td>{{ $costCenter->code }}</td></tr>
                <tr><th>Name</th><td>{{ $costCenter->name }}</td></tr>
                <tr><th>Type</th><td>{{ ucfirst($costCenter->type) }}</td></tr>
                <tr><th>Linked Record</th><td>{{ $linkedRecord?->name ?? 'Not linked' }}</td></tr>
                <tr><th>Manager</th><td>{{ $costCenter->manager?->name ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$costCenter->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <div class="help-box">
            This cost center appears on journal lines, supplier bills and customer invoices. Deactivating it keeps existing postings intact but hides it from new transaction forms.
        </div>
    </div>

    <x-admin.data-table title="Recent Posted Lines">
        <thead>
            <tr><th>Date</th><th>Journal</th><th>Account</th><th>Description</th><th>Debit</th><th>Credit</th></tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $line->journalEntry->journal_date->toDateString() }}</td>
                    <td><a href="{{ route('admin.accounting.journal-entries.show', $line->journalEntry) }}" style="color:var(--blue);font-weight:700">{{ $line->journalEntry->journal_number }}</a></td>
                    <td>{{ $line->account->label() }}</td>
                    <td>{{ $line->description ?? '-' }}</td>
                    <td>{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                    <td>{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No posted journal lines against this cost center yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
