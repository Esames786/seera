@extends('layouts.admin')

@section('title', 'Cash Flow')
@section('breadcrumb', 'Accounting / Financial Reports / Cash Flow')

@section('content')
    <x-admin.page-header title="Cash Flow" description="Movement across the cash in hand and bank accounts">
        <a class="btn outline" href="{{ route('admin.accounting.reports.index') }}">All Reports</a>
    </x-admin.page-header>

    @include('admin.accounting.reports._filters', ['showScope' => false])

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($openingCash, 2)" label="Opening Cash"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($cashIn, 2)" label="Cash In"/>
        <x-admin.metric-card color="red" :value="'SAR '.number_format($cashOut, 2)" label="Cash Out"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($closingCash, 2)" label="Closing Cash"/>
    </div>

    <x-admin.data-table title="Cash Flow Summary" class="detail-table">
        <tbody>
            <tr><th>Opening Cash</th><td>SAR {{ number_format($openingCash, 2) }}</td></tr>
            <tr><th>Cash In</th><td>SAR {{ number_format($cashIn, 2) }}</td></tr>
            <tr><th>Cash Out</th><td>SAR {{ number_format($cashOut, 2) }}</td></tr>
            <tr><th>Closing Cash</th><td><strong>SAR {{ number_format($closingCash, 2) }}</strong></td></tr>
        </tbody>
    </x-admin.data-table>

    <x-admin.data-table title="Recent Cash &amp; Bank Movements">
        <thead>
            <tr><th>Date</th><th>Journal</th><th>Account</th><th>Description</th><th>Source</th><th>Cash In</th><th>Cash Out</th></tr>
        </thead>
        <tbody>
            @forelse ($movements as $line)
                <tr>
                    <td>{{ $line->journalEntry->journal_date->toDateString() }}</td>
                    <td><a href="{{ route('admin.accounting.journal-entries.show', $line->journalEntry) }}" style="color:var(--blue);font-weight:700">{{ $line->journalEntry->journal_number }}</a></td>
                    <td>{{ $line->account->label() }}</td>
                    <td>{{ $line->description ?? $line->journalEntry->description ?? '-' }}</td>
                    <td>{{ $line->journalEntry->source_module }}</td>
                    <td>{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                    <td>{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty">No cash or bank movement in this period.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <div class="note">
        Opening cash is the sum of the opening balances on the cash and bank accounts. Bank reconciliation and cheque management arrive in a later phase.
    </div>
@endsection
