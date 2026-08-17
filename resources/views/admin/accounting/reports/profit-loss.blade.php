@extends('layouts.admin')

@section('title', 'Profit & Loss')
@section('breadcrumb', 'Accounting / Financial Reports / Profit &amp; Loss')

@section('content')
    <x-admin.page-header title="Profit &amp; Loss" description="Revenue less expenses for the selected period">
        <a class="btn outline" href="{{ route('admin.accounting.reports.index') }}">All Reports</a>
    </x-admin.page-header>

    @include('admin.accounting.reports._filters')

    <div class="card-grid">
        <x-admin.metric-card color="green" :value="'SAR '.number_format($totalRevenue, 2)" label="Total Revenue"/>
        <x-admin.metric-card color="red" :value="'SAR '.number_format($totalExpenses, 2)" label="Total Expenses"/>
        <x-admin.metric-card :color="$netProfit >= 0 ? 'green' : 'red'" :value="'SAR '.number_format($netProfit, 2)" label="Net Profit / Loss"/>
        <x-admin.metric-card color="cyan" :value="$totalRevenue > 0 ? number_format($netProfit / $totalRevenue * 100, 1).'%' : '-'" label="Net Margin"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Revenue">
            <thead>
                <tr><th>Code</th><th>Account</th><th>Amount</th></tr>
            </thead>
            <tbody>
                @forelse ($revenue as $row)
                    <tr>
                        <td>{{ $row['account_code'] }}</td>
                        <td>{{ $row['account_name'] }}</td>
                        <td>SAR {{ number_format($row['balance'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="table-empty">No revenue in this period.</td></tr>
                @endforelse
            </tbody>
            <x-slot:footer>
                <span><strong>Total Revenue</strong></span>
                <span><strong>SAR {{ number_format($totalRevenue, 2) }}</strong></span>
            </x-slot:footer>
        </x-admin.data-table>

        <x-admin.data-table title="Expenses">
            <thead>
                <tr><th>Code</th><th>Account</th><th>Amount</th></tr>
            </thead>
            <tbody>
                @forelse ($expenses as $row)
                    <tr>
                        <td>{{ $row['account_code'] }}</td>
                        <td>{{ $row['account_name'] }}</td>
                        <td>SAR {{ number_format($row['balance'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="table-empty">No expenses in this period.</td></tr>
                @endforelse
            </tbody>
            <x-slot:footer>
                <span><strong>Total Expenses</strong></span>
                <span><strong>SAR {{ number_format($totalExpenses, 2) }}</strong></span>
            </x-slot:footer>
        </x-admin.data-table>
    </div>

    <x-admin.data-table title="Result">
        <tbody>
            <tr><th>Total Revenue</th><td>SAR {{ number_format($totalRevenue, 2) }}</td></tr>
            <tr><th>Total Expenses</th><td>SAR {{ number_format($totalExpenses, 2) }}</td></tr>
            <tr><th>Net Profit / Loss</th><td><strong>SAR {{ number_format($netProfit, 2) }}</strong></td></tr>
        </tbody>
    </x-admin.data-table>
@endsection
