@extends('layouts.admin')

@section('title', 'HR Dashboard')
@section('breadcrumb', 'HR &amp; Payroll / HR Dashboard')

@section('content')
    <x-admin.page-header title="HR Dashboard" description="Employees, attendance, leaves, overtime, documents and payroll at a glance">
        <a class="btn outline" href="{{ route('admin.hr.attendance.create') }}">+ Manual Attendance</a>
        <a class="btn primary" href="{{ route('admin.hr.employees.create') }}">+ Add Employee</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalEmployees" label="Total Employees"/>
        <x-admin.metric-card color="green" :value="$activeEmployees" label="Active Employees"/>
        <x-admin.metric-card color="green" :value="$presentToday" label="Present Today"/>
        <x-admin.metric-card color="yellow" :value="$lateToday" label="Late Today"/>
    </div>

    <div class="card-grid">
        <x-admin.metric-card color="cyan" :value="$onLeaveToday" label="On Leave Today"/>
        <x-admin.metric-card color="yellow" :value="$pendingLeaves" label="Pending Leaves"/>
        <x-admin.metric-card color="yellow" :value="$pendingOvertime" label="Pending Overtime"/>
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($currentPayroll?->net_amount ?? 0)" label="Current Month Payroll"/>
    </div>

    <div class="card-grid">
        <x-admin.metric-card color="red" :value="$expiringIqamas" label="Expiring IQAMAs (60 days)"/>
        <x-admin.metric-card color="red" :value="$expiringDocuments" label="Expiring Documents (60 days)"/>
        <x-admin.metric-card color="yellow" :value="$pendingEosb" label="Pending EOSB"/>
        <x-admin.metric-card color="red" :value="$absentToday" label="Absent Today"/>
    </div>

    <div class="split even">
        <div>
            <x-admin.data-table title="Today's Attendance Summary">
                <x-slot:headerActions>
                    <a class="btn sm primary" href="{{ route('admin.hr.attendance.index') }}">Open Attendance</a>
                </x-slot:headerActions>
                <thead>
                    <tr><th>Status</th><th>Employees</th></tr>
                </thead>
                <tbody>
                    <tr><td><x-admin.status-badge status="present"/></td><td>{{ $presentToday }}</td></tr>
                    <tr><td><x-admin.status-badge status="late"/></td><td>{{ $lateToday }}</td></tr>
                    <tr><td><x-admin.status-badge status="absent"/></td><td>{{ $absentToday }}</td></tr>
                    <tr><td><x-admin.status-badge status="on leave"/></td><td>{{ $onLeaveToday }}</td></tr>
                </tbody>
            </x-admin.data-table>

            <div class="chart-placeholder">Chart Placeholder: Present / Late / Absent / Leave trend</div>
        </div>

        <div>
            <x-admin.data-table title="Pending HR Approvals">
                <thead>
                    <tr><th>Queue</th><th>Count</th><th></th></tr>
                </thead>
                <tbody>
                    <tr><td>Leave Requests</td><td>{{ $pendingLeaves }}</td><td><a class="btn sm" href="{{ route('admin.hr.leaves.index', ['status' => 'pending']) }}">Open</a></td></tr>
                    <tr><td>Overtime Claims</td><td>{{ $pendingOvertime }}</td><td><a class="btn sm" href="{{ route('admin.hr.overtime.index', ['status' => 'pending']) }}">Open</a></td></tr>
                    <tr><td>Document Expiry</td><td>{{ $expiringDocuments }}</td><td><a class="btn sm" href="{{ route('admin.hr.documents.index', ['validity' => 'expiring']) }}">Open</a></td></tr>
                    <tr><td>Payroll Approval</td><td>{{ $pendingPayrollApprovals }}</td><td><a class="btn sm" href="{{ route('admin.hr.payroll.index') }}">Open</a></td></tr>
                    <tr><td>End of Service</td><td>{{ $pendingEosb }}</td><td><a class="btn sm" href="{{ route('admin.hr.eosb.index', ['status' => 'draft']) }}">Open</a></td></tr>
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table title="IQAMA Expiring Soon">
                <thead>
                    <tr><th>Employee</th><th>Department</th><th>Expiry</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($expiringEmployees as $employee)
                        <tr>
                            <td><a href="{{ route('admin.hr.employees.show', $employee) }}" style="color:var(--blue);font-weight:700">{{ $employee->name }}</a></td>
                            <td>{{ $employee->department?->name ?? '-' }}</td>
                            <td>{{ $employee->iqama_expiry_date?->toDateString() ?? '-' }}</td>
                            <td><x-admin.status-badge :status="$employee->iqamaStatus()"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">No IQAMA expiring in the next 60 days.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>
    </div>

    <x-admin.data-table title="Latest Leave Requests">
        <x-slot:headerActions>
            <a class="btn sm primary" href="{{ route('admin.hr.leaves.index') }}">View All</a>
        </x-slot:headerActions>
        <thead>
            <tr><th>Employee</th><th>Leave Type</th><th>Start</th><th>End</th><th>Days</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($recentLeaves as $leave)
                <tr>
                    <td>{{ $leave->employee->name }}</td>
                    <td>{{ $leave->leaveType->name }}</td>
                    <td>{{ $leave->start_date->toDateString() }}</td>
                    <td>{{ $leave->end_date->toDateString() }}</td>
                    <td>{{ rtrim(rtrim((string) $leave->total_days, '0'), '.') }}</td>
                    <td><x-admin.status-badge :status="$leave->status"/></td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No leave requests yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
