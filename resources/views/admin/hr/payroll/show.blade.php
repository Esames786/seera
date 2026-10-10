@extends('layouts.admin')

@section('title', 'Payroll Details')
@section('breadcrumb', 'HR & Payroll / Payroll / Payroll Details')

@section('content')
    @php
        /** @var \App\Models\PayrollRun $run */
        $money = fn ($v) => 'SAR '.number_format((float) $v, 2);
        $journal = $run->journalEntry;
        $selfUrl = route('admin.hr.payroll.show', $run, false);
        $accountingClass = match ($run->accounting_status) {
            \App\Models\PayrollRun::ACCOUNTING_POSTED => 'green',
            \App\Models\PayrollRun::ACCOUNTING_FAILED => 'red',
            \App\Models\PayrollRun::ACCOUNTING_REVIEW => 'yellow',
            \App\Models\PayrollRun::ACCOUNTING_REVERSED => 'gray',
            default => 'gray',
        };
        $sections = ['summary' => 'Run Summary', 'items' => 'Payroll Items', 'accounting' => 'Accounting'];
        if ($activity !== null) $sections['activity'] = 'Activity';
    @endphp

    <x-admin.page-header :title="'Payroll Run: '.$run->code" :description="$run->periodLabel().' — '.$run->period_start->toDateString().' to '.$run->period_end->toDateString().'. Read-only view; Process, Approve, Post and Reverse are explicit actions.'">
        @if ($canEdit)
            <a class="btn outline" href="{{ route('admin.hr.payroll.edit', $run) }}">Edit</a>
        @endif
        @if ($canProcess)
            <form method="POST" action="{{ route('admin.hr.payroll.process', $run) }}">
                @csrf
                <button type="submit" class="btn warning">Process Payroll</button>
            </form>
        @endif
        @if ($canApprove)
            <form method="POST" action="{{ route('admin.hr.payroll.approve', $run) }}">
                @csrf
                <button type="submit" class="btn primary">Approve Payroll</button>
            </form>
        @endif
        @if ($canPost)
            <form method="POST" action="{{ route('admin.hr.payroll.post', $run) }}">
                @csrf
                <button type="submit" class="btn primary">{{ $run->accounting_status === \App\Models\PayrollRun::ACCOUNTING_FAILED ? 'Retry Posting' : 'Post to Accounting' }}</button>
            </form>
        @endif
        @if ($canReverse)
            <form method="POST" action="{{ route('admin.hr.payroll.reverse', $run) }}" onsubmit="var reason = prompt('Reason for reversing this payroll posting (kept on the run):'); if (!reason) return false; this.reason.value = reason;">
                @csrf
                <input type="hidden" name="reason" value=""/>
                <button type="submit" class="btn danger" title="Posts an opposite journal; the original journal and the approved run stay in history">Reverse Posting</button>
            </form>
        @endif
        @if ($canViewJournal && $journal)
            <a class="btn outline" href="{{ route('admin.accounting.journal-entries.show', $journal) }}">View Journal</a>
        @endif
        <a class="btn outline" href="{{ route('admin.hr.payroll.index') }}">Back to Payroll</a>
    </x-admin.page-header>

    {{-- Persistent run identity --}}
    <div class="card workspace-header" data-workspace-header>
        <div class="identity">
            <h2>{{ $run->code }}</h2>
            <div>
                <x-admin.status-badge :status="$run->status"/>
                <span class="badge {{ $accountingClass }}">{{ $run->accountingLabel() }}</span>
            </div>
        </div>
        <dl>
            <dt>Period</dt><dd>{{ $run->periodLabel() }} · {{ $run->period_start->toDateString() }} → {{ $run->period_end->toDateString() }}</dd>
            <dt>Scope</dt><dd>{{ $run->branch?->name ?? 'All branches' }} / {{ $run->project?->name ?? 'All projects' }}</dd>
            <dt>Employees</dt><dd>{{ $totals->employees }}</dd>
            <dt>Approved</dt><dd>{{ $run->approver?->name ?? '-' }}{{ $run->approved_at ? ' · '.$run->approved_at->format('Y-m-d H:i') : '' }}</dd>
        </dl>
        <dl>
            <dt>Gross payroll</dt><dd>{{ $money($totals->gross) }}</dd>
            <dt>Allowances / Overtime</dt><dd>{{ $money($totals->allowances) }} / {{ $money($totals->overtime) }}</dd>
            <dt>Deductions</dt><dd>{{ $money($totals->deductions) }}</dd>
            <dt>Net payroll (payable)</dt><dd><strong>{{ $money($totals->net) }}</strong></dd>
            <dt>Accounting</dt>
            <dd>
                {{ $run->accountingLabel() }}
                @if ($journal && $canViewJournal) · <a href="{{ route('admin.accounting.journal-entries.show', $journal) }}">{{ $journal->journal_number }}</a>@endif
            </dd>
        </dl>
    </div>

    <nav class="tabs workspace-nav" aria-label="Payroll run sections">
        @foreach ($sections as $key => $label)
            <a class="tab" href="#{{ $key }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Run Summary --}}
    <div class="card-grid" id="summary">
        <x-admin.metric-card color="blue" :value="$totals->employees" label="Employees"/>
        <x-admin.metric-card color="green" :value="$money($totals->gross)" label="Gross Payroll"/>
        <x-admin.metric-card color="red" :value="$money($totals->deductions)" label="Total Deductions"/>
        <x-admin.metric-card color="cyan" :value="$money($totals->net)" label="Net Payroll"/>
    </div>

    <x-admin.data-table title="Payroll Run Information" class="detail-table">
        <tbody>
            <tr><th>Code</th><td>{{ $run->code }}</td></tr>
            <tr><th>Month</th><td>{{ $run->periodLabel() }}</td></tr>
            <tr><th>Period</th><td>{{ $run->period_start->toDateString() }} → {{ $run->period_end->toDateString() }}</td></tr>
            <tr><th>Branch</th><td>{{ $run->branch?->name ?? 'All branches' }}</td></tr>
            <tr><th>Project</th><td>{{ $run->project?->name ?? 'All projects' }}</td></tr>
            <tr><th>Status</th><td><x-admin.status-badge :status="$run->status"/></td></tr>
            <tr><th>Processed At</th><td>{{ $run->processed_at?->format('Y-m-d H:i') ?? 'Not processed yet' }}</td></tr>
            <tr><th>Approved By</th><td>{{ $run->approver?->name ?? '-' }}</td></tr>
            <tr><th>Approved At</th><td>{{ $run->approved_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
            <tr><th>Basic / Allowances / Overtime</th><td>{{ $money($totals->basic) }} / {{ $money($totals->allowances) }} / {{ $money($totals->overtime) }}</td></tr>
            <tr><th>Accounting</th><td>{{ $run->accountingLabel() }}</td></tr>
            <tr><th>Notes</th><td>{{ $run->notes ?? '-' }}</td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- Payroll Items --}}
    <x-admin.data-table title="Payroll Items" :subtitle="$totals->employees.' employees · stored values from Process; net = basic + allowances + approved overtime − deductions' " id="items">
        <thead>
            <tr><th>Employee</th><th>Department</th><th>Project / Cost Center</th><th>Present / Leave</th><th>Basic</th><th>Allowances</th><th>Overtime</th><th>Gross</th><th>Deductions</th><th>Net</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>@if ($canViewEmployee && $item->employee)<a href="{{ route('admin.hr.employees.show', $item->employee) }}" style="color:var(--blue);font-weight:700">{{ $item->employee->name }}</a>@else{{ $item->employee?->name ?? '-' }}@endif <span class="small">{{ $item->employee?->employee_code }}</span></td>
                    <td>{{ $item->employee?->department?->name ?? '-' }}</td>
                    <td>{{ $item->employee?->project?->name ?? 'Head Office' }}</td>
                    <td>{{ $item->present_days }} / {{ $item->leave_days }}</td>
                    <td>{{ number_format($item->basic_salary, 2) }}</td>
                    <td>{{ number_format($item->total_allowances, 2) }}</td>
                    <td>{{ number_format($item->overtime_amount, 2) }}</td>
                    <td>{{ number_format($item->gross_amount, 2) }}</td>
                    <td>{{ number_format($item->total_deductions, 2) }}</td>
                    <td><strong>{{ number_format($item->net_amount, 2) }}</strong></td>
                    <td><a class="btn sm outline" href="{{ route('admin.hr.payroll.payslip', [$run, $item->id, 'return_to' => $selfUrl.'#items']) }}">Payslip</a></td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">No payroll items yet. Process this payroll run to generate them.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $items->firstItem() ?? 0 }}-{{ $items->lastItem() ?? 0 }} of {{ $items->total() }}</span>
            @if ($items->hasPages()){{ $items->links() }}@endif
        </x-slot:footer>
    </x-admin.data-table>

    {{-- Accounting --}}
    <x-admin.data-table title="Accounting" subtitle="Approval is HR authorization; posting is the financial recognition (Dr salary expense per employee with the employee's project, Cr payroll payable for the net, Cr deduction liability). Salary payment is a later phase." class="detail-table" id="accounting">
        <tbody>
            <tr><th>Accounting state</th><td><span class="badge {{ $accountingClass }}">{{ $run->accountingLabel() }}</span></td></tr>
            @if ($journal)
                <tr><th>Journal</th><td>@if ($canViewJournal)<a href="{{ route('admin.accounting.journal-entries.show', $journal) }}" style="font-weight:700">{{ $journal->journal_number }}</a>@else{{ $journal->journal_number }}@endif · <x-admin.status-badge :status="$journal->status"/></td></tr>
                <tr><th>Posting date</th><td>{{ $journal->journal_date?->toDateString() }} (payroll period end)</td></tr>
                <tr><th>Debit / Credit total</th><td>{{ number_format($journal->total_debit, 2) }} / {{ number_format($journal->total_credit, 2) }}</td></tr>
                <tr><th>Posted at</th><td>{{ $run->posted_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
            @else
                <tr><th>Journal</th><td>{{ $run->isApproved() ? 'No journal yet' : 'Created when the approved run is posted' }}</td></tr>
            @endif
            @if ($run->posting_error)
                <tr><th>Posting error</th><td class="warn" style="color:var(--red)">{{ $run->posting_error }}</td></tr>
            @endif
            @if ($run->reversalJournal)
                <tr><th>Reversal</th><td>@if ($canViewJournal)<a href="{{ route('admin.accounting.journal-entries.show', $run->reversalJournal) }}">{{ $run->reversalJournal->journal_number }}</a>@else{{ $run->reversalJournal->journal_number }}@endif · {{ $run->reversed_at?->format('Y-m-d H:i') }} · {{ $run->reversal_reason }}</td></tr>
            @endif
            @if ($canViewJournal && $journal)
                @foreach ($journal->lines as $line)
                    <tr><th class="small">{{ $loop->first ? 'Journal lines' : '' }}</th><td class="small">{{ $line->account?->label() }} · {{ $line->description }} · Dr {{ number_format($line->debit, 2) }} / Cr {{ number_format($line->credit, 2) }}</td></tr>
                @endforeach
            @endif
        </tbody>
        @if ($canPost || $canReverse)
            <x-slot:footer>
                @if ($canPost)
                    <form method="POST" action="{{ route('admin.hr.payroll.post', $run) }}">
                        @csrf
                        <button type="submit" class="btn sm primary">{{ $run->accounting_status === \App\Models\PayrollRun::ACCOUNTING_FAILED ? 'Retry Posting' : 'Post to Accounting' }}</button>
                    </form>
                @endif
                <span class="small">Posting is idempotent: a retry never creates a second journal.</span>
            </x-slot:footer>
        @endif
    </x-admin.data-table>

    {{-- Activity --}}
    @if ($activity !== null)
        <x-admin.data-table title="Activity" subtitle="Latest entries that name this run, limited to the users your role may see" id="activity">
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
                    <tr><td colspan="5" class="table-empty">No activity recorded for this run yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    @endif

    <div class="note">
        Present and leave days are informational; they do not change pay in the current system. GOSI, WPS / bank files and salary payment settlement are not yet operational.
    </div>
@endsection
