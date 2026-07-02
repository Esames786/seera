@extends('layouts.admin')

@section('title', 'User Details')
@section('breadcrumb', 'Administration / Users / User Details')

@section('content')
    <x-admin.page-header title="User Details" description="View user profile, access scope, permissions, and activity logs">
        <a class="btn primary" href="{{ route('admin.users.edit', $user) }}">Edit User</a>
    </x-admin.page-header>

    @php $primaryRole = $user->primaryRole(); @endphp

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="collect([$user->project_id, $user->site_id])->filter()->count()" label="Assigned Scopes"/>
        <x-admin.metric-card color="green" :value="$primaryRole?->permissions->count() ?? 0" label="Active Permissions"/>
        <x-admin.metric-card color="yellow" :value="$user->roles->count()" label="Assigned Roles"/>
        <x-admin.metric-card color="red" :value="$recentLogs->where('status', 'failed')->count()" label="Security Alerts"/>
    </div>

    <div class="split">
        <div class="card profile-card">
            <div class="avatar lg" style="margin:auto">{{ $user->initials() }}</div>
            <h3>{{ $user->name }}</h3>
            <div>
                <span class="badge blue">{{ $primaryRole?->name ?? 'No role' }}</span>
                <x-admin.status-badge :status="$user->status"/>
            </div>
            <hr style="border:0;border-top:1px solid #e5e7eb;margin:18px 0">
            <p class="small">{{ $user->email }}</p>
            <p class="small">{{ $user->phone ?? '-' }}</p>
            <p class="small">{{ $user->project?->name ? $user->project->name.($user->site ? ' / '.$user->site->name : '') : ($user->branch?->name ?? 'All Company') }}</p>
            <p class="small">Joined: {{ $user->joining_date?->format('M d, Y') ?? '-' }}</p>
        </div>

        <div>
            <div class="tabs">
                <div class="tab active">Overview</div>
                <div class="tab">Permissions</div>
                <div class="tab">Projects/Sites</div>
                <div class="tab">Activity Logs</div>
            </div>

            <x-admin.data-table title="Access Summary" class="detail-table">
                <tbody>
                    <tr><th>Employee ID</th><td>{{ $user->employee_id ?? '-' }}</td></tr>
                    <tr><th>Primary Role</th><td>{{ $primaryRole?->name ?? '-' }}</td></tr>
                    <tr><th>Parent Role</th><td>{{ $primaryRole?->parent?->name ?? 'None' }}</td></tr>
                    <tr><th>Access Scope</th><td>{{ $primaryRole?->access_scope ?? '-' }}</td></tr>
                    <tr><th>Department</th><td>{{ $user->department?->name ?? '-' }}</td></tr>
                    <tr><th>Designation</th><td>{{ $user->designation?->name ?? '-' }}</td></tr>
                    <tr><th>Branch</th><td>{{ $user->branch?->name ?? '-' }}</td></tr>
                    <tr><th>Contract Type</th><td>{{ $user->contract_type ?? '-' }}</td></tr>
                    <tr><th>Iqama Number</th><td>{{ $user->iqama_number ?? '-' }} @if($user->iqama_expiry_date)<span class="small">(expires {{ $user->iqama_expiry_date->format('M d, Y') }})</span>@endif</td></tr>
                    <tr><th>Mobile App Access</th><td><x-admin.status-badge :status="$user->mobile_access ? 'enabled' : 'disabled'"/></td></tr>
                    <tr><th>Two Factor Auth</th><td><x-admin.status-badge :status="$user->two_factor_enabled ? 'enabled' : 'disabled'"/></td></tr>
                    <tr><th>Last Login</th><td>{{ $user->last_login_at?->format('M d, Y H:i') ?? 'Never' }}</td></tr>
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table title="Recent Activity">
                <thead>
                    <tr><th>Time</th><th>Activity</th><th>Module</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($recentLogs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('M d, H:i') }}</td>
                            <td>{{ $log->action }}</td>
                            <td>{{ $log->module }}</td>
                            <td><x-admin.status-badge :status="$log->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">No activity recorded for this user yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>
    </div>
@endsection
