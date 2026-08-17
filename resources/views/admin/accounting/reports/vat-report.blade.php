@extends('layouts.admin')

@section('title', 'VAT Report')
@section('breadcrumb', 'Accounting / Financial Reports / VAT Report')

@section('content')
    <x-admin.page-header title="VAT Report" description="Output VAT, input VAT and VAT payable per period">
        <a class="btn outline" href="{{ route('admin.accounting.vat.index') }}">VAT Management</a>
        <a class="btn outline" href="{{ route('admin.accounting.reports.index') }}">All Reports</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($totalOutputVat, 2)" label="Total Output VAT"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($totalInputVat, 2)" label="Total Input VAT"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($totalVatPayable, 2)" label="Total VAT Payable"/>
        <x-admin.metric-card color="green" :value="$periods->count()" label="VAT Periods"/>
    </div>

    <x-admin.data-table title="VAT by Period">
        <thead>
            <tr><th>Period</th><th>Start</th><th>End</th><th>Sales Taxable</th><th>Output VAT</th><th>Purchase Taxable</th><th>Input VAT</th><th>VAT Payable</th><th>Status</th></tr>
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
                    <td><x-admin.status-badge :status="$period->status"/></td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No VAT periods defined yet.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span><strong>Totals</strong></span>
            <span><strong>Output SAR {{ number_format($totalOutputVat, 2) }} &nbsp; | &nbsp; Input SAR {{ number_format($totalInputVat, 2) }} &nbsp; | &nbsp; Payable SAR {{ number_format($totalVatPayable, 2) }}</strong></span>
        </x-slot:footer>
    </x-admin.data-table>

    <div class="note">
        VAT Payable = Output VAT − Input VAT, at the standard Saudi rate of 15%. Official filing and ZATCA submission are handled in the compliance phase.
    </div>
@endsection
