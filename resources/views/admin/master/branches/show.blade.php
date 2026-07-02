@extends('layouts.admin')

@section('title', 'Branch Details')
@section('breadcrumb', 'Master Setup / Branches / Branch Details')

@section('content')
    <x-admin.page-header :title="$branch->name" description="Branch overview with linked projects and warehouses">
        <a class="btn primary" href="{{ route('admin.master.branches.edit', $branch) }}">Edit Branch</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$branch->projects->count()" label="Projects"/>
        <x-admin.metric-card color="green" :value="$branch->warehouses->count()" label="Warehouses"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($branch->projects->sum('budget') / 1000000, 1).'M'" label="Total Project Budget"/>
        <x-admin.metric-card color="cyan" :value="strtoupper($branch->code)" label="Branch Code"/>
    </div>

    <div class="split">
        <x-admin.data-table title="Branch Information" class="detail-table">
            <tbody>
                <tr><th>Branch Name</th><td>{{ $branch->name }}</td></tr>
                <tr><th>Branch Code</th><td>{{ $branch->code }}</td></tr>
                <tr><th>City</th><td>{{ $branch->city ?? '-' }}</td></tr>
                <tr><th>Manager</th><td>{{ $branch->manager?->name ?? '-' }}</td></tr>
                <tr><th>Phone</th><td>{{ $branch->phone ?? '-' }}</td></tr>
                <tr><th>Email</th><td>{{ $branch->email ?? '-' }}</td></tr>
                <tr><th>Address</th><td>{{ $branch->address ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$branch->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <x-admin.data-table title="Projects in this Branch">
                <thead>
                    <tr><th>Code</th><th>Project</th><th>Client</th><th>Budget</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($branch->projects as $project)
                        <tr>
                            <td>{{ $project->code }}</td>
                            <td><a href="{{ route('admin.master.projects.show', $project) }}" style="color:var(--blue);font-weight:700">{{ $project->name }}</a></td>
                            <td>{{ $project->customer?->name ?? '-' }}</td>
                            <td>SAR {{ number_format($project->budget) }}</td>
                            <td><x-admin.status-badge :status="$project->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">No projects in this branch yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table title="Warehouses in this Branch">
                <thead>
                    <tr><th>Code</th><th>Warehouse</th><th>Level</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($branch->warehouses as $warehouse)
                        <tr>
                            <td>{{ $warehouse->code }}</td>
                            <td><a href="{{ route('admin.master.warehouses.show', $warehouse) }}" style="color:var(--blue);font-weight:700">{{ $warehouse->name }}</a></td>
                            <td>{{ $warehouse->site_id ? 'Site Level' : ($warehouse->project_id ? 'Project Level' : 'Branch Level') }}</td>
                            <td><x-admin.status-badge :status="$warehouse->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">No warehouses in this branch yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>
    </div>
@endsection
