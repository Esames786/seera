@extends('layouts.admin')

@section('title', 'EOSB Details')
@section('breadcrumb', 'HR &amp; Payroll / End of Service / Details')

@section('content')
    <x-admin.page-header :title="'End of Service: '.$record->employee->name" :description="'Termination date '.$record->termination_date->toDateString()">
        <a class="btn outline" href="{{ route('admin.hr.eosb.edit', $record) }}">Edit</a>
        @if ($record->status === 'draft')
            <form method="POST" action="{{ route('admin.hr.eosb.approve', $record) }}">
                @csrf
                <button type="submit" class="btn primary">Approve</button>
            </form>
        @endif
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$record->service_years" label="Service Years"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($record->eosb_amount, 2)" label="EOSB Amount"/>
        <x-admin.metric-card color="red" :value="'SAR '.number_format($record->deductions, 2)" label="Deductions"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($record->final_amount, 2)" label="Final Amount"/>
    </div>

    <x-admin.data-table title="Settlement Details" class="detail-table">
        <tbody>
            <tr><th>Employee</th><td><a href="{{ route('admin.hr.employees.show', $record->employee) }}" style="color:var(--blue);font-weight:700">{{ $record->employee->name }}</a> ({{ $record->employee->employee_code }})</td></tr>
            <tr><th>Department</th><td>{{ $record->employee->department?->name ?? '-' }}</td></tr>
            <tr><th>Designation</th><td>{{ $record->employee->designation?->name ?? '-' }}</td></tr>
            <tr><th>Termination Date</th><td>{{ $record->termination_date->toDateString() }}</td></tr>
            <tr><th>Service Years</th><td>{{ $record->service_years }}</td></tr>
            <tr><th>Last Basic Salary</th><td>SAR {{ number_format($record->last_basic_salary, 2) }}</td></tr>
            <tr><th>EOSB Amount</th><td>SAR {{ number_format($record->eosb_amount, 2) }}</td></tr>
            <tr><th>Leave Salary</th><td>SAR {{ number_format($record->leave_salary, 2) }}</td></tr>
            <tr><th>Other Dues</th><td>SAR {{ number_format($record->other_dues, 2) }}</td></tr>
            <tr><th>Deductions</th><td>SAR {{ number_format($record->deductions, 2) }}</td></tr>
            <tr><th>Final Amount</th><td><strong>SAR {{ number_format($record->final_amount, 2) }}</strong></td></tr>
            <tr><th>Reason</th><td>{{ $record->reason ?? '-' }}</td></tr>
            <tr><th>Status</th><td><x-admin.status-badge :status="$record->status"/></td></tr>
            <tr><th>Approved By</th><td>{{ $record->approver?->name ?? '-' }}</td></tr>
            <tr><th>Approved At</th><td>{{ $record->approved_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
        </tbody>
    </x-admin.data-table>
@endsection
