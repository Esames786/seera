@extends('layouts.admin')

@section('title', 'Customers')
@section('breadcrumb', 'Master Setup / Customers')

@section('content')
    <x-admin.page-header title="Customer / Client Management" description="Used by project contracts, receivables, sales invoices, VAT, and ZATCA e-invoicing">
        <a class="btn primary" href="{{ route('admin.master.customers.create') }}">+ Add Customer</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalCustomers" label="Total Customers"/>
        <x-admin.metric-card color="green" :value="$activeCustomers" label="Active Customers"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($totalReceivable / 1000).'K'" label="Receivable Balance"/>
        <x-admin.metric-card color="cyan" :value="$customers->sum('projects_count')" label="Linked Projects"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:240px" type="search" name="search" value="{{ request('search') }}" placeholder="Search name, code, VAT number..."/>
        <select class="select" style="width:160px" name="type">
            <option value="">All Types</option>
            <option value="Company" @selected(request('type') === 'Company')>Company</option>
            <option value="Individual" @selected(request('type') === 'Individual')>Individual</option>
        </select>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <x-slot:actions>
            <a class="btn primary" href="{{ route('admin.master.customers.create') }}">+ Add Customer</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Customers Listing">
        <thead>
            <tr>
                <th>Customer Code</th><th>Customer Name</th><th>VAT Number</th><th>Contact Person</th>
                <th>Total Projects</th><th>Receivable</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($customers as $customer)
                <tr>
                    <td>{{ $customer->code }}</td>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->vat_number ?? '-' }}</td>
                    <td>{{ $customer->contact_person ?? '-' }}</td>
                    <td>{{ $customer->projects_count }}</td>
                    <td>SAR {{ number_format($customer->opening_receivable / 1000, 1) }}K</td>
                    <td><x-admin.status-badge :status="$customer->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.master.customers.show', $customer)"
                            :edit="route('admin.master.customers.edit', $customer)"
                            :delete="route('admin.master.customers.destroy', $customer)"
                            :name="$customer->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No customers found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $customers->firstItem() ?? 0 }}-{{ $customers->lastItem() ?? 0 }} of {{ $customers->total() }}</span>
            {{ $customers->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
