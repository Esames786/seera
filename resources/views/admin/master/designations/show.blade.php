@extends('layouts.admin')

@section('title', 'Designation Details')
@section('breadcrumb', 'Master Setup / Designations / Designation Details')

@section('content')
    <x-admin.page-header :title="$designation->name" description="Designation overview with assigned employees">
        <a class="btn primary" href="{{ route('admin.master.designations.edit', $designation) }}">Edit Designation</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$designation->users->count()" label="Employees"/>
        <x-admin.metric-card color="green" :value="$designation->grade ?? '-'" label="Grade / Level"/>
        <x-admin.metric-card color="yellow" :value="$designation->defaultRole?->name ?? 'None'" label="Default Role"/>
        <x-admin.metric-card color="cyan" :value="$designation->mobile_access_default ? 'Yes' : 'No'" label="Mobile Access Default"/>
    </div>

    <div class="split">
        <x-admin.data-table title="Designation Information" class="detail-table">
            <tbody>
                <tr><th>Designation Name</th><td>{{ $designation->name }}</td></tr>
                <tr><th>Department</th><td>{{ $designation->department?->name ?? '-' }}</td></tr>
                <tr><th>Grade / Level</th><td>{{ $designation->grade ?? '-' }}</td></tr>
                <tr><th>Default Role</th><td>{{ $designation->defaultRole?->name ?? '-' }}</td></tr>
                <tr><th>Description</th><td>{{ $designation->description ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$designation->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Employees with this Designation">
            <thead>
                <tr><th>Employee ID</th><th>Name</th><th>Branch</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($designation->users as $user)
                    <tr>
                        <td>{{ $user->employee_id ?? '-' }}</td>
                        <td><a href="{{ route('admin.users.show', $user) }}" style="color:var(--blue);font-weight:700">{{ $user->name }}</a></td>
                        <td>{{ $user->branch?->name ?? '-' }}</td>
                        <td><x-admin.status-badge :status="$user->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="table-empty">No employees hold this designation yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    </div>
@endsection
