@extends('layouts.admin')

@section('title', 'Warehouses')
@section('breadcrumb', 'Master Setup / Warehouses')

@section('content')
    <x-admin.page-header title="Warehouse Management" description="Warehouses can be branch-level or project/site-level for inventory tracking">
        <a class="btn primary" href="{{ route('admin.master.warehouses.create') }}">+ Add Warehouse</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalWarehouses" label="Total Warehouses"/>
        <x-admin.metric-card color="green" :value="$activeWarehouses" label="Active Warehouses"/>
        <x-admin.metric-card color="yellow" :value="$siteWarehouses" label="Site-Level Stores"/>
        <x-admin.metric-card color="cyan" :value="$branches->count()" label="Branches"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:230px" type="search" name="search" value="{{ request('search') }}" placeholder="Search warehouse name or code..."/>
        <select class="select" style="width:170px" name="branch">
            <option value="">All Branches</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(request('branch') == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <x-slot:actions>
            <a class="btn primary" href="{{ route('admin.master.warehouses.create') }}">+ Add Warehouse</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Warehouses Listing">
        <thead>
            <tr>
                <th>Warehouse Code</th><th>Warehouse Name</th><th>Branch</th><th>Project/Site</th>
                <th>Incharge</th><th>Valuation</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($warehouses as $warehouse)
                <tr>
                    <td>{{ $warehouse->code }}</td>
                    <td>{{ $warehouse->name }}</td>
                    <td>{{ $warehouse->branch?->name ?? '-' }}</td>
                    <td>{{ $warehouse->project ? $warehouse->project->name.($warehouse->site ? ' / '.$warehouse->site->name : '') : 'Branch Level' }}</td>
                    <td>{{ $warehouse->incharge?->name ?? '-' }}</td>
                    <td>{{ $warehouse->valuation_method }}</td>
                    <td><x-admin.status-badge :status="$warehouse->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.master.warehouses.show', $warehouse)"
                            :edit="route('admin.master.warehouses.edit', $warehouse)"
                            :delete="route('admin.master.warehouses.destroy', $warehouse)"
                            :name="$warehouse->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No warehouses found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $warehouses->firstItem() ?? 0 }}-{{ $warehouses->lastItem() ?? 0 }} of {{ $warehouses->total() }}</span>
            {{ $warehouses->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
