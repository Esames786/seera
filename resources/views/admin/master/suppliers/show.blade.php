@extends('layouts.admin')

@section('title', 'Supplier Details')
@section('breadcrumb', 'Master Setup / Suppliers / Supplier Details')

@section('content')
    <x-admin.page-header :title="$supplier->name" description="Supplier overview">
        <a class="btn primary" href="{{ route('admin.master.suppliers.edit', $supplier) }}">Edit Supplier</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="strtoupper($supplier->code)" label="Supplier Code"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($supplier->opening_balance)" label="Payable Balance"/>
        <x-admin.metric-card color="green" :value="$supplier->payment_terms ?? '-'" label="Payment Terms"/>
        <x-admin.metric-card color="cyan" :value="$supplier->category ?? '-'" label="Category"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Supplier Information" class="detail-table">
            <tbody>
                <tr><th>Supplier Name</th><td>{{ $supplier->name }}</td></tr>
                <tr><th>Supplier Code</th><td>{{ $supplier->code }}</td></tr>
                <tr><th>Category</th><td>{{ $supplier->category ?? '-' }}</td></tr>
                <tr><th>VAT Number</th><td>{{ $supplier->vat_number ?? '-' }}</td></tr>
                <tr><th>CR Number</th><td>{{ $supplier->cr_number ?? '-' }}</td></tr>
                <tr><th>Contact Person</th><td>{{ $supplier->contact_person ?? '-' }}</td></tr>
                <tr><th>Phone</th><td>{{ $supplier->phone ?? '-' }}</td></tr>
                <tr><th>Email</th><td>{{ $supplier->email ?? '-' }}</td></tr>
                <tr><th>Linked Payable Account</th><td>{{ $supplier->linked_account ?? '-' }}</td></tr>
                <tr><th>Address</th><td>{{ $supplier->address ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$supplier->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <div class="table-card">
            <div class="table-title">Supplier Ledger</div>
            <div class="table-empty">
                Purchases, payments, and the payable ledger will appear here when the Accounting module ships in a later phase.
            </div>
        </div>
    </div>
@endsection
