@extends('layouts.admin')

@section('title', 'Site Details')
@section('breadcrumb', 'Master Setup / Sites / Site Details')

@section('content')
    <x-admin.page-header :title="$site->name" description="Site overview with geo-fence and warehouses">
        <a class="btn primary" href="{{ route('admin.master.sites.edit', $site) }}">Edit Site</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$site->geofence_radius.' m'" label="Geo-Fence Radius"/>
        <x-admin.metric-card color="green" :value="$site->geofence_enabled ? 'Enabled' : 'Disabled'" label="Geo-Fence"/>
        <x-admin.metric-card color="yellow" :value="$site->warehouses->count()" label="Warehouses"/>
        <x-admin.metric-card color="cyan" :value="$site->offline_attendance_allowed ? 'Yes' : 'No'" label="Offline Attendance"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Site Information" class="detail-table">
            <tbody>
                <tr><th>Site Code</th><td>{{ $site->code }}</td></tr>
                <tr><th>Project</th><td>{{ $site->project?->name ?? '-' }} @if($site->project?->customer)<span class="small">({{ $site->project->customer->name }})</span>@endif</td></tr>
                <tr><th>Supervisor</th><td>{{ $site->supervisor?->name ?? '-' }}</td></tr>
                <tr><th>Latitude</th><td>{{ $site->latitude ?? '-' }}</td></tr>
                <tr><th>Longitude</th><td>{{ $site->longitude ?? '-' }}</td></tr>
                <tr><th>Attendance Inside Boundary</th><td>{{ $site->attendance_inside_only ? 'Yes' : 'No' }}</td></tr>
                <tr><th>Offline Attendance</th><td>{{ $site->offline_attendance_allowed ? 'Yes' : 'No' }}</td></tr>
                <tr><th>Address</th><td>{{ $site->address ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$site->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <div class="map-placeholder">
                Map + Geo-Fence Circle<br>
                {{ $site->latitude }}, {{ $site->longitude }} — radius {{ $site->geofence_radius }} m
            </div>
            <br/>
            <x-admin.data-table title="Warehouses on this Site">
                <thead>
                    <tr><th>Code</th><th>Warehouse</th><th>Incharge</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($site->warehouses as $warehouse)
                        <tr>
                            <td>{{ $warehouse->code }}</td>
                            <td><a href="{{ route('admin.master.warehouses.show', $warehouse) }}" style="color:var(--blue);font-weight:700">{{ $warehouse->name }}</a></td>
                            <td>{{ $warehouse->incharge?->name ?? '-' }}</td>
                            <td><x-admin.status-badge :status="$warehouse->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">No warehouses on this site yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>
    </div>
@endsection
