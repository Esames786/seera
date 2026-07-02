@extends('layouts.admin')

@section('title', 'Suppliers')
@section('breadcrumb', 'Master Setup / Suppliers')

@section('content')
    <x-admin.page-header title="Supplier Management" description="Used by purchase management, expenses, inventory, payable accounting, and VAT records">
        <a class="btn primary" href="{{ route('admin.master.suppliers.create') }}">+ Add Supplier</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalSuppliers" label="Total Suppliers"/>
        <x-admin.metric-card color="green" :value="$activeSuppliers" label="Active Suppliers"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($totalPayable / 1000).'K'" label="Payable Balance"/>
        <x-admin.metric-card color="cyan" :value="$categories->count()" label="Categories"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:240px" type="search" name="search" value="{{ request('search') }}" placeholder="Search name, code, VAT number..."/>
        <select class="select" style="width:160px" name="category">
            <option value="">All Categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <x-slot:actions>
            <a class="btn primary" href="{{ route('admin.master.suppliers.create') }}">+ Add Supplier</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Suppliers Listing">
        <thead>
            <tr>
                <th>Supplier Code</th><th>Supplier Name</th><th>VAT Number</th><th>Phone</th>
                <th>Category</th><th>Payable Balance</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($suppliers as $supplier)
                <tr>
                    <td>{{ $supplier->code }}</td>
                    <td>{{ $supplier->name }}</td>
                    <td>{{ $supplier->vat_number ?? '-' }}</td>
                    <td>{{ $supplier->phone ?? '-' }}</td>
                    <td>{{ $supplier->category ?? '-' }}</td>
                    <td>SAR {{ number_format($supplier->opening_balance / 1000, 1) }}K</td>
                    <td><x-admin.status-badge :status="$supplier->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.master.suppliers.show', $supplier)"
                            :edit="route('admin.master.suppliers.edit', $supplier)"
                            :delete="route('admin.master.suppliers.destroy', $supplier)"
                            :name="$supplier->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No suppliers found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $suppliers->firstItem() ?? 0 }}-{{ $suppliers->lastItem() ?? 0 }} of {{ $suppliers->total() }}</span>
            {{ $suppliers->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
