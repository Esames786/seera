@extends('layouts.admin')

@section('title', 'Roles')
@section('breadcrumb', 'Administration / Roles')

@section('content')
    <x-admin.page-header title="Roles Listing" description="Manage ERP roles, hierarchy, scope, and role actions">
        <a class="btn primary" href="{{ route('admin.roles.create') }}">+ Add New Role</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalRoles" label="Total Roles"/>
        <x-admin.metric-card color="green" :value="$activeRoles" label="Active Roles"/>
        <x-admin.metric-card color="yellow" :value="$assignedUsers" label="Assigned Users"/>
        <x-admin.metric-card color="red" :value="$workflowCount" label="Approval Workflows"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Search role..."/>
        <select class="select" style="width:170px" name="department">
            <option value="">All Departments</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(request('department') == $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:130px" name="status">
            <option value="">All Status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <select class="select" style="width:160px" name="scope">
            <option value="">All Scope</option>
            @foreach (['Company', 'Project', 'Site'] as $scope)
                <option value="{{ $scope }}" @selected(request('scope') === $scope)>{{ $scope }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn primary" href="{{ route('admin.roles.create') }}">+ Add New Role</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Roles Listing" subtitle="Role table with hierarchy, scope, and actions">
        <thead>
            <tr>
                <th>Role Name</th><th>Department</th><th>Parent Role</th><th>Access Scope</th>
                <th>Total Users</th><th>Status</th><th>Created Date</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($roles as $role)
                <tr>
                    <td>{{ $role->name }} @if($role->is_system)<span class="badge purple">System</span>@endif</td>
                    <td>{{ $role->department?->name ?? '-' }}</td>
                    <td>{{ $role->parent?->name ?? 'None' }}</td>
                    <td>{{ $role->access_scope }}</td>
                    <td>{{ $role->users_count }}</td>
                    <td><x-admin.status-badge :status="$role->status"/></td>
                    <td>{{ $role->created_at->format('M d, Y') }}</td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.roles.show', $role)"
                            :edit="route('admin.roles.edit', $role)"
                            :delete="$role->is_system ? null : route('admin.roles.destroy', $role)"
                            :name="$role->name">
                            <a class="btn sm warning" href="{{ route('admin.roles.assign-users', ['role' => $role->id]) }}">Assign</a>
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No roles found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $roles->firstItem() ?? 0 }}-{{ $roles->lastItem() ?? 0 }} of {{ $roles->total() }}</span>
            {{ $roles->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
