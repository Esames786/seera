@extends('layouts.admin')

@section('title', __('HR Reports'))
@section('breadcrumb', __('Reports / HR Reports'))

@section('content')
    <x-admin.page-header :title="__('HR Reports')" :description="__('Read-only workforce and monthly attendance/leave summary from permitted HR records. No salary, bank or payroll data is shown.')"/>

    <x-admin.filter-bar :action="route('admin.reports.hr.index')">
        <label for="hr-report-month">{{ __('Report month') }}</label>
        <input class="input" id="hr-report-month" type="month" name="month" value="{{ $selectedMonth }}" required/>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.reports.hr.index') }}">{{ __('Reset') }}</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <div class="help-box">{{ __('Reporting period: :from to :to', ['from' => $from, 'to' => $to]) }}</div>
    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$employeeCount" :label="__('Employees in scope')"/>
        <x-admin.metric-card color="green" :value="$activeEmployees" :label="__('Active employees in scope')"/>
    </div>

    <x-admin.data-table :title="__('Leave requests by start month')">
        <thead><tr><th>{{ __('Status') }}</th><th>{{ __('Requests') }}</th></tr></thead>
        <tbody>
            @foreach (['pending', 'approved', 'rejected'] as $status)
                <tr><td>{{ __(ucfirst($status)) }}</td><td>{{ $leaveCounts[$status] }}</td></tr>
            @endforeach
        </tbody>
    </x-admin.data-table>
    <p class="small">{{ __('Leave counts use the request start date, not days consumed or leave balances.') }}</p>

    @if ($canViewAttendance)
        <x-admin.data-table :title="__('Attendance records by status')">
            <thead><tr><th>{{ __('Status') }}</th><th>{{ __('Records') }}</th></tr></thead>
            <tbody>
                @foreach (['present', 'late', 'absent', 'leave', 'half day'] as $status)
                    <tr><td>{{ __(ucfirst($status)) }}</td><td>{{ $attendanceCounts[$status] }}</td></tr>
                @endforeach
            </tbody>
        </x-admin.data-table>
        <p class="small">{{ __('Attendance figures count recorded entries, not unique employees, worked days or payroll entitlement.') }}</p>
        <a class="btn outline" href="{{ route('admin.hr.attendance.index', ['from' => $from, 'to' => $to]) }}">{{ __('Open Attendance') }}</a>
    @else
        <div class="help-box">{{ __('Attendance figures are hidden because you do not have Attendance view permission.') }}</div>
    @endif

    <div class="help-box">{{ __('Official GOSI, WPS/Mudad and payroll financial reports are not provided by this operational overview.') }}</div>
@endsection
