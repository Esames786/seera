@extends('layouts.admin')

@section('title', 'Customer Details')
@section('breadcrumb', 'Master Setup / Customers / Customer Details')

@section('content')
    <x-admin.page-header :title="$customer->name" description="Customer overview with linked projects">
        <a class="btn primary" href="{{ route('admin.master.customers.edit', $customer) }}">Edit Customer</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$customer->projects->count()" label="Projects"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($customer->opening_receivable)" label="Receivable"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($customer->credit_limit)" label="Credit Limit"/>
        <x-admin.metric-card color="cyan" :value="$customer->type" label="Customer Type"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Customer Information" class="detail-table">
            <tbody>
                <tr><th>Customer Name</th><td>{{ $customer->name }}</td></tr>
                <tr><th>Customer Code</th><td>{{ $customer->code }}</td></tr>
                <tr><th>Type</th><td>{{ $customer->type }}</td></tr>
                <tr><th>VAT Number</th><td>{{ $customer->vat_number ?? '-' }}</td></tr>
                <tr><th>CR Number</th><td>{{ $customer->cr_number ?? '-' }}</td></tr>
                <tr><th>Contact Person</th><td>{{ $customer->contact_person ?? '-' }}</td></tr>
                <tr><th>Phone</th><td>{{ $customer->phone ?? '-' }}</td></tr>
                <tr><th>Email</th><td>{{ $customer->email ?? '-' }}</td></tr>
                <tr><th>Linked Receivable Account</th><td>{{ $customer->linked_account ?? '-' }}</td></tr>
                <tr><th>Billing Address</th><td>{{ $customer->billing_address ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$customer->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Projects for this Customer">
            <thead>
                <tr><th>Code</th><th>Project</th><th>Manager</th><th>Budget</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($customer->projects as $project)
                    <tr>
                        <td>{{ $project->code }}</td>
                        <td><a href="{{ route('admin.master.projects.show', $project) }}" style="color:var(--blue);font-weight:700">{{ $project->name }}</a></td>
                        <td>{{ $project->manager?->name ?? '-' }}</td>
                        <td>SAR {{ number_format($project->budget) }}</td>
                        <td><x-admin.status-badge :status="$project->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-empty">No projects for this customer yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    </div>
@endsection
