@extends('layouts.admin')

@section('title', 'Branches')
@section('breadcrumb', 'Master Setup / Branches')

@section('content')
    <x-admin.page-header title="Branch Management" description="Manage company branches for users, projects, warehouses, and accounting reports">
        <a class="btn primary" href="{{ route('admin.master.branches.create') }}">+ Add Branch</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalBranches" label="Total Branches"/>
        <x-admin.metric-card color="green" :value="$activeBranches" label="Active Branches"/>
        <x-admin.metric-card color="yellow" :value="$branches->sum('projects_count')" label="Linked Projects"/>
        <x-admin.metric-card color="cyan" :value="$cities->count()" label="Cities Covered"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Search branch name or code..."/>
        <select class="select" style="width:150px" name="city">
            <option value="">All Cities</option>
            @foreach ($cities as $city)
                <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <x-slot:actions>
            <a class="btn primary" href="{{ route('admin.master.branches.create') }}">+ Add Branch</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Branches Listing">
        <thead>
            <tr>
                <th>Branch Code</th><th>Branch Name</th><th>City</th><th>Manager</th>
                <th>Phone</th><th>Total Projects</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($branches as $branch)
                <tr>
                    <td>{{ $branch->code }}</td>
                    <td>{{ $branch->name }}</td>
                    <td>{{ $branch->city ?? '-' }}</td>
                    <td>{{ $branch->manager?->name ?? '-' }}</td>
                    <td>{{ $branch->phone ?? '-' }}</td>
                    <td>{{ $branch->projects_count }}</td>
                    <td><x-admin.status-badge :status="$branch->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.master.branches.show', $branch)"
                            :edit="route('admin.master.branches.edit', $branch)"
                            :delete="route('admin.master.branches.destroy', $branch)"
                            :name="$branch->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No branches found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $branches->firstItem() ?? 0 }}-{{ $branches->lastItem() ?? 0 }} of {{ $branches->total() }}</span>
            {{ $branches->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
