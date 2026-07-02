@extends('layouts.admin')

@section('title', 'Sites')
@section('breadcrumb', 'Master Setup / Sites / Geo-Fence')

@section('content')
    <x-admin.page-header title="Site Management with Geo-Fence" description="Construction sites used by mobile attendance, expenses, material consumption, and equipment assignment">
        <a class="btn primary" href="{{ route('admin.master.sites.create') }}">+ Add Site</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalSites" label="Total Sites"/>
        <x-admin.metric-card color="green" :value="$activeSites" label="Active Sites"/>
        <x-admin.metric-card color="yellow" :value="$geoFencedSites" label="Geo-Fenced Sites"/>
        <x-admin.metric-card color="cyan" :value="$projects->count()" label="Projects"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Search site name or code..."/>
        <select class="select" style="width:180px" name="project">
            <option value="">All Projects</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            @foreach (['active', 'draft', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn primary" href="{{ route('admin.master.sites.create') }}">+ Add Site</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Sites Listing">
        <thead>
            <tr>
                <th>Site Code</th><th>Site Name</th><th>Project</th><th>Supervisor</th>
                <th>Geo-Fence</th><th>Radius</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sites as $site)
                <tr>
                    <td>{{ $site->code }}</td>
                    <td>{{ $site->name }}</td>
                    <td>{{ $site->project?->name ?? '-' }}</td>
                    <td>{{ $site->supervisor?->name ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$site->geofence_enabled ? 'enabled' : 'pending'"/></td>
                    <td>{{ $site->geofence_radius }} m</td>
                    <td><x-admin.status-badge :status="$site->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.master.sites.show', $site)"
                            :edit="route('admin.master.sites.edit', $site)"
                            :delete="route('admin.master.sites.destroy', $site)"
                            :name="$site->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No sites found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $sites->firstItem() ?? 0 }}-{{ $sites->lastItem() ?? 0 }} of {{ $sites->total() }}</span>
            {{ $sites->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
