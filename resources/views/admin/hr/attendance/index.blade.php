@extends('layouts.admin')

@section('title', 'Attendance')
@section('breadcrumb', 'HR &amp; Payroll / Attendance')

@section('content')
    <x-admin.page-header title="Attendance Management" description="Manual, mobile and offline attendance with geo-fence validation">
        <a class="btn primary" href="{{ route('admin.hr.attendance.create') }}">+ Manual Attendance</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="green" :value="$presentToday" label="Present Today"/>
        <x-admin.metric-card color="yellow" :value="$lateToday" label="Late Today"/>
        <x-admin.metric-card color="red" :value="$absentToday" label="Absent Today"/>
        <x-admin.metric-card color="cyan" :value="$outsideGeofence" label="Outside Geo-Fence Today"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:200px" type="search" name="search" value="{{ request('search') }}" placeholder="Employee code or name..."/>
        <input class="input" style="width:150px" type="date" name="from" value="{{ request('from') }}"/>
        <input class="input" style="width:150px" type="date" name="to" value="{{ request('to') }}"/>
        <select class="select" style="width:160px" name="department">
            <option value="">All Departments</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(request('department') == $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="project">
            <option value="">All Projects</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="site">
            <option value="">All Sites</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}" @selected(request('site') == $site->id)>{{ $site->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:130px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <select class="select" style="width:130px" name="source">
            <option value="">All Sources</option>
            @foreach ($sources as $source)
                <option value="{{ $source }}" @selected(request('source') === $source)>{{ ucfirst($source) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.hr.attendance.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Attendance Records">
        <thead>
            <tr>
                <th>Date</th><th>Employee</th><th>Project / Site</th><th>Shift</th>
                <th>Check In</th><th>Check Out</th><th>Late</th><th>Overtime</th>
                <th>Source</th><th>Geo-Fence</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td>{{ $record->attendance_date->toDateString() }}</td>
                    <td><a href="{{ route('admin.hr.employees.show', $record->employee) }}" style="color:var(--blue);font-weight:700">{{ $record->employee->name }}</a></td>
                    <td>{{ $record->project?->name ?? 'Head Office' }}@if($record->site) / {{ $record->site->name }}@endif</td>
                    <td>{{ $record->shift?->name ?? '-' }}</td>
                    <td>{{ $record->check_in ?? '-' }}</td>
                    <td>{{ $record->check_out ?? '-' }}</td>
                    <td>{{ $record->late_minutes }} min</td>
                    <td>{{ $record->overtime_minutes }} min</td>
                    <td><x-admin.status-badge :status="$record->source"/></td>
                    <td><x-admin.status-badge :status="$record->geofence_status"/></td>
                    <td><x-admin.status-badge :status="$record->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :edit="route('admin.hr.attendance.edit', $record)"
                            :delete="route('admin.hr.attendance.destroy', $record)"
                            :name="$record->employee->name.' - '.$record->attendance_date->toDateString()"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="12" class="table-empty">No attendance records found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $records->firstItem() ?? 0 }}-{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</span>
            {{ $records->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
