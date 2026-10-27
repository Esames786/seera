@extends('layouts.admin')

@section('title', __('Dashboard'))
@section('breadcrumb', __('Administration / Dashboard'))

@section('content')
    <x-admin.page-header title="Dashboard" description="Overview of users, roles, projects and recent activity"/>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalStaff" label="Total Staff"/>
        <x-admin.metric-card color="green" :value="$activeRoles" label="Active Roles"/>
        <x-admin.metric-card color="yellow" :value="$pendingApprovals" label="Pending Approvals"/>
        <x-admin.metric-card color="red" :value="$inactiveUsers" label="Inactive Users"/>
    </div>

    <div class="card-grid">
        <x-admin.metric-card color="cyan" :value="$totalProjects" label="Total Projects"/>
        <x-admin.metric-card color="green" :value="$activeSites" label="Active Sites"/>
        <x-admin.metric-card color="blue" :value="$geoFencedSites" label="Geo-Fenced Sites"/>
        <x-admin.metric-card color="yellow" :value="$recentLogs->count()" label="Recent Activities"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Active Projects">
            <x-slot:headerActions>
                <a class="btn sm primary" href="{{ route('admin.master.projects.index') }}">{{ __('View All') }}</a>
            </x-slot:headerActions>
            <thead>
                <tr><th>{{ __('Code') }}</th><th>{{ __('Project') }}</th><th>{{ __('Client') }}</th><th>{{ __('Manager') }}</th><th>{{ __('Budget') }}</th><th>{{ __('Status') }}</th></tr>
            </thead>
            <tbody>
                @forelse ($projects as $project)
                    <tr>
                        <td>{{ $project->code }}</td>
                        <td><a href="{{ route('admin.master.projects.show', $project) }}" style="color:var(--blue);font-weight:700">{{ $project->name }}</a></td>
                        <td>{{ $project->customer?->name ?? '-' }}</td>
                        <td>{{ $project->manager?->name ?? '-' }}</td>
                        <td>SAR {{ number_format($project->budget / 1000000, 1) }}M</td>
                        <td><x-admin.status-badge :status="$project->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="table-empty">No projects yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Recent Activity">
            <x-slot:headerActions>
                <a class="btn sm primary" href="{{ route('admin.activity-logs.index') }}">{{ __('Open Activity Logs') }}</a>
            </x-slot:headerActions>
            <thead>
                <tr><th>{{ __('Time') }}</th><th>{{ __('User') }}</th><th>{{ __('Module') }}</th><th>{{ __('Activity') }}</th><th>{{ __('Status') }}</th></tr>
            </thead>
            <tbody>
                @forelse ($recentLogs as $log)
                    <tr>
                        <td>{{ $log->created_at->diffForHumans() }}</td>
                        <td>{{ $log->user_name }}</td>
                        <td>{{ $log->module }}</td>
                        <td>{{ $log->action }}</td>
                        <td><x-admin.status-badge :status="$log->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-empty">No activity yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    </div>
@endsection
