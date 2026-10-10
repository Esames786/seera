@extends('layouts.admin')

@section('title', 'Payslip')
@section('breadcrumb', 'HR & Payroll / Payroll / Payslip')
@push('styles')
<style>
.payslip { max-width: 820px; margin: 0 auto; background: #fff; color: #111; padding: 28px 32px; border: 1px solid var(--line); border-radius: 10px; }
.payslip header { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
.payslip h1 { font-size: 22px; margin: 0; }
.payslip h2 { font-size: 15px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: .5px; }
.payslip table { width: 100%; border-collapse: collapse; font-size: 14px; }
.payslip th, .payslip td { border: 1px solid #ccc; padding: 6px 9px; text-align: start; }
.payslip th { background: #f3f4f6; width: 38%; }
.payslip .amount { text-align: end; font-variant-numeric: tabular-nums; }
.payslip .total td { font-weight: 800; background: #eef6ff; }
.payslip .muted { color: #555; font-size: 12px; }
.payslip-actions { max-width: 820px; margin: 0 auto 14px; display: flex; gap: 8px; flex-wrap: wrap; }
@media print {
    .sidebar, .topbar, .payslip-actions, .note, .alert { display: none !important; }
    .content, .page { padding: 0 !important; margin: 0 !important; }
    .payslip { border: 0; border-radius: 0; max-width: none; padding: 0; }
}
</style>
@endpush

@section('content')
    @php
        /** @var \App\Models\PayrollRun $run */
        /** @var \App\Models\PayrollRunItem $item */
        $employee = $item->employee;
        $money = fn ($v) => number_format((float) $v, 2);
    @endphp

    <div class="payslip-actions">
        <a class="btn outline" href="{{ $returnTo }}">Back</a>
        <button type="button" class="btn primary" onclick="window.print()">Print / Save as PDF</button>
        <span class="small">Values are the stored payroll row as processed and approved; later salary changes never alter this payslip.</span>
    </div>

    <article class="payslip" data-payslip>
        <header>
            <div>
                <h1>{{ $company?->name ?? config('app.name') }}</h1>
                @if($company?->name_ar)<div class="muted" dir="rtl">{{ $company->name_ar }}</div>@endif
                <div class="muted">{{ $company?->address ?? '' }}{{ $company?->city ? ' · '.$company->city : '' }}{{ $company?->cr_number ? ' · CR '.$company->cr_number : '' }}</div>
            </div>
            <div style="text-align:end">
                <h1>Payslip</h1>
                <div class="muted">{{ $run->periodLabel() }}</div>
                <div class="muted">Run {{ $run->code }} · {{ $run->period_start->toDateString() }} → {{ $run->period_end->toDateString() }}</div>
            </div>
        </header>

        <h2>Employee</h2>
        <table>
            <tbody>
                <tr><th>Employee Code</th><td>{{ $employee?->employee_code ?? '-' }}</td></tr>
                <tr><th>Employee Name</th><td>{{ $employee?->name ?? 'Employee #'.$item->employee_id }}</td></tr>
                <tr><th>Department</th><td>{{ $employee?->department?->name ?? '-' }}</td></tr>
                <tr><th>Designation</th><td>{{ $employee?->designation?->name ?? '-' }}</td></tr>
                <tr><th>Project</th><td>{{ $employee?->project?->name ?? 'Head Office' }}</td></tr>
                <tr><th>Branch</th><td>{{ $employee?->branch?->name ?? '-' }}</td></tr>
                <tr><th>IQAMA / ID</th><td>{{ $employee?->iqama_number ?? '-' }}</td></tr>
                <tr><th>Payment Method</th><td>{{ $employee?->payment_method ?? '-' }}{{ $employee?->bank_name ? ' · '.$employee->bank_name : '' }}{{ $employee?->iban ? ' · '.$employee->iban : '' }}</td></tr>
            </tbody>
        </table>

        <h2>Earnings and deductions (SAR)</h2>
        <table>
            <tbody>
                <tr><th>Basic Salary</th><td class="amount">{{ $money($item->basic_salary) }}</td></tr>
                <tr><th>Allowances</th><td class="amount">{{ $money($item->total_allowances) }}</td></tr>
                <tr><th>Approved Overtime</th><td class="amount">{{ $money($item->overtime_amount) }}</td></tr>
                <tr class="total"><td>Gross</td><td class="amount">{{ $money($item->gross_amount) }}</td></tr>
                <tr><th>Deductions</th><td class="amount">{{ $money($item->total_deductions) }}</td></tr>
                <tr class="total"><td>Net Salary</td><td class="amount">{{ $money($item->net_amount) }}</td></tr>
            </tbody>
        </table>

        <h2>Attendance (informational)</h2>
        <table>
            <tbody>
                <tr><th>Present days</th><td>{{ $item->present_days }}</td></tr>
                <tr><th>Leave days</th><td>{{ $item->leave_days }}</td></tr>
            </tbody>
        </table>
        <p class="muted">Present and leave days are recorded for information; they do not change pay in the current system.</p>

        <h2>Payroll status</h2>
        <table>
            <tbody>
                <tr><th>Run status</th><td>{{ ucfirst($run->status) }}{{ $run->approved_at ? ' · approved '.$run->approved_at->format('Y-m-d').($run->approver ? ' by '.$run->approver->name : '') : '' }}</td></tr>
                <tr><th>Accounting</th><td>{{ $run->accountingLabel() }}{{ $run->journalEntry ? ' · '.$run->journalEntry->journal_number : '' }}</td></tr>
                <tr><th>Remarks</th><td>{{ $item->remarks ?? '-' }}</td></tr>
                <tr><th>Generated</th><td>{{ $generatedAt->format('Y-m-d H:i') }} · payslip reference {{ $run->code }}/{{ $item->id }}</td></tr>
            </tbody>
        </table>
        <p class="muted">This payslip shows the salary recognised by the company for the period. Payment of the net salary is settled separately; GOSI contributions and WPS bank files are not part of this document in the current system.</p>
    </article>
@endsection
