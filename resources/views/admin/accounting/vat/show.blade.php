@extends('layouts.admin')

@section('title', 'VAT Period')
@section('breadcrumb', 'Accounting / VAT Management / VAT Period')

@section('content')
    <x-admin.page-header :title="'VAT Period: '.$period->period_name" :description="$period->start_date->toDateString().' to '.$period->end_date->toDateString()">
        @if ($period->status !== 'submitted')
            <form method="POST" action="{{ route('admin.accounting.vat.recalculate', $period) }}">
                @csrf
                <button type="submit" class="btn outline">Recalculate</button>
            </form>
        @endif
        @if ($period->status === 'draft')
            <form method="POST" action="{{ route('admin.accounting.vat.finalize', $period) }}">
                @csrf
                <button type="submit" class="btn primary">Finalize Period</button>
            </form>
        @endif
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($period->output_vat, 2)" label="Output VAT"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($period->input_vat, 2)" label="Input VAT"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($period->vat_payable, 2)" label="VAT Payable"/>
        <x-admin.metric-card color="green" :value="$period->transactions_count" label="Transactions"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Period Summary" class="detail-table">
            <tbody>
                <tr><th>Period Name</th><td>{{ $period->period_name }}</td></tr>
                <tr><th>Start Date</th><td>{{ $period->start_date->toDateString() }}</td></tr>
                <tr><th>End Date</th><td>{{ $period->end_date->toDateString() }}</td></tr>
                <tr><th>Sales Taxable Amount</th><td>SAR {{ number_format($period->sales_taxable_amount, 2) }}</td></tr>
                <tr><th>Output VAT</th><td>SAR {{ number_format($period->output_vat, 2) }}</td></tr>
                <tr><th>Purchase Taxable Amount</th><td>SAR {{ number_format($period->purchase_taxable_amount, 2) }}</td></tr>
                <tr><th>Input VAT</th><td>SAR {{ number_format($period->input_vat, 2) }}</td></tr>
                <tr><th>VAT Payable</th><td><strong>SAR {{ number_format($period->vat_payable, 2) }}</strong></td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$period->status"/></td></tr>
                <tr><th>Submitted At</th><td>{{ $period->submitted_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
                <tr><th>Notes</th><td>{{ $period->notes ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <div class="note">
            VAT Payable = Output VAT − Input VAT.<br><br>
            Output VAT SAR {{ number_format($period->output_vat, 2) }} − Input VAT SAR {{ number_format($period->input_vat, 2) }}
            = <strong>SAR {{ number_format($period->vat_payable, 2) }}</strong>.
            <br><br>
            Final Saudi VAT filing and ZATCA submission are handled in the dedicated compliance phase.
        </div>
    </div>

    <x-admin.filter-bar>
        <select class="select" style="width:160px" name="type">
            <option value="">All VAT Types</option>
            <option value="output" @selected(request('type') === 'output')>Output</option>
            <option value="input" @selected(request('type') === 'input')>Input</option>
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.accounting.vat.show', $period) }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="VAT Transactions">
        <thead>
            <tr><th>Date</th><th>Source</th><th>Reference</th><th>Party</th><th>Taxable</th><th>Rate</th><th>VAT</th><th>Type</th></tr>
        </thead>
        <tbody>
            @forelse ($transactions as $transaction)
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
                <tr><td colspan="8" class="table-empty">No VAT transactions in this period.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $transactions->firstItem() ?? 0 }}-{{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }}</span>
            {{ $transactions->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
