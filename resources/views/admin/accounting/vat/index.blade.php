@extends('layouts.admin')

@section('title', 'VAT Management')
@section('breadcrumb', 'Accounting / VAT Management')

@section('content')
    <x-admin.page-header title="VAT Management" description="Saudi VAT 15% — output VAT on sales, input VAT on purchases, and VAT payable per period">
        <a class="btn outline" href="{{ route('admin.accounting.reports.vat-report') }}">VAT Report</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($outputVat, 2)" label="Output VAT (Sales)"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($inputVat, 2)" label="Input VAT (Purchases)"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($vatPayable, 2)" label="VAT Payable"/>
        <x-admin.metric-card color="red" :value="$exceptions" label="VAT Exceptions"/>
    </div>

    <div class="help-box">
        VAT Payable = Output VAT − Input VAT. Transactions that fall outside any defined VAT period are listed as exceptions until a period covers their date.
    </div>

    <x-admin.filter-bar>
        <select class="select" style="width:160px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.accounting.vat.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="VAT Periods">
        <thead>
            <tr>
                <th>Period</th><th>Start</th><th>End</th><th>Sales Taxable</th><th>Output VAT</th>
                <th>Purchase Taxable</th><th>Input VAT</th><th>VAT Payable</th><th>Transactions</th>
                <th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($periods as $period)
                <tr>
                    <td><a href="{{ route('admin.accounting.vat.show', $period) }}" style="color:var(--blue);font-weight:700">{{ $period->period_name }}</a></td>
                    <td>{{ $period->start_date->toDateString() }}</td>
                    <td>{{ $period->end_date->toDateString() }}</td>
                    <td>{{ number_format($period->sales_taxable_amount, 2) }}</td>
                    <td>{{ number_format($period->output_vat, 2) }}</td>
                    <td>{{ number_format($period->purchase_taxable_amount, 2) }}</td>
                    <td>{{ number_format($period->input_vat, 2) }}</td>
                    <td><strong>{{ number_format($period->vat_payable, 2) }}</strong></td>
                    <td>{{ $period->transactions_count }}</td>
                    <td><x-admin.status-badge :status="$period->status"/></td>
                    <td>
                        <div class="actions">
                            <a class="btn sm primary" href="{{ route('admin.accounting.vat.show', $period) }}">View</a>
                            @if ($period->status !== 'submitted')
                                <form method="POST" action="{{ route('admin.accounting.vat.recalculate', $period) }}">
                                    @csrf
                                    <button type="submit" class="btn sm">Recalculate</button>
                                </form>
                            @endif
                            @if ($period->status === 'draft')
                                <form method="POST" action="{{ route('admin.accounting.vat.finalize', $period) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Finalize</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">No VAT periods defined yet.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $periods->firstItem() ?? 0 }}-{{ $periods->lastItem() ?? 0 }} of {{ $periods->total() }}</span>
            {{ $periods->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    <x-admin.data-table title="Recent VAT Transactions">
        <thead>
            <tr><th>Date</th><th>Source</th><th>Reference</th><th>Party</th><th>Taxable</th><th>Rate</th><th>VAT</th><th>Type</th></tr>
        </thead>
        <tbody>
            @forelse ($recentTransactions as $transaction)
                <tr>
                    <td>{{ $transaction->transaction_date->toDateString() }}</td>
                    <td>{{ $transaction->source_module }}</td>
                    <td>{{ $transaction->source_reference ?? '-' }}</td>
                    <td>{{ $transaction->party_name ?? '-' }}</td>
                    <td>{{ number_format($transaction->taxable_amount, 2) }}</td>
                    <td>{{ number_format($transaction->vat_rate, 2) }}%</td>
                    <td>{{ number_format($transaction->vat_amount, 2) }}</td>
                    <td><x-admin.status-badge :status="$transaction->vat_type"/></td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No VAT transactions yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
