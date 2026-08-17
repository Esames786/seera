@extends('layouts.admin')

@section('title', 'Leaves')
@section('breadcrumb', 'HR &amp; Payroll / Leaves')

@section('content')
    <x-admin.page-header title="Leave Management" description="Annual, sick, emergency and unpaid leave requests with approvals">
        <a class="btn primary" href="{{ route('admin.hr.leaves.create') }}">+ Add Leave</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$pendingLeaves" label="Pending Requests"/>
        <x-admin.metric-card color="green" :value="$approvedLeaves" label="Approved Requests"/>
        <x-admin.metric-card color="red" :value="$rejectedLeaves" label="Rejected Requests"/>
        <x-admin.metric-card color="blue" :value="rtrim(rtrim(number_format($totalLeaveDays, 1), '0'), '.')" label="Approved Leave Days"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:230px" type="search" name="search" value="{{ request('search') }}" placeholder="Employee code or name..."/>
        <select class="select" style="width:180px" name="leave_type">
            <option value="">All Leave Types</option>
            @foreach ($leaveTypes as $type)
                <option value="{{ $type->id }}" @selected(request('leave_type') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.hr.leaves.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Leave Requests">
        <thead>
            <tr><th>Employee</th><th>Leave Type</th><th>Start</th><th>End</th><th>Days</th><th>Reason</th><th>Approved By</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($leaves as $leave)
                <tr>
                    <td><a href="{{ route('admin.hr.employees.show', $leave->employee) }}" style="color:var(--blue);font-weight:700">{{ $leave->employee->name }}</a></td>
                    <td>{{ $leave->leaveType->name }}</td>
                    <td>{{ $leave->start_date->toDateString() }}</td>
                    <td>{{ $leave->end_date->toDateString() }}</td>
                    <td>{{ rtrim(rtrim((string) $leave->total_days, '0'), '.') }}</td>
                    <td>{{ $leave->reason ? Str::limit($leave->reason, 30) : '-' }}</td>
                    <td>{{ $leave->approver?->name ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$leave->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.hr.leaves.show', $leave)"
                            :edit="route('admin.hr.leaves.edit', $leave)"
                            :delete="route('admin.hr.leaves.destroy', $leave)"
                            :name="$leave->employee->name.' leave'">
                            @if ($leave->status === 'pending')
                                <form method="POST" action="{{ route('admin.hr.leaves.approve', $leave) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Approve</button>
                                </form>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No leave requests found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $leaves->firstItem() ?? 0 }}-{{ $leaves->lastItem() ?? 0 }} of {{ $leaves->total() }}</span>
            {{ $leaves->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
