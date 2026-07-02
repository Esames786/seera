@extends('layouts.admin')

@section('title', 'Department Details')
@section('breadcrumb', 'Master Setup / Departments / Department Details')

@section('content')
    <x-admin.page-header :title="$department->name" description="Department overview with users, designations, and roles">
        <a class="btn primary" href="{{ route('admin.master.departments.edit', $department) }}">Edit Department</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$department->users->count()" label="Users"/>
        <x-admin.metric-card color="green" :value="$department->designations->count()" label="Designations"/>
        <x-admin.metric-card color="yellow" :value="$department->roles->count()" label="Roles"/>
        <x-admin.metric-card color="cyan" :value="strtoupper($department->code)" label="Department Code"/>
    </div>

    <div class="split">
        <x-admin.data-table title="Department Information" class="detail-table">
            <tbody>
                <tr><th>Department Name</th><td>{{ $department->name }}</td></tr>
                <tr><th>Department Code</th><td>{{ $department->code }}</td></tr>
                <tr><th>Department Head</th><td>{{ $department->head?->name ?? '-' }}</td></tr>
                <tr><th>Description</th><td>{{ $department->description ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$department->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <x-admin.data-table title="Designations in this Department">
                <thead>
                    <tr><th>Designation</th><th>Grade</th><th>Default Role</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($department->designations as $designation)
                        <tr>
                            <td><a href="{{ route('admin.master.designations.show', $designation) }}" style="color:var(--blue);font-weight:700">{{ $designation->name }}</a></td>
                            <td>{{ $designation->grade ?? '-' }}</td>
                            <td>{{ $designation->defaultRole?->name ?? '-' }}</td>
                            <td><x-admin.status-badge :status="$designation->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">No designations yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table title="Users in this Department">
                <thead>
                    <tr><th>Employee ID</th><th>Name</th><th>Designation</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($department->users as $user)
                        <tr>
                            <td>{{ $user->employee_id ?? '-' }}</td>
                            <td><a href="{{ route('admin.users.show', $user) }}" style="color:var(--blue);font-weight:700">{{ $user->name }}</a></td>
                            <td>{{ $user->designation?->name ?? '-' }}</td>
                            <td><x-admin.status-badge :status="$user->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">No users yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>
    </div>
@endsection
