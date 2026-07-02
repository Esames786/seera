@extends('layouts.admin')

@section('title', 'Projects')
@section('breadcrumb', 'Master Setup / Projects')

@section('content')
    <x-admin.page-header title="Project Management Setup" description="Master project setup before budgeting, attendance, expense, inventory, and equipment assignment">
        <a class="btn primary" href="{{ route('admin.master.projects.create') }}">+ Create Project</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalProjects" label="Total Projects"/>
        <x-admin.metric-card color="green" :value="$activeProjects" label="Active Projects"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($totalBudget / 1000000, 1).'M'" label="Total Budget"/>
        <x-admin.metric-card color="cyan" :value="$projects->sum('sites_count')" label="Linked Sites"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:230px" type="search" name="search" value="{{ request('search') }}" placeholder="Search project name or code..."/>
        <select class="select" style="width:170px" name="branch">
            <option value="">All Branches</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(request('branch') == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="status">
            <option value="">All Status</option>
            @foreach (['active', 'planning', 'on hold', 'completed', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn primary" href="{{ route('admin.master.projects.create') }}">+ Create Project</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Projects Listing">
        <thead>
            <tr>
                <th>Project Code</th><th>Project Name</th><th>Client</th><th>Branch</th><th>Project Manager</th>
                <th>Budget</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($projects as $project)
                <tr>
                    <td>{{ $project->code }}</td>
                    <td>{{ $project->name }}</td>
                    <td>{{ $project->customer?->name ?? '-' }}</td>
                    <td>{{ $project->branch?->name ?? '-' }}</td>
                    <td>{{ $project->manager?->name ?? '-' }}</td>
                    <td>SAR {{ number_format($project->budget / 1000000, 1) }}M</td>
                    <td>{{ $project->start_date?->format('d M Y') ?? '-' }}</td>
                    <td>{{ $project->end_date?->format('d M Y') ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$project->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.master.projects.show', $project)"
                            :edit="route('admin.master.projects.edit', $project)"
                            :delete="route('admin.master.projects.destroy', $project)"
                            :name="$project->name">
                            <a class="btn sm warning" href="{{ route('admin.master.sites.index', ['project' => $project->id]) }}">Sites</a>
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">No projects found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $projects->firstItem() ?? 0 }}-{{ $projects->lastItem() ?? 0 }} of {{ $projects->total() }}</span>
            {{ $projects->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
