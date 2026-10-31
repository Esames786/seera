@extends('layouts.admin')

@section('title', __('HR Dashboard'))
@section('breadcrumb', __('HR & Payroll / HR Dashboard'))

@section('content')
    <x-admin.page-header title="HR Dashboard" description="Employees, attendance, leaves, overtime, documents and payroll at a glance">
        @if(auth()->user()->hasPermission('Attendance', 'create'))<a class="btn outline" href="{{ route('admin.hr.attendance.create') }}">{{ __('+ Manual Attendance') }}</a>@endif
        @if(auth()->user()->hasPermission('HR', 'create'))<a class="btn primary" href="{{ route('admin.hr.employees.create') }}">{{ __('+ Add Employee') }}</a>@endif
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalEmployees" label="Total Employees"/>
        <x-admin.metric-card color="green" :value="$activeEmployees" label="Active Employees"/>
        @if($canViewAttendance)<x-admin.metric-card color="green" :value="$presentToday" label="Present Today"/>@endif
        @if($canViewAttendance)<x-admin.metric-card color="yellow" :value="$lateToday" label="Late Today"/>@endif
    </div>

    <div class="card-grid">
        <x-admin.metric-card color="cyan" :value="$onLeaveToday" label="On Leave Today"/>
        <x-admin.metric-card color="yellow" :value="$pendingLeaves" label="Pending Leaves"/>
        @if($canViewPayroll)<x-admin.metric-card color="yellow" :value="$pendingOvertime" label="Pending Overtime"/>@endif
        @if($canViewPayroll)<x-admin.metric-card color="blue" :value="'SAR '.number_format($currentPayroll?->net_amount ?? 0)" label="Current Month Payroll"/>@endif
    </div>

    <div class="card-grid">
        <x-admin.metric-card color="red" :value="$expiringIqamas" label="Expiring IQAMAs (60 days)"/>
        <x-admin.metric-card color="red" :value="$expiringDocuments" label="Expiring Documents (60 days)"/>
        @if($canViewPayroll)<x-admin.metric-card color="yellow" :value="$pendingEosb" label="Pending EOSB"/>@endif
        @if($canViewAttendance)<x-admin.metric-card color="red" :value="$absentToday" label="Absent Today"/>@endif
    </div>

    <div class="split even">
        <div>
            @if($canViewAttendance)
            <x-admin.data-table title="Today's Attendance Summary">
                <x-slot:headerActions>
                    <a class="btn sm primary" href="{{ route('admin.hr.attendance.index') }}">{{ __('Open Attendance') }}</a>
                </x-slot:headerActions>
                <thead>
                    <tr><th>{{ __('Status') }}</th><th>{{ __('Employees') }}</th></tr>
                </thead>
                <tbody>
                    <tr><td><x-admin.status-badge status="present"/></td><td>{{ $presentToday }}</td></tr>
                    <tr><td><x-admin.status-badge status="late"/></td><td>{{ $lateToday }}</td></tr>
                    <tr><td><x-admin.status-badge status="absent"/></td><td>{{ $absentToday }}</td></tr>
                    <tr><td><x-admin.status-badge status="on leave"/></td><td>{{ $onLeaveToday }}</td></tr>
                </tbody>
            </x-admin.data-table>

            <div class="help-box">{{ __('Recorded attendance and approved leave counts for today; not a historical trend or payroll calculation.') }}</div>
            @else
            <div class="help-box">{{ __('Attendance figures are hidden without Attendance view permission.') }}</div>
            @endif
        </div>

        <div>
            <x-admin.data-table title="Pending HR Approvals">
                <thead>
                    <tr><th>{{ __('Queue') }}</th><th>{{ __('Count') }}</th><th></th></tr>
                </thead>
                <tbody>
                    <tr><td>{{ __('Leave Requests') }}</td><td>{{ $pendingLeaves }}</td><td><a class="btn sm" href="{{ route('admin.hr.leaves.index', ['status' => 'pending']) }}">{{ __('Open') }}</a></td></tr>
                    @if($canViewPayroll)<tr><td>{{ __('Overtime Claims') }}</td><td>{{ $pendingOvertime }}</td><td><a class="btn sm" href="{{ route('admin.hr.overtime.index', ['status' => 'pending']) }}">{{ __('Open') }}</a></td></tr>@endif
                    <tr><td>{{ __('Document Expiry') }}</td><td>{{ $expiringDocuments }}</td><td><a class="btn sm" href="{{ route('admin.hr.documents.index', ['validity' => 'expiring']) }}">{{ __('Open') }}</a></td></tr>
                    @if($canViewPayroll)
                    <tr><td>{{ __('Payroll Approval') }}</td><td>{{ $pendingPayrollApprovals }}</td><td><a class="btn sm" href="{{ route('admin.hr.payroll.index') }}">{{ __('Open') }}</a></td></tr>
                    <tr><td>{{ __('End of Service') }}</td><td>{{ $pendingEosb }}</td><td><a class="btn sm" href="{{ route('admin.hr.eosb.index', ['status' => 'draft']) }}">{{ __('Open') }}</a></td></tr>
                    @endif
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table title="IQAMA Expiring Soon">
                <thead>
                    <tr><th>{{ __('Employee') }}</th><th>{{ __('Department') }}</th><th>{{ __('Expiry') }}</th><th>{{ __('Status') }}</th></tr>
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
                        <tr><td colspan="4" class="table-empty">{{ __('No IQAMA expiring in the next 60 days.') }}</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>
    </div>

    <x-admin.data-table title="Latest Leave Requests">
        <x-slot:headerActions>
            <a class="btn sm primary" href="{{ route('admin.hr.leaves.index') }}">{{ __('View All') }}</a>
        </x-slot:headerActions>
        <thead>
            <tr><th>{{ __('Employee') }}</th><th>{{ __('Leave Type') }}</th><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Days') }}</th><th>{{ __('Status') }}</th></tr>
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
                <tr><td colspan="6" class="table-empty">{{ __('No leave requests yet.') }}</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
