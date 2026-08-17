@extends('layouts.admin')

@section('title', 'Cost Centers')
@section('breadcrumb', 'Accounting / Cost Centers')

@section('content')
    <x-admin.page-header title="Project-Based Cost Centers" description="Track cost by branch, department, project, site and warehouse">
        <a class="btn primary" href="{{ route('admin.accounting.cost-centers.create') }}">+ Add Cost Center</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalCostCenters" label="Total Cost Centers"/>
        <x-admin.metric-card color="green" :value="$activeCostCenters" label="Active"/>
        <x-admin.metric-card color="cyan" :value="$projectCostCenters" label="Project Cost Centers"/>
        <x-admin.metric-card color="yellow" :value="$siteCostCenters" label="Site Cost Centers"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Code or name..."/>
        <select class="select" style="width:160px" name="type">
            <option value="">All Types</option>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            @foreach (['active', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.accounting.cost-centers.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Cost Centers Listing">
        <thead>
            <tr><th>Code</th><th>Name</th><th>Type</th><th>Manager</th><th>Journal Lines</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($costCenters as $costCenter)
                <tr>
                    <td>{{ $costCenter->code }}</td>
                    <td><a href="{{ route('admin.accounting.cost-centers.show', $costCenter) }}" style="color:var(--blue);font-weight:700">{{ $costCenter->name }}</a></td>
                    <td>{{ ucfirst($costCenter->type) }}</td>
                    <td>{{ $costCenter->manager?->name ?? '-' }}</td>
                    <td>{{ $costCenter->journal_lines_count }}</td>
                    <td><x-admin.status-badge :status="$costCenter->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.accounting.cost-centers.show', $costCenter)"
                            :edit="route('admin.accounting.cost-centers.edit', $costCenter)"
                            :delete="route('admin.accounting.cost-centers.destroy', $costCenter)"
                            :name="$costCenter->code.' - '.$costCenter->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty">No cost centers match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $costCenters->firstItem() ?? 0 }}-{{ $costCenters->lastItem() ?? 0 }} of {{ $costCenters->total() }}</span>
            {{ $costCenters->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    <div class="help-box">
        Every journal line, supplier bill, customer invoice and payroll posting can be tagged with a cost center, which is what makes project-level cost reporting possible.
    </div>
@endsection
