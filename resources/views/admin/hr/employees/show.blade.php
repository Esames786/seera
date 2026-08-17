@extends('layouts.admin')

@section('title', 'Employee Details')
@section('breadcrumb', 'HR &amp; Payroll / Employees / Employee Details')

@section('content')
    <x-admin.page-header :title="$employee->name" :description="'Employee profile with HR history and payroll context'">
        <a class="btn outline" href="{{ route('admin.hr.documents.create', ['employee' => $employee->id]) }}">+ Add Document</a>
        <a class="btn primary" href="{{ route('admin.hr.employees.edit', $employee) }}">Edit Employee</a>
    </x-admin.page-header>

    <div class="card profile-head">
        <div class="avatar lg">{{ $employee->initials() }}</div>
        <div>
            <h2 style="margin:0 0 8px">{{ $employee->name }}</h2>
            <div class="pill-row">
                <span class="badge blue">{{ $employee->employee_code }}</span>
                <x-admin.status-badge :status="$employee->status"/>
                <span class="badge purple">{{ $employee->designation?->name ?? 'No designation' }}</span>
                <span class="badge gray">{{ $employee->project?->name ?? 'Head Office' }}@if($employee->site) / {{ $employee->site->name }}@endif</span>
                <x-admin.status-badge :status="$employee->mobile_access ? 'yes' : 'no'"/>
            </div>
        </div>
    </div>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($employee->basic_salary, 2)" label="Basic Salary"/>
        <x-admin.metric-card color="red" :value="$employee->iqama_expiry_date?->toDateString() ?? '-'" label="IQAMA Expiry"/>
        <x-admin.metric-card color="green" :value="$presentDays.' / '.$workedDays" label="Attendance This Month"/>
        <x-admin.metric-card color="yellow" :value="$pendingCount" label="Pending Leave / Overtime"/>
    </div>

    <div class="tabs">
        @foreach (['overview' => 'Overview', 'documents' => 'Documents', 'attendance' => 'Attendance', 'leaves' => 'Leaves', 'overtime' => 'Overtime', 'salary' => 'Salary', 'payroll' => 'Payroll History'] as $anchor => $label)
            <a class="tab {{ $loop->first ? 'active' : '' }}" href="#{{ $anchor }}">{{ $label }}</a>
        @endforeach
    </div>
    <br/>

    <div class="split even" id="overview">
        <x-admin.data-table title="Employment Information" class="detail-table">
            <tbody>
                <tr><th>Employee Code</th><td>{{ $employee->employee_code }}</td></tr>
                <tr><th>Department</th><td>{{ $employee->department?->name ?? '-' }}</td></tr>
                <tr><th>Designation</th><td>{{ $employee->designation?->name ?? '-' }}</td></tr>
                <tr><th>Branch</th><td>{{ $employee->branch?->name ?? '-' }}</td></tr>
                <tr><th>Project / Site</th><td>{{ $employee->project?->name ?? 'Head Office' }}@if($employee->site) / {{ $employee->site->name }}@endif</td></tr>
                <tr><th>Manager</th><td>{{ $employee->manager?->name ?? '-' }}</td></tr>
                <tr><th>Joining Date</th><td>{{ $employee->joining_date?->toDateString() ?? '-' }}</td></tr>
                <tr><th>Contract</th><td>{{ $employee->contract_type }} ({{ $employee->contract_start_date?->toDateString() ?? '-' }} → {{ $employee->contract_end_date?->toDateString() ?? 'Open' }})</td></tr>
                <tr><th>Linked User Account</th><td>{{ $employee->user?->name ?? 'Not linked' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <x-admin.data-table title="Personal &amp; Saudi Documents" class="detail-table">
                <tbody>
                    <tr><th>Email</th><td>{{ $employee->email ?? '-' }}</td></tr>
                    <tr><th>Phone</th><td>{{ $employee->phone ?? '-' }}</td></tr>
                    <tr><th>Emergency Contact</th><td>{{ $employee->emergency_contact ?? '-' }}</td></tr>
                    <tr><th>Nationality</th><td>{{ $employee->nationality ?? '-' }}</td></tr>
                    <tr><th>IQAMA Number</th><td>{{ $employee->iqama_number ?? '-' }}</td></tr>
                    <tr><th>IQAMA Expiry</th><td>{{ $employee->iqama_expiry_date?->toDateString() ?? '-' }} <x-admin.status-badge :status="$employee->iqamaStatus()"/></td></tr>
                    <tr><th>Passport Number</th><td>{{ $employee->passport_number ?? '-' }}</td></tr>
                    <tr><th>Passport Expiry</th><td>{{ $employee->passport_expiry_date?->toDateString() ?? '-' }}</td></tr>
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table title="Payroll Information" class="detail-table">
                <tbody>
                    <tr><th>Basic Salary</th><td>SAR {{ number_format($employee->basic_salary, 2) }}</td></tr>
                    <tr><th>Payment Method</th><td>{{ $employee->payment_method }}</td></tr>
                    <tr><th>Bank Name</th><td>{{ $employee->bank_name ?? '-' }}</td></tr>
                    <tr><th>IBAN</th><td>{{ $employee->iban ?? '-' }}</td></tr>
                </tbody>
            </x-admin.data-table>
        </div>
    </div>

    <x-admin.data-table title="Documents" id="documents">
        <x-slot:headerActions>
            <a class="btn sm primary" href="{{ route('admin.hr.documents.create', ['employee' => $employee->id]) }}">+ Add Document</a>
        </x-slot:headerActions>
        <thead>
            <tr><th>Type</th><th>Number</th><th>Issue</th><th>Expiry</th><th>Validity</th><th>File</th></tr>
        </thead>
        <tbody>
            @forelse ($employee->documents as $document)
                <tr>
                    <td>{{ $document->document_type }}</td>
                    <td>{{ $document->document_number ?? '-' }}</td>
                    <td>{{ $document->issue_date?->toDateString() ?? '-' }}</td>
                    <td>{{ $document->expiry_date?->toDateString() ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$document->validityStatus()"/></td>
                    <td>{{ $document->file_path ? 'Uploaded' : 'Not uploaded' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No documents recorded.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <x-admin.data-table title="Recent Attendance" id="attendance">
        <thead>
            <tr><th>Date</th><th>Shift</th><th>Check In</th><th>Check Out</th><th>Late</th><th>Overtime</th><th>Source</th><th>Geo-Fence</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($attendance as $record)
                <tr>
                    <td>{{ $record->attendance_date->toDateString() }}</td>
                    <td>{{ $record->shift?->name ?? '-' }}</td>
                    <td>{{ $record->check_in ?? '-' }}</td>
                    <td>{{ $record->check_out ?? '-' }}</td>
                    <td>{{ $record->late_minutes }} min</td>
                    <td>{{ $record->overtime_minutes }} min</td>
                    <td><x-admin.status-badge :status="$record->source"/></td>
                    <td><x-admin.status-badge :status="$record->geofence_status"/></td>
                    <td><x-admin.status-badge :status="$record->status"/></td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No attendance records yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <div class="split even">
        <x-admin.data-table title="Leave Requests" id="leaves">
            <thead>
                <tr><th>Type</th><th>Start</th><th>End</th><th>Days</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($leaves as $leave)
                    <tr>
                        <td>{{ $leave->leaveType->name }}</td>
                        <td>{{ $leave->start_date->toDateString() }}</td>
                        <td>{{ $leave->end_date->toDateString() }}</td>
                        <td>{{ rtrim(rtrim((string) $leave->total_days, '0'), '.') }}</td>
                        <td><x-admin.status-badge :status="$leave->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-empty">No leave requests yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Overtime" id="overtime">
            <thead>
                <tr><th>Date</th><th>Hours</th><th>Rate</th><th>Amount</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($overtime as $record)
                    <tr>
                        <td>{{ $record->overtime_date->toDateString() }}</td>
                        <td>{{ $record->hours }}</td>
                        <td>SAR {{ number_format($record->rate, 2) }}</td>
                        <td>SAR {{ number_format($record->amount, 2) }}</td>
                        <td><x-admin.status-badge :status="$record->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-empty">No overtime records yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    </div>

    <x-admin.data-table title="Salary Structures" id="salary">
        <x-slot:headerActions>
            <a class="btn sm primary" href="{{ route('admin.hr.salary-structures.create') }}">+ Add Structure</a>
        </x-slot:headerActions>
        <thead>
            <tr><th>Effective From</th><th>Effective To</th><th>Basic</th><th>Allowances</th><th>Deductions</th><th>Net</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($employee->salaryStructures as $structure)
                <tr>
                    <td>{{ $structure->effective_from->toDateString() }}</td>
                    <td>{{ $structure->effective_to?->toDateString() ?? 'Open' }}</td>
                    <td>SAR {{ number_format($structure->basic_salary, 2) }}</td>
                    <td>SAR {{ number_format($structure->totalAllowances(), 2) }}</td>
                    <td>SAR {{ number_format($structure->totalDeductions(), 2) }}</td>
                    <td>SAR {{ number_format($structure->netSalary(), 2) }}</td>
                    <td><x-admin.status-badge :status="$structure->status"/></td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty">No salary structure defined yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <x-admin.data-table title="Payroll History" id="payroll">
        <thead>
            <tr><th>Payroll Run</th><th>Period</th><th>Basic</th><th>Allowances</th><th>Overtime</th><th>Deductions</th><th>Net</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($payrollItems as $item)
                <tr>
                    <td><a href="{{ route('admin.hr.payroll.show', $item->payrollRun) }}" style="color:var(--blue);font-weight:700">{{ $item->payrollRun->code }}</a></td>
                    <td>{{ $item->payrollRun->periodLabel() }}</td>
                    <td>SAR {{ number_format($item->basic_salary, 2) }}</td>
                    <td>SAR {{ number_format($item->total_allowances, 2) }}</td>
                    <td>SAR {{ number_format($item->overtime_amount, 2) }}</td>
                    <td>SAR {{ number_format($item->total_deductions, 2) }}</td>
                    <td>SAR {{ number_format($item->net_amount, 2) }}</td>
                    <td><x-admin.status-badge :status="$item->payrollRun->status"/></td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No payroll history yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
