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
        <x-admin.metric-card color="green" :value="$supplier->paymentTerm?->label() ?? $supplier->payment_terms ?? '-'" label="Payment Terms"/>
        <x-admin.metric-card :color="match ($supplier->rating) { 'Green' => 'green', 'Amber' => 'yellow', 'Red' => 'red', default => 'cyan' }" :value="$supplier->rating ?? 'Not rated'" label="Supplier Rating"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Supplier Information" class="detail-table">
            <tbody>
                <tr><th>Supplier Name</th><td>{{ $supplier->name }}</td></tr>
                <tr><th>Supplier Code</th><td>{{ $supplier->code }}</td></tr>
                <tr><th>Category</th><td>{{ $supplier->category ?? '-' }}</td></tr>
                <tr><th>City / Location</th><td>{{ $supplier->city ?? '-' }}</td></tr>
                <tr><th>Rating</th><td>@if($supplier->rating)<span class="badge {{ match ($supplier->rating) { 'Green' => 'green', 'Amber' => 'yellow', default => 'red' } }}">{{ $supplier->rating }}</span>@else - @endif</td></tr>
                <tr><th>VAT Number</th><td>{{ $supplier->vat_number ?? '-' }}</td></tr>
                <tr><th>CR Number</th><td>{{ $supplier->cr_number ?? '-' }}</td></tr>
                <tr><th>Contact Person</th><td>{{ $supplier->contact_person ?? '-' }}</td></tr>
                <tr><th>Phone</th><td>{{ $supplier->phone ?? '-' }}</td></tr>
                <tr><th>Email</th><td>{{ $supplier->email ?? '-' }}</td></tr>
                <tr><th>Payment Terms</th><td>{{ $supplier->paymentTerm?->label() ?? $supplier->payment_terms ?? '-' }}</td></tr>
                <tr><th>Accepted Payment Types</th><td>{{ $supplier->allowed_payment_types ?? 'Both' }}</td></tr>
                <tr><th>Bank</th><td>{{ $supplier->bank_name ?? '-' }}@if($supplier->bank_account_name) · {{ $supplier->bank_account_name }}@endif</td></tr>
                <tr><th>IBAN</th><td>{{ $supplier->iban ?? '-' }}</td></tr>
                <tr><th>Linked Payable Account</th><td>{{ $supplier->linkedAccount?->label() ?? $supplier->linked_account ?? '-' }}</td></tr>
                <tr><th>Address</th><td>{{ $supplier->address ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$supplier->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <x-admin.data-table title="Projects this Supplier Works For">
                <thead>
                    <tr><th>Code</th><th>Project</th><th>Client</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($supplier->projects as $project)
                        <tr>
                            <td>{{ $project->code }}</td>
                            <td><a href="{{ route('admin.master.projects.show', $project) }}" style="color:var(--blue);font-weight:700">{{ $project->name }}</a></td>
                            <td>{{ $project->customer?->name ?? '-' }}</td>
                            <td><x-admin.status-badge :status="$project->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">Not linked to any project yet. Tick the projects on the supplier form.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>

            <div class="help-box">
                Bills and payments for this supplier are recorded under <a href="{{ route('admin.accounting.accounts-payable.index', ['supplier' => $supplier->id]) }}" style="color:var(--blue);font-weight:700">Accounts Payable</a>.
            </div>
        </div>
    </div>
@endsection
