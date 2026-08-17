@extends('layouts.admin')

@section('title', 'Balance Sheet')
@section('breadcrumb', 'Accounting / Financial Reports / Balance Sheet')

@section('content')
    <x-admin.page-header title="Balance Sheet" description="Assets, liabilities and equity from posted journal entries">
        <a class="btn outline" href="{{ route('admin.accounting.reports.index') }}">All Reports</a>
    </x-admin.page-header>

    @include('admin.accounting.reports._filters')

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($totalAssets, 2)" label="Total Assets"/>
        <x-admin.metric-card color="red" :value="'SAR '.number_format($totalLiabilities, 2)" label="Total Liabilities"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($totalEquity, 2)" label="Total Equity"/>
        <x-admin.metric-card :color="$netProfit >= 0 ? 'green' : 'red'" :value="'SAR '.number_format($netProfit, 2)" label="Current Period Result"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Assets">
            <thead>
                <tr><th>Code</th><th>Account</th><th>Balance</th></tr>
            </thead>
            <tbody>
                @forelse ($assets as $row)
                    <tr>
                        <td>{{ $row['account_code'] }}</td>
                        <td>{{ $row['account_name'] }}</td>
                        <td>SAR {{ number_format($row['balance'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="table-empty">No asset movement in this period.</td></tr>
                @endforelse
            </tbody>
            <x-slot:footer>
                <span><strong>Total Assets</strong></span>
                <span><strong>SAR {{ number_format($totalAssets, 2) }}</strong></span>
            </x-slot:footer>
        </x-admin.data-table>

        <div>
            <x-admin.data-table title="Liabilities">
                <thead>
                    <tr><th>Code</th><th>Account</th><th>Balance</th></tr>
                </thead>
                <tbody>
                    @forelse ($liabilities as $row)
                        <tr>
                            <td>{{ $row['account_code'] }}</td>
                            <td>{{ $row['account_name'] }}</td>
                            <td>SAR {{ number_format($row['balance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="table-empty">No liability movement in this period.</td></tr>
                    @endforelse
                </tbody>
                <x-slot:footer>
                    <span><strong>Total Liabilities</strong></span>
                    <span><strong>SAR {{ number_format($totalLiabilities, 2) }}</strong></span>
                </x-slot:footer>
            </x-admin.data-table>

            <x-admin.data-table title="Equity">
                <thead>
                    <tr><th>Code</th><th>Account</th><th>Balance</th></tr>
                </thead>
                <tbody>
                    @forelse ($equity as $row)
                        <tr>
                            <td>{{ $row['account_code'] }}</td>
                            <td>{{ $row['account_name'] }}</td>
                            <td>SAR {{ number_format($row['balance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="table-empty">No equity movement in this period.</td></tr>
                    @endforelse
                    <tr>
                        <td>-</td>
                        <td>Current Period Profit / Loss</td>
                        <td>SAR {{ number_format($netProfit, 2) }}</td>
                    </tr>
                </tbody>
                <x-slot:footer>
                    <span><strong>Total Equity</strong></span>
                    <span><strong>SAR {{ number_format($totalEquity + $netProfit, 2) }}</strong></span>
                </x-slot:footer>
            </x-admin.data-table>
        </div>
    </div>

    <div class="note">
        This phase reports movement from posted journal entries. Opening balances entered on the chart of accounts are shown on each account screen and are folded into the general ledger running balance, not into this movement report.
    </div>
@endsection
