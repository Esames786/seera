@extends('layouts.admin')

@section('title', 'Trial Balance')
@section('breadcrumb', 'Accounting / Financial Reports / Trial Balance')

@section('content')
    <x-admin.page-header title="Trial Balance" description="Debit and credit balance per account from posted journal entries">
        <a class="btn outline" href="{{ route('admin.accounting.reports.index') }}">All Reports</a>
    </x-admin.page-header>

    @include('admin.accounting.reports._filters')

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($totalDebit, 2)" label="Total Debit"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($totalCredit, 2)" label="Total Credit"/>
        <x-admin.metric-card :color="abs($totalDebit - $totalCredit) < 0.01 ? 'green' : 'red'" :value="abs($totalDebit - $totalCredit) < 0.01 ? 'Balanced' : 'Out of balance'" label="Balance Check"/>
        <x-admin.metric-card color="yellow" :value="$rows->count()" label="Accounts With Movement"/>
    </div>

    <x-admin.data-table title="Trial Balance">
        <thead>
            <tr><th>Code</th><th>Account</th><th>Type</th><th>Debit Balance</th><th>Credit Balance</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['account_code'] }}</td>
                    <td>{{ $row['account_name'] }}</td>
                    <td>{{ ucfirst($row['account_type']) }}</td>
                    <td>{{ $row['debit_balance'] > 0 ? number_format($row['debit_balance'], 2) : '-' }}</td>
                    <td>{{ $row['credit_balance'] > 0 ? number_format($row['credit_balance'], 2) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="table-empty">No posted movement in this period.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span><strong>Totals</strong></span>
            <span><strong>Debit SAR {{ number_format($totalDebit, 2) }} &nbsp; | &nbsp; Credit SAR {{ number_format($totalCredit, 2) }}</strong></span>
        </x-slot:footer>
    </x-admin.data-table>

    <div class="note">
        Total debit balances must equal total credit balances. A mismatch means an unbalanced journal was posted, which the posting rules are designed to prevent.
    </div>
@endsection
