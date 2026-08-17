@extends('layouts.admin')

@section('title', 'Accounting Dashboard')
@section('breadcrumb', 'Accounting / Accounting Dashboard')

@section('content')
    <x-admin.page-header title="Accounting Dashboard" description="Cash, payables, receivables, VAT, unposted journals and ZATCA clearance at a glance">
        <a class="btn outline" href="{{ route('admin.accounting.journal-entries.create') }}">+ Journal Entry</a>
        <a class="btn primary" href="{{ route('admin.accounting.reports.index') }}">Financial Reports</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="green" :value="'SAR '.number_format($cashBalance, 2)" label="Cash / Bank Balance"/>
        <x-admin.metric-card color="red" :value="'SAR '.number_format($payableBalance, 2)" label="Accounts Payable"/>
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($receivableBalance, 2)" label="Accounts Receivable"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($vatPayable, 2)" label="VAT Payable"/>
    </div>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$unpostedJournals" label="Unposted Journals"/>
        <x-admin.metric-card color="red" :value="$zatcaFailed" label="ZATCA Failed Invoices"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($monthlyRevenue, 2)" label="Monthly Revenue"/>
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($monthlyExpenses, 2)" label="Monthly Expenses"/>
    </div>

    <div class="split even">
        <div>
            <x-admin.data-table title="Profit &amp; Expense Trend" subtitle="Current month">
                <thead>
                    <tr><th>Measure</th><th>Amount</th></tr>
                </thead>
                <tbody>
                    <tr><td>Revenue</td><td>SAR {{ number_format($monthlyRevenue, 2) }}</td></tr>
                    <tr><td>Expenses</td><td>SAR {{ number_format($monthlyExpenses, 2) }}</td></tr>
                    <tr><td><strong>Net Profit / Loss</strong></td><td><strong>SAR {{ number_format($monthlyRevenue - $monthlyExpenses, 2) }}</strong></td></tr>
                </tbody>
            </x-admin.data-table>

            <div class="chart-placeholder">Chart Placeholder: Revenue vs Expense trend</div>
        </div>

        <x-admin.data-table title="Finance Action Queue">
            <thead>
                <tr><th>Queue</th><th>Count</th><th></th></tr>
            </thead>
            <tbody>
                <tr><td>Unposted Journals</td><td>{{ $unpostedJournals }}</td><td><a class="btn sm" href="{{ route('admin.accounting.journal-entries.index', ['status' => 'draft']) }}">Open</a></td></tr>
                <tr><td>Draft Supplier Bills</td><td>{{ $draftBills }}</td><td><a class="btn sm" href="{{ route('admin.accounting.accounts-payable.index', ['status' => 'draft']) }}">Open</a></td></tr>
                <tr><td>Draft Customer Invoices</td><td>{{ $draftInvoices }}</td><td><a class="btn sm" href="{{ route('admin.accounting.accounts-receivable.index', ['status' => 'draft']) }}">Open</a></td></tr>
                <tr><td>Overdue Bills</td><td>{{ $overdueBills }}</td><td><a class="btn sm" href="{{ route('admin.accounting.accounts-payable.index') }}">Open</a></td></tr>
                <tr><td>Overdue Invoices</td><td>{{ $overdueInvoices }}</td><td><a class="btn sm" href="{{ route('admin.accounting.accounts-receivable.index') }}">Open</a></td></tr>
                <tr><td>ZATCA Failed</td><td>{{ $zatcaFailed }}</td><td><a class="btn sm" href="{{ route('admin.accounting.zatca.index', ['status' => 'failed']) }}">Open</a></td></tr>
            </tbody>
        </x-admin.data-table>
    </div>

    <div class="split even">
        <x-admin.data-table title="Payable Aging" subtitle="Outstanding supplier balances">
            <thead>
                <tr><th>Bucket</th><th>Amount</th></tr>
            </thead>
            <tbody>
                @foreach ($payableAging as $bucket => $amount)
                    <tr><td>{{ $bucket }}</td><td>SAR {{ number_format($amount, 2) }}</td></tr>
                @endforeach
                <tr><td><strong>Total</strong></td><td><strong>SAR {{ number_format(array_sum($payableAging), 2) }}</strong></td></tr>
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Receivable Aging" subtitle="Outstanding customer balances">
            <thead>
                <tr><th>Bucket</th><th>Amount</th></tr>
            </thead>
            <tbody>
                @foreach ($receivableAging as $bucket => $amount)
                    <tr><td>{{ $bucket }}</td><td>SAR {{ number_format($amount, 2) }}</td></tr>
                @endforeach
                <tr><td><strong>Total</strong></td><td><strong>SAR {{ number_format(array_sum($receivableAging), 2) }}</strong></td></tr>
            </tbody>
        </x-admin.data-table>
    </div>

    <div class="split even">
        <x-admin.data-table title="VAT Summary">
            <x-slot:headerActions>
                <a class="btn sm primary" href="{{ route('admin.accounting.vat.index') }}">Open VAT</a>
            </x-slot:headerActions>
            <thead>
                <tr><th>Measure</th><th>Amount</th></tr>
            </thead>
            <tbody>
                <tr><td>Output VAT (sales)</td><td>SAR {{ number_format($outputVat, 2) }}</td></tr>
                <tr><td>Input VAT (purchases)</td><td>SAR {{ number_format($inputVat, 2) }}</td></tr>
                <tr><td><strong>VAT Payable</strong></td><td><strong>SAR {{ number_format($vatPayable, 2) }}</strong></td></tr>
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="ZATCA Status Summary">
            <x-slot:headerActions>
                <a class="btn sm primary" href="{{ route('admin.accounting.zatca.index') }}">Open ZATCA</a>
            </x-slot:headerActions>
            <thead>
                <tr><th>Clearance Status</th><th>Invoices</th></tr>
            </thead>
            <tbody>
                @forelse ($zatcaSummary as $status => $count)
                    <tr><td><x-admin.status-badge :status="$status"/></td><td>{{ $count }}</td></tr>
                @empty
                    <tr><td colspan="2" class="table-empty">No ZATCA records yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    </div>

    <x-admin.data-table title="Recent Journal Entries">
        <x-slot:headerActions>
            <a class="btn sm primary" href="{{ route('admin.accounting.journal-entries.index') }}">View All</a>
        </x-slot:headerActions>
        <thead>
            <tr><th>Journal No</th><th>Date</th><th>Source</th><th>Description</th><th>Cost Center</th><th>Debit</th><th>Credit</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($recentJournals as $entry)
                <tr>
                    <td><a href="{{ route('admin.accounting.journal-entries.show', $entry) }}" style="color:var(--blue);font-weight:700">{{ $entry->journal_number }}</a></td>
                    <td>{{ $entry->journal_date->toDateString() }}</td>
                    <td>{{ $entry->source_module }}</td>
                    <td>{{ $entry->description ? Str::limit($entry->description, 40) : '-' }}</td>
                    <td>{{ $entry->costCenter?->code ?? '-' }}</td>
                    <td>SAR {{ number_format($entry->total_debit, 2) }}</td>
                    <td>SAR {{ number_format($entry->total_credit, 2) }}</td>
                    <td><x-admin.status-badge :status="$entry->status"/></td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No journal entries yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
