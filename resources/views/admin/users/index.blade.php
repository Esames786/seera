@extends('layouts.admin')

@section('title', 'Users')
@section('breadcrumb', 'Administration / Users')

@section('content')
    <x-admin.page-header title="Users Listing" description="Manage ERP web and mobile users">
        <a class="btn primary" href="{{ route('admin.users.create') }}">+ Add New User</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalUsers" label="Total Users"/>
        <x-admin.metric-card color="green" :value="$activeUsers" label="Active Users"/>
        <x-admin.metric-card color="yellow" :value="$mobileUsers" label="Mobile App Users"/>
        <x-admin.metric-card color="red" :value="$lockedUsers" label="Locked / Inactive"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:230px" type="search" name="search" value="{{ request('search') }}" placeholder="Search name, email, employee ID"/>
        <select class="select" style="width:170px" name="department">
            <option value="">All Departments</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(request('department') == $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:170px" name="role">
            <option value="">All Roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected(request('role') == $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            @foreach (['active', 'inactive', 'locked', 'pending'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn primary" href="{{ route('admin.users.create') }}">+ Add New User</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Users Listing" subtitle="ERP user table with role and scope">
        <thead>
            <tr>
                <th>Employee ID</th><th>Name</th><th>Email / Phone</th><th>Department</th>
                <th>Primary Role</th><th>Assigned Project/Site</th><th>Mobile Access</th>
                <th>Last Login</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->employee_id ?? '-' }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}<br><span class="small">{{ $user->phone }}</span></td>
                    <td>{{ $user->department?->name ?? '-' }}</td>
                    <td>{{ $user->primaryRole()?->name ?? '-' }}</td>
                    <td>{{ $user->project?->name ? $user->project->name.($user->site ? ' / '.$user->site->name : '') : ($user->branch?->name ?? 'All Company') }}</td>
                    <td><x-admin.status-badge :status="$user->mobile_access ? 'enabled' : 'disabled'"/></td>
                    <td>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                    <td><x-admin.status-badge :status="$user->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.users.show', $user)"
                            :edit="route('admin.users.edit', $user)"
                            :delete="route('admin.users.destroy', $user)"
                            :name="$user->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">No users found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }} of {{ $users->total() }}</span>
            {{ $users->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
