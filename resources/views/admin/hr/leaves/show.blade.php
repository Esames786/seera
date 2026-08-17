@extends('layouts.admin')

@section('title', 'Leave Details')
@section('breadcrumb', 'HR &amp; Payroll / Leaves / Leave Details')

@section('content')
    <x-admin.page-header :title="'Leave Request: '.$leave->employee->name" :description="$leave->leaveType->name.' — '.$leave->start_date->toDateString().' to '.$leave->end_date->toDateString()">
        <a class="btn outline" href="{{ route('admin.hr.leaves.edit', $leave) }}">Edit</a>
        @if ($leave->status === 'pending')
            <form method="POST" action="{{ route('admin.hr.leaves.approve', $leave) }}">
                @csrf
                <button type="submit" class="btn primary">Approve</button>
            </form>
        @endif
    </x-admin.page-header>

    <div class="split even">
        <x-admin.data-table title="Request Details" class="detail-table">
            <tbody>
                <tr><th>Employee</th><td><a href="{{ route('admin.hr.employees.show', $leave->employee) }}" style="color:var(--blue);font-weight:700">{{ $leave->employee->name }}</a> ({{ $leave->employee->employee_code }})</td></tr>
                <tr><th>Department</th><td>{{ $leave->employee->department?->name ?? '-' }}</td></tr>
                <tr><th>Designation</th><td>{{ $leave->employee->designation?->name ?? '-' }}</td></tr>
                <tr><th>Leave Type</th><td>{{ $leave->leaveType->name }}</td></tr>
                <tr><th>Start Date</th><td>{{ $leave->start_date->toDateString() }}</td></tr>
                <tr><th>End Date</th><td>{{ $leave->end_date->toDateString() }}</td></tr>
                <tr><th>Total Days</th><td>{{ rtrim(rtrim((string) $leave->total_days, '0'), '.') }}</td></tr>
                <tr><th>Reason</th><td>{{ $leave->reason ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$leave->status"/></td></tr>
                <tr><th>Approved By</th><td>{{ $leave->approver?->name ?? '-' }}</td></tr>
                <tr><th>Approved At</th><td>{{ $leave->approved_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
                <tr><th>Rejection Reason</th><td>{{ $leave->rejection_reason ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <div class="form-section">
            <div class="section-title">Reject Request</div>
            <div class="section-body">
                @if ($leave->status === 'pending')
                    <form method="POST" action="{{ route('admin.hr.leaves.reject', $leave) }}">
                        @csrf
                        <label for="rejection_reason">Rejection Reason *</label>
                        <textarea id="rejection_reason" name="rejection_reason" class="textarea" required></textarea>
                        <div class="form-actions" style="margin-top:12px">
                            <button type="submit" class="btn danger">Reject Request</button>
                        </div>
                    </form>
                @else
                    <div class="help-box">This request is already {{ $leave->status }}. Only pending requests can be approved or rejected.</div>
                @endif
            </div>
        </div>
    </div>
@endsection
