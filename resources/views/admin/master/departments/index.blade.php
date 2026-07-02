@extends('layouts.admin')

@section('title', 'Departments')
@section('breadcrumb', 'Master Setup / Departments')

@section('content')
    <x-admin.page-header title="Department Management" description="Used for users, employees, roles, approval workflows, and reports">
        <a class="btn primary" href="{{ route('admin.master.departments.create') }}">+ Add Department</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalDepartments" label="Total Departments"/>
        <x-admin.metric-card color="green" :value="$activeDepartments" label="Active Departments"/>
        <x-admin.metric-card color="yellow" :value="$departments->sum('users_count')" label="Assigned Users"/>
        <x-admin.metric-card color="cyan" :value="$departments->whereNotNull('head_user_id')->count()" label="With Department Head"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:230px" type="search" name="search" value="{{ request('search') }}" placeholder="Search department name or code..."/>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <x-slot:actions>
            <a class="btn primary" href="{{ route('admin.master.departments.create') }}">+ Add Department</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Departments Listing">
        <thead>
            <tr>
                <th>Department Code</th><th>Department Name</th><th>Department Head</th>
                <th>Total Users</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($departments as $department)
                <tr>
                    <td>{{ $department->code }}</td>
                    <td>{{ $department->name }}</td>
                    <td>{{ $department->head?->name ?? '-' }}</td>
                    <td>{{ $department->users_count }}</td>
                    <td><x-admin.status-badge :status="$department->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.master.departments.show', $department)"
                            :edit="route('admin.master.departments.edit', $department)"
                            :delete="route('admin.master.departments.destroy', $department)"
                            :name="$department->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No departments found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $departments->firstItem() ?? 0 }}-{{ $departments->lastItem() ?? 0 }} of {{ $departments->total() }}</span>
            {{ $departments->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
