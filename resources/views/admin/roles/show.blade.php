@extends('layouts.admin')

@section('title', 'Role Details')
@section('breadcrumb', 'Administration / Roles / Role Details')

@section('content')
    <x-admin.page-header title="Role Details" description="View role information, assigned users, permissions, workflows, and logs">
        <a class="btn primary" href="{{ route('admin.roles.edit', $role) }}">Edit Role</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$role->users->count()" label="Assigned Users"/>
        <x-admin.metric-card color="green" :value="$role->permissions->count()" label="Active Permissions"/>
        <x-admin.metric-card color="yellow" :value="$workflows->count()" label="Approval Workflows"/>
        <x-admin.metric-card color="red" value="0" label="Security Issues"/>
    </div>

    <div class="tabs" style="margin-bottom:16px">
        <div class="tab active">Overview</div>
        <div class="tab">Permissions</div>
        <div class="tab">Assigned Users</div>
        <div class="tab">Approval Workflows</div>
        <div class="tab">Activity Logs</div>
    </div>

    <div class="split">
        <x-admin.data-table title="Role Information" class="detail-table">
            <tbody>
                <tr><th>Role Name</th><td>{{ $role->name }}</td></tr>
                <tr><th>Role Code</th><td>{{ $role->code }}</td></tr>
                <tr><th>Department</th><td>{{ $role->department?->name ?? '-' }}</td></tr>
                <tr><th>Parent Role</th><td>{{ $role->parent?->name ?? 'None' }}</td></tr>
                <tr><th>Role Level</th><td>Level {{ $role->level }}</td></tr>
                <tr><th>Scope</th><td>{{ $role->access_scope }}</td></tr>
                <tr><th>Mobile App</th><td><x-admin.status-badge :status="$role->mobile_app_access ? 'enabled' : 'disabled'"/></td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$role->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <div class="table-card">
            <div class="table-title">Approval Workflows Connected</div>
            <div style="padding:12px 16px">
                @forelse ($workflows as $workflow)
                    <div class="small" style="font-weight:700;margin-top:4px">{{ $workflow->name }}</div>
                    <div class="workflow">
                        @foreach ($workflow->steps as $step)
                            <div class="workflow-step">{{ $step->approverRole?->name ?? 'Any' }}<br><span class="small">Step {{ $step->step_no }}</span></div>
                            @if (! $loop->last)<div class="arrow">&rarr;</div>@endif
                        @endforeach
                    </div>
                @empty
                    <p class="small">This role is not part of any approval workflow yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <x-admin.data-table title="Granted Permissions" class="permission-table">
        <thead>
            <tr>
                <th>Module</th>
                @foreach (['view', 'create', 'edit', 'delete', 'approve', 'export', 'mobile'] as $action)
                    <th>{{ ucfirst($action) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($role->permissions->groupBy('module') as $module => $permissions)
                <tr>
                    <td>{{ $module }}</td>
                    @foreach (['view', 'create', 'edit', 'delete', 'approve', 'export', 'mobile'] as $action)
                        <td>{!! $permissions->firstWhere('action', $action) ? '<span class="badge green">Yes</span>' : '<span class="small">-</span>' !!}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No permissions granted to this role yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <x-admin.data-table title="Assigned Users">
        <x-slot:headerActions>
            <a class="btn sm warning" href="{{ route('admin.roles.assign-users', ['role' => $role->id]) }}">Assign Users</a>
        </x-slot:headerActions>
        <thead>
            <tr><th>Employee ID</th><th>Name</th><th>Email</th><th>Project/Site</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($role->users as $user)
                <tr>
                    <td>{{ $user->employee_id ?? '-' }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->project?->name ? $user->project->name.($user->site ? ' / '.$user->site->name : '') : 'All Company' }}</td>
                    <td><x-admin.status-badge :status="$user->status"/></td>
                    <td><a class="btn sm primary" href="{{ route('admin.users.show', $user) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No users assigned to this role yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
