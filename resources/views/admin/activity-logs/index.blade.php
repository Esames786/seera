@extends('layouts.admin')

@section('title', 'Activity Logs')
@section('breadcrumb', 'Administration / Activity Logs')

@section('content')
    <x-admin.page-header title="Activity Logs" description="Audit trail for security, user, role, permission and workflow changes"/>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Search activity..."/>
        <select class="select" style="width:170px" name="module">
            <option value="">All Modules</option>
            @foreach ($modules as $module)
                <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
            @endforeach
        </select>
        <select class="select" style="width:160px" name="action">
            <option value="">All Actions</option>
            @foreach (['Created', 'Updated', 'Deleted', 'Login', 'Failed'] as $action)
                <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
            @endforeach
        </select>
        <input class="input" style="width:150px" type="date" name="date" value="{{ request('date') }}"/>
    </x-admin.filter-bar>

    <x-admin.data-table title="Audit Trail / Activity Logs">
        <thead>
            <tr>
                <th>Date &amp; Time</th><th>User</th><th>Module</th><th>Action</th>
                <th>Old Value</th><th>New Value</th><th>IP Address</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $log->user_name }}</td>
                    <td>{{ $log->module }}</td>
                    <td>{{ $log->action }}@if($log->description)<br><span class="small">{{ $log->description }}</span>@endif</td>
                    <td>{{ $log->old_value ?? '-' }}</td>
                    <td>{{ $log->new_value ?? '-' }}</td>
                    <td>{{ $log->ip_address }}</td>
                    <td><x-admin.status-badge :status="$log->status"/></td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No activity found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $logs->firstItem() ?? 0 }}-{{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }}</span>
            {{ $logs->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
