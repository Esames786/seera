@extends('layouts.admin')

@section('title', 'Account Details')
@section('breadcrumb', 'Accounting / Chart of Accounts / Account Details')

@section('content')
    <x-admin.page-header :title="$account->label()" :description="ucfirst($account->account_type).' account with a normal '.$account->normal_balance.' balance'">
        <a class="btn outline" href="{{ route('admin.accounting.general-ledger', ['account' => $account->id]) }}">Open in Ledger</a>
        <a class="btn primary" href="{{ route('admin.accounting.chart-of-accounts.edit', $account) }}">Edit Account</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($account->opening_balance, 2)" label="Opening Balance"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($balance, 2)" label="Posted Balance"/>
        <x-admin.metric-card color="yellow" :value="$account->vat_applicable ? 'Yes' : 'No'" label="VAT Applicable"/>
        <x-admin.metric-card color="cyan" :value="$account->children->count()" label="Sub-Accounts"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Account Information" class="detail-table">
            <tbody>
                <tr><th>Account Code</th><td>{{ $account->account_code }}</td></tr>
                <tr><th>Account Name</th><td>{{ $account->account_name }}</td></tr>
                <tr><th>Account Type</th><td>{{ ucfirst($account->account_type) }}</td></tr>
                <tr><th>Parent Account</th><td>{{ $account->parent?->label() ?? 'Top level' }}</td></tr>
                <tr><th>Normal Balance</th><td>{{ ucfirst($account->normal_balance) }}</td></tr>
                <tr><th>VAT Applicable</th><td>{{ $account->vat_applicable ? 'Yes' : 'No' }}</td></tr>
                <tr><th>Cost Center Required</th><td>{{ $account->cost_center_required ? 'Yes' : 'No' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$account->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Sub-Accounts">
            <thead>
                <tr><th>Code</th><th>Account</th><th>Type</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($account->children as $child)
                    <tr>
                        <td>{{ $child->account_code }}</td>
                        <td><a href="{{ route('admin.accounting.chart-of-accounts.show', $child) }}" style="color:var(--blue);font-weight:700">{{ $child->account_name }}</a></td>
                        <td>{{ ucfirst($child->account_type) }}</td>
                        <td><x-admin.status-badge :status="$child->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="table-empty">No sub-accounts.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    </div>

    <x-admin.data-table title="Recent Posted Transactions">
        <thead>
            <tr><th>Date</th><th>Journal</th><th>Description</th><th>Debit</th><th>Credit</th></tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $line->journalEntry->journal_date->toDateString() }}</td>
                    <td><a href="{{ route('admin.accounting.journal-entries.show', $line->journalEntry) }}" style="color:var(--blue);font-weight:700">{{ $line->journalEntry->journal_number }}</a></td>
                    <td>{{ $line->description ?? $line->journalEntry->description ?? '-' }}</td>
                    <td>SAR {{ number_format($line->debit, 2) }}</td>
                    <td>SAR {{ number_format($line->credit, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="table-empty">No posted transactions on this account yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
