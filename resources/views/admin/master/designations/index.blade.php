@extends('layouts.admin')

@section('title', 'Designations')
@section('breadcrumb', 'Master Setup / Designations')

@section('content')
    <x-admin.page-header title="Designation Management" description="Employee job titles connected with departments and role suggestions">
        <a class="btn primary" href="{{ route('admin.master.designations.create') }}">+ Add Designation</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalDesignations" label="Total Designations"/>
        <x-admin.metric-card color="green" :value="$activeDesignations" label="Active Designations"/>
        <x-admin.metric-card color="yellow" :value="$designations->sum('users_count')" label="Assigned Employees"/>
        <x-admin.metric-card color="cyan" :value="$departments->count()" label="Departments"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Search designation..."/>
        <select class="select" style="width:180px" name="department">
            <option value="">All Departments</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(request('department') == $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <x-slot:actions>
            <a class="btn primary" href="{{ route('admin.master.designations.create') }}">+ Add Designation</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Designations Listing">
        <thead>
            <tr>
                <th>Designation</th><th>Department</th><th>Grade / Level</th><th>Default Role</th>
                <th>Total Employees</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($designations as $designation)
                <tr>
                    <td>{{ $designation->name }}</td>
                    <td>{{ $designation->department?->name ?? '-' }}</td>
                    <td>{{ $designation->grade ?? '-' }}</td>
                    <td>{{ $designation->defaultRole?->name ?? '-' }}</td>
                    <td>{{ $designation->users_count }}</td>
                    <td><x-admin.status-badge :status="$designation->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.master.designations.show', $designation)"
                            :edit="route('admin.master.designations.edit', $designation)"
                            :delete="route('admin.master.designations.destroy', $designation)"
                            :name="$designation->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty">No designations found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $designations->firstItem() ?? 0 }}-{{ $designations->lastItem() ?? 0 }} of {{ $designations->total() }}</span>
            {{ $designations->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
