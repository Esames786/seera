@extends('layouts.admin')

@section('title', 'Project Details')
@section('breadcrumb', 'Master Setup / Projects / Project Details')

@section('content')
    <x-admin.page-header :title="$project->name" description="Project overview with sites and warehouses">
        <a class="btn primary" href="{{ route('admin.master.projects.edit', $project) }}">Edit Project</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($project->budget / 1000000, 1).'M'" label="Budget"/>
        <x-admin.metric-card color="green" :value="$project->sites->count()" label="Sites"/>
        <x-admin.metric-card color="yellow" :value="$project->warehouses->count()" label="Warehouses"/>
        <x-admin.metric-card color="cyan" :value="$project->end_date ? $project->end_date->format('d M Y') : '-'" label="Target Completion"/>
    </div>

    <div class="split">
        <x-admin.data-table title="Project Information" class="detail-table">
            <tbody>
                <tr><th>Project Code</th><td>{{ $project->code }}</td></tr>
                <tr><th>Client</th><td>{{ $project->customer?->name ?? '-' }}</td></tr>
                <tr><th>Branch</th><td>{{ $project->branch?->name ?? '-' }}</td></tr>
                <tr><th>Project Manager</th><td>{{ $project->manager?->name ?? '-' }}</td></tr>
                <tr><th>Start Date</th><td>{{ $project->start_date?->format('d M Y') ?? '-' }}</td></tr>
                <tr><th>End Date</th><td>{{ $project->end_date?->format('d M Y') ?? '-' }}</td></tr>
                <tr><th>Budget</th><td>SAR {{ number_format($project->budget) }}</td></tr>
                <tr><th>Location</th><td>{{ $project->location ?? '-' }}</td></tr>
                <tr><th>Description</th><td>{{ $project->description ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$project->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <x-admin.data-table title="Sites in this Project">
                <thead>
                    <tr><th>Code</th><th>Site</th><th>Supervisor</th><th>Geo-Fence</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($project->sites as $site)
                        <tr>
                            <td>{{ $site->code }}</td>
                            <td><a href="{{ route('admin.master.sites.show', $site) }}" style="color:var(--blue);font-weight:700">{{ $site->name }}</a></td>
                            <td>{{ $site->supervisor?->name ?? '-' }}</td>
                            <td><x-admin.status-badge :status="$site->geofence_enabled ? 'enabled' : 'pending'"/></td>
                            <td><x-admin.status-badge :status="$site->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">No sites in this project yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table title="Warehouses in this Project">
                <thead>
                    <tr><th>Code</th><th>Warehouse</th><th>Incharge</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($project->warehouses as $warehouse)
                        <tr>
                            <td>{{ $warehouse->code }}</td>
                            <td><a href="{{ route('admin.master.warehouses.show', $warehouse) }}" style="color:var(--blue);font-weight:700">{{ $warehouse->name }}</a></td>
                            <td>{{ $warehouse->incharge?->name ?? '-' }}</td>
                            <td><x-admin.status-badge :status="$warehouse->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">No warehouses in this project yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>
    </div>
@endsection
