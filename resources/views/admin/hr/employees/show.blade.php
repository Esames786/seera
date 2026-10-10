@extends('layouts.admin')

@section('title', 'Employee Details')
@section('breadcrumb', 'HR & Payroll / Employees / Employee Details')

@section('content')
    @php
        /** @var \App\Models\Employee $employee */
        $money = fn ($v) => 'SAR '.number_format((float) $v, 2);
        $days = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    @endphp

    <x-admin.page-header :title="$employee->name" :description="__('workspace.hr_readonly')">
        @if($canEditEmployee)
            <a class="btn outline" href="{{ route('admin.hr.employees.edit', $employee) }}#documents">{{ __('ui.attach_document') }}</a>
            <a class="btn primary" href="{{ route('admin.hr.employees.edit', $employee) }}">{{ __('ui.edit_employee') }}</a>
        @endif
        <a class="btn outline" href="{{ route('admin.hr.employees.index') }}">{{ __('Back to Employees') }}</a>
    </x-admin.page-header>

    <x-admin.linked-identity :link="$linkedUser" :title="__('ui.linked_user')"/>

    {{-- Persistent employee identity --}}
    <div class="card workspace-header" data-workspace-header>
        <div class="identity">
            <div class="avatar lg">{{ $employee->initials() }}</div>
            <h2>{{ $employee->employee_code }} — {{ $employee->name }}</h2>
            <div class="pill-row">
                <x-admin.status-badge :status="$employee->status"/>
                <span class="badge purple">{{ $employee->designation?->name ?? 'No designation' }}</span>
                <span class="badge gray">{{ $employee->project?->name ?? 'Head Office' }}@if($employee->site) / {{ $employee->site->name }}@endif</span>
                <span class="badge blue">{{ $employee->employee_classification }}</span>
            </div>
        </div>
        <dl>
            <dt>{{ __('workspace.department') }}</dt><dd>{{ $employee->department?->name ?? '-' }}</dd>
            <dt>{{ __('workspace.manager') }}</dt><dd>{{ $employee->manager?->name ?? '-' }}</dd>
            <dt>{{ __('workspace.hr_contract_type') }}</dt><dd>{{ $contract['contract_type'] ?? '-' }} · {{ $contract['contract_start'] ?? '-' }} → {{ $contract['contract_end'] ?? __('workspace.hr_open_ended') }}</dd>
            <dt>{{ __('workspace.hr_iqama') }}</dt><dd>{{ $contract['iqama_number'] ?? '-' }} · {{ $contract['iqama_expiry'] ?? '-' }} <x-admin.status-badge :status="$contract['iqama_status']"/></dd>
        </dl>
        <dl>
            @if($canViewAttendance)
                <dt>{{ __('workspace.hr_attendance_month') }}</dt><dd>{{ $presentDays }} / {{ $workedDays }}</dd>
            @endif
            <dt>{{ __('workspace.hr_remaining') }} ({{ $leaveBalance['year'] }})</dt><dd>{{ $days($leaveBalance['remaining']) }} / {{ $leaveBalance['entitlement'] }} {{ __('days') }}</dd>
            <dt>{{ __('workspace.hr_pending_items') }}</dt><dd>{{ $pendingLeaves }}@if($canViewOvertime) / {{ $pendingOvertime }}@endif</dd>
            @if($canViewPayroll)
                <dt>{{ __('Basic Salary') }}</dt><dd>{{ $money($employee->basic_salary) }}</dd>
            @endif
        </dl>
    </div>

    @foreach($contract['alerts'] as $alert)
        <div class="alert flash">{{ $alert }}</div>
    @endforeach

    <nav class="tabs workspace-nav" aria-label="Employee sections">
        @foreach ($sections as $key => $label)
            <a class="tab" href="#{{ $key }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Overview --}}
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
                <tr><th>Classification</th><td><span class="badge blue">{{ $employee->employee_classification }}</span></td></tr>
                <tr><th>Contract</th><td>{{ $employee->contract_type }} ({{ $contract['contract_start'] ?? '-' }} → {{ $contract['contract_end'] ?? 'Open' }}) <x-admin.status-badge :status="$contract['contract_status']"/></td></tr>
                <tr><th>Annual Leave Entitlement</th><td>{{ $leaveBalance['entitlement'] }} days</td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <x-admin.data-table :title="__('workspace.hr_contract')" class="detail-table">
                <tbody>
                    <tr><th>Email</th><td>{{ $employee->email ?? '-' }}</td></tr>
                    <tr><th>Phone</th><td>{{ $employee->phone ?? '-' }}</td></tr>
                    <tr><th>Emergency Contact</th><td>{{ $employee->emergency_contact ?? '-' }}</td></tr>
                    <tr><th>Nationality</th><td>{{ $employee->nationality ?? '-' }}</td></tr>
                    <tr><th>IQAMA Number</th><td>{{ $employee->iqama_number ?? '-' }}</td></tr>
                    <tr><th>IQAMA Expiry</th><td>{{ $employee->iqama_expiry_date?->toDateString() ?? '-' }} <x-admin.status-badge :status="$contract['iqama_status']"/></td></tr>
                    <tr><th>Passport</th><td>{{ $employee->passport_number ?? '-' }} · {{ $employee->passport_expiry_date?->toDateString() ?? '-' }} <x-admin.status-badge :status="$contract['passport_status']"/></td></tr>
                    <tr><th>Medical Insurance</th><td>{{ $employee->insurance_number ?? '-' }} · {{ $employee->insurance_expiry_date?->toDateString() ?? '-' }} <x-admin.status-badge :status="$contract['insurance_status']"/></td></tr>
                    <tr><th>Driving License</th><td>{{ $employee->driving_license_number ?? '-' }} · {{ $employee->driving_license_expiry_date?->toDateString() ?? '-' }}</td></tr>
                    <tr><th>{{ __('workspace.hr_documents_summary') }}</th><td>{{ __('workspace.hr_documents_counts', ['total' => $contract['documents_total'], 'expiring' => $contract['documents_expiring'], 'expired' => $contract['documents_expired']]) }}</td></tr>
                </tbody>
            </x-admin.data-table>

            @if($canViewPayroll)
                <x-admin.data-table title="Payroll Information" class="detail-table">
                    <tbody>
                        <tr><th>Basic Salary</th><td>{{ $money($employee->basic_salary) }}</td></tr>
                        <tr><th>Housing Allowance</th><td>{{ $money($employee->housing_allowance) }}</td></tr>
                        <tr><th>Transport Allowance</th><td>{{ $money($employee->transport_allowance) }}</td></tr>
                        <tr><th>Food Allowance</th><td>{{ $money($employee->food_allowance) }}</td></tr>
                        <tr><th>Fuel Allowance</th><td>{{ $money($employee->fuel_allowance) }}</td></tr>
                        <tr><th>Other Allowance</th><td>{{ $money($employee->other_allowance) }}</td></tr>
                        <tr><th>Payment Method</th><td>{{ $employee->payment_method }}</td></tr>
                        <tr><th>Bank Name</th><td>{{ $employee->bank_name ?? '-' }}</td></tr>
                        <tr><th>IBAN</th><td>{{ $employee->iban ?? '-' }}</td></tr>
                    </tbody>
                </x-admin.data-table>
            @endif
        </div>
    </div>

    {{-- Documents --}}
    <x-admin.data-table :title="__('workspace.hr_documents')" id="documents">
        <x-slot:headerActions>
            @if($canEditEmployee)<a class="btn sm primary" href="{{ route('admin.hr.employees.edit', $employee) }}#documents">{{ __('ui.attach_document') }}</a>@endif
            <a class="btn sm outline" href="{{ $links['documents_all'] }}">{{ __('workspace.hr_view_register') }}</a>
        </x-slot:headerActions>
        <thead>
            <tr><th>Type</th><th>Number</th><th>Issue</th><th>Expiry</th><th>Validity</th><th>File</th></tr>
        </thead>
        <tbody>
            @forelse ($employee->documents as $document)
                <tr>
                    <td>{{ $document->document_type }}@if($document->document_subtype) <span class="small">· {{ $document->document_subtype }}</span>@endif</td>
                    <td>{{ $document->document_number ?? '-' }}</td>
                    <td>{{ $document->issue_date?->toDateString() ?? '-' }}</td>
                    <td>{{ $document->expiry_date?->toDateString() ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$document->validityStatus()"/></td>
                    <td>
                        @if ($document->file_path)
                            <a href="{{ route('admin.hr.documents.view', $document) }}" target="_blank" rel="noopener" style="color:var(--blue);font-weight:700">View</a>
                            <span class="small">·</span>
                            <a href="{{ route('admin.hr.documents.download', $document) }}" style="color:var(--blue);font-weight:700">Download</a>
                        @else
                            <span class="small">Not uploaded</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No documents recorded.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    {{-- Attendance --}}
    @if($canViewAttendance)
        <x-admin.data-table :title="__('workspace.hr_attendance')" :subtitle="__('workspace.hr_attendance_help')" id="attendance">
            <x-slot:headerActions>
                @if($links['attendance_create'])<a class="btn sm primary" href="{{ $links['attendance_create'] }}">{{ __('workspace.hr_add_attendance') }}</a>@endif
                <a class="btn sm outline" href="{{ $links['attendance_all'] }}">{{ __('workspace.hr_view_register') }}</a>
            </x-slot:headerActions>
            <thead>
                <tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Shift</th><th>Status</th><th>Late</th><th>Overtime</th><th>Project</th><th>Site</th><th>Source</th><th>Geo-Fence</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($attendance as $record)
                    <tr>
                        <td>{{ $record->attendance_date->toDateString() }}</td>
                        <td>{{ $record->check_in ?? '-' }}</td>
                        <td>{{ $record->check_out ?? '-' }}</td>
                        <td>{{ $record->shift?->name ?? '-' }}</td>
                        <td><x-admin.status-badge :status="$record->status"/></td>
                        <td>{{ $record->late_minutes }} min</td>
                        <td>{{ $record->overtime_minutes }} min</td>
                        <td>{{ $record->project?->name ?? '-' }}</td>
                        <td>{{ $record->site?->name ?? '-' }}</td>
                        <td>@if($record->isGpsRecord())<span class="badge green">{{ $record->sourceLabel() }}</span>@else<x-admin.status-badge :status="$record->source"/>@endif</td>
                        <td>{{ \App\Models\AttendanceRecord::geofenceLabel($record->geofence_status) }}@if($record->isGpsRecord() && $record->check_in_distance_meters !== null) <span class="small">· {{ number_format($record->check_in_distance_meters) }} m</span>@endif</td>
                        <td>@if($canEditAttendance)<a class="btn sm outline" href="{{ route('admin.hr.attendance.edit', ['attendance_record' => $record, 'return_to' => $links['origin_attendance']]) }}">Edit</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="table-empty">{{ __('workspace.hr_no_attendance') }}</td></tr>
                @endforelse
            </tbody>
            <x-slot:footer>
                <span class="small">{{ __('Showing the latest :count of :total', ['count' => $attendance->count(), 'total' => $attendance->total()]) }}</span>
                @if($attendance->hasPages()){{ $attendance->links() }}@endif
            </x-slot:footer>
        </x-admin.data-table>
    @endif

    {{-- Leaves --}}
    <x-admin.data-table :title="__('workspace.hr_leaves')" :subtitle="__('workspace.hr_leave_help')" id="leaves">
        <x-slot:headerActions>
            @if($links['leaves_create'])<a class="btn sm primary" href="{{ $links['leaves_create'] }}">{{ __('workspace.hr_add_leave') }}</a>@endif
            <a class="btn sm outline" href="{{ $links['leaves_all'] }}">{{ __('workspace.hr_view_register') }}</a>
        </x-slot:headerActions>
        <thead>
            <tr><th>{{ __('workspace.hr_entitlement') }} {{ $leaveBalance['year'] }}</th><th>{{ __('workspace.hr_used') }}</th><th>{{ __('workspace.hr_pending') }}</th><th>{{ __('workspace.hr_remaining') }}</th><th colspan="4"></th></tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>{{ $leaveBalance['entitlement'] }}</strong> days</td>
                <td>{{ $days($leaveBalance['used']) }} days</td>
                <td>{{ $days($leaveBalance['pending']) }} days</td>
                <td><strong style="color:{{ $leaveBalance['remaining'] < 0 ? 'var(--red)' : 'var(--green)' }}">{{ $days($leaveBalance['remaining']) }}</strong> days</td>
                <td colspan="4"></td>
            </tr>
            <tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Reason</th><th>Decided by</th><th></th></tr>
            @forelse ($leaves as $leave)
                <tr>
                    <td>{{ $leave->leaveType?->name ?? '-' }}@if($leave->attachment_path) <a href="{{ route('admin.hr.leaves.attachment', $leave) }}" title="Supporting document" style="color:var(--blue)">📎</a>@endif</td>
                    <td>{{ $leave->start_date->toDateString() }}</td>
                    <td>{{ $leave->end_date->toDateString() }}</td>
                    <td>{{ $days($leave->total_days) }}</td>
                    <td><x-admin.status-badge :status="$leave->status"/></td>
                    <td class="small">{{ $leave->reason ?? '-' }}{{ $leave->rejection_reason ? ' · '.$leave->rejection_reason : '' }}</td>
                    <td class="small">{{ $leave->approver?->name ?? '-' }}</td>
                    <td>
                        <a class="btn sm outline" href="{{ route('admin.hr.leaves.show', ['leave_request' => $leave, 'return_to' => $links['origin_leaves']]) }}">{{ __('workspace.view') }}</a>
                        @if($canEditLeave && $leave->status === 'pending')<a class="btn sm outline" href="{{ route('admin.hr.leaves.edit', ['leave_request' => $leave, 'return_to' => $links['origin_leaves']]) }}">Edit</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">{{ __('workspace.hr_no_leaves') }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">{{ __('Showing the latest :count of :total', ['count' => $leaves->count(), 'total' => $leaves->total()]) }}</span>
            @if($leaves->hasPages()){{ $leaves->links() }}@endif
        </x-slot:footer>
    </x-admin.data-table>

    {{-- Overtime --}}
    @if($canViewOvertime)
        <x-admin.data-table :title="__('workspace.hr_overtime')" :subtitle="__('workspace.hr_overtime_help')" id="overtime">
            <x-slot:headerActions>
                @if($links['overtime_create'])<a class="btn sm primary" href="{{ $links['overtime_create'] }}">{{ __('workspace.hr_add_overtime') }}</a>@endif
                <a class="btn sm outline" href="{{ $links['overtime_all'] }}">{{ __('workspace.hr_view_register') }}</a>
            </x-slot:headerActions>
            <thead>
                <tr><th>Date</th><th>Hours</th><th>Rate</th><th>Amount</th><th>Attendance</th><th>Status</th><th>Reason</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($overtime as $record)
                    <tr>
                        <td>{{ $record->overtime_date->toDateString() }}</td>
                        <td>{{ $record->hours }}</td>
                        <td>{{ $money($record->rate) }}</td>
                        <td>{{ $money($record->amount) }}</td>
                        <td>{{ $record->attendanceRecord?->attendance_date?->toDateString() ?? '-' }}</td>
                        <td><x-admin.status-badge :status="$record->status"/></td>
                        <td class="small">{{ $record->reason ?? '-' }}</td>
                        <td>@if($canEditOvertime && $record->status === 'pending')<a class="btn sm outline" href="{{ route('admin.hr.overtime.edit', ['overtime_record' => $record, 'return_to' => $links['origin_overtime']]) }}">Edit</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="table-empty">{{ __('workspace.hr_no_overtime') }}</td></tr>
                @endforelse
            </tbody>
            <x-slot:footer>
                <span class="small">{{ __('Showing the latest :count of :total', ['count' => $overtime->count(), 'total' => $overtime->total()]) }} · {{ __('workspace.hr_approved_hours') }}: {{ $approvedOvertimeHours }}</span>
                @if($overtime->hasPages()){{ $overtime->links() }}@endif
            </x-slot:footer>
        </x-admin.data-table>
    @endif

    {{-- Salary Structures --}}
    @if($canViewPayroll)
        @if ($employee->salaryStructureOutOfDate())
            <div class="alert flash" id="salary-mismatch">
                <strong>Payroll information and salary structure differ.</strong>
                The profile now shows basic SAR {{ number_format($employee->basic_salary, 2) }} and allowances SAR {{ number_format($employee->defaultAllowanceTotal(), 2) }},
                while the active structure carries basic SAR {{ number_format($employee->activeSalaryStructure->basic_salary, 2) }} and allowances SAR {{ number_format($employee->activeSalaryStructure->totalAllowances(), 2) }}.
                Payroll uses the structure. Raise a new structure from the new figures rather than editing the old one, so past payroll stays as it was run.
            </div>
        @endif

        <x-admin.data-table :title="__('workspace.hr_salary')" :subtitle="__('workspace.hr_salary_help')" id="salary">
            <x-slot:headerActions>
                @if($links['salary_create'])<a class="btn sm primary" href="{{ $links['salary_create'] }}">+ New Structure From Profile</a>@endif
                <a class="btn sm outline" href="{{ $links['salary_all'] }}">{{ __('workspace.hr_view_register') }}</a>
            </x-slot:headerActions>
            <thead>
                <tr><th>Effective From</th><th>Effective To</th><th>Basic</th><th>Allowances</th><th>Deductions</th><th>Net</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($employee->salaryStructures as $structure)
                    <tr>
                        <td>{{ $structure->effective_from->toDateString() }}</td>
                        <td>{{ $structure->effective_to?->toDateString() ?? 'Open' }}</td>
                        <td>{{ $money($structure->basic_salary) }}</td>
                        <td>{{ $money($structure->totalAllowances()) }}</td>
                        <td>{{ $money($structure->totalDeductions()) }}</td>
                        <td>{{ $money($structure->netSalary()) }}</td>
                        <td><x-admin.status-badge :status="$structure->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="table-empty">No salary structure yet. "+ New Structure From Profile" opens the form already filled with the pay above.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>

        {{-- Payroll --}}
        <x-admin.data-table :title="__('workspace.hr_payroll')" :subtitle="__('workspace.hr_payroll_help')" id="payroll">
            <x-slot:headerActions>
                <a class="btn sm outline" href="{{ $links['payroll_all'] }}">{{ __('workspace.hr_view_register') }}</a>
            </x-slot:headerActions>
            <thead>
                <tr><th>Payroll Run</th><th>Period</th><th>Basic</th><th>Allowances</th><th>Overtime</th><th>Deductions</th><th>Net</th><th>Present / Leave days</th><th>Run Status</th><th>Accounting</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($payrollItems as $item)
                    <tr>
                        <td>{{ $item->payrollRun?->code ?? '-' }}</td>
                        <td>{{ $item->payrollRun?->periodLabel() ?? '-' }}</td>
                        <td>{{ $money($item->basic_salary) }}</td>
                        <td>{{ $money($item->total_allowances) }}</td>
                        <td>{{ $money($item->overtime_amount) }}</td>
                        <td>{{ $money($item->total_deductions) }}</td>
                        <td><strong>{{ $money($item->net_amount) }}</strong></td>
                        <td>{{ $item->present_days }} / {{ $item->leave_days }}</td>
                        <td>@if($item->payrollRun)<x-admin.status-badge :status="$item->payrollRun->status"/>@else - @endif</td>
                        <td class="small">{{ $item->payrollRun?->accountingLabel() ?? '-' }}</td>
                        <td>@if($item->payrollRun)<a class="btn sm outline" href="{{ route('admin.hr.payroll.show', $item->payrollRun) }}">{{ __('workspace.hr_open_run') }}</a> <a class="btn sm outline" href="{{ route('admin.hr.payroll.payslip', [$item->payrollRun, $item->id, 'return_to' => $selfUrl.'#payroll']) }}">{{ __('workspace.hr_payslip') }}</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="table-empty">{{ __('workspace.hr_no_payroll') }}</td></tr>
                @endforelse
            </tbody>
            <x-slot:footer>
                <span class="small">{{ __('Showing the latest :count of :total', ['count' => $payrollItems->count(), 'total' => $payrollItems->total()]) }}</span>
                @if($payrollItems->hasPages()){{ $payrollItems->links() }}@endif
            </x-slot:footer>
        </x-admin.data-table>
    @endif

    {{-- Activity --}}
    @if ($activity !== null)
        <x-admin.data-table :title="__('workspace.activity')" :subtitle="__('workspace.hr_activity_help')" id="activity">
            <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
                @forelse ($activity as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $log->user_name }}</td>
                        <td>{{ $log->module }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-empty">{{ __('workspace.hr_no_activity') }}</td></tr>
                @endforelse
            </tbody>
            <x-slot:footer>
                <a class="btn sm outline" href="{{ route('admin.activity-logs.index', ['search' => $employee->employee_code]) }}">{{ __('View all') }}</a>
            </x-slot:footer>
        </x-admin.data-table>
    @endif
@endsection
