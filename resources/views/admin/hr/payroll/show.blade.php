@extends('layouts.admin')

@section('title', 'Payroll Details')
@section('breadcrumb', 'HR &amp; Payroll / Payroll / Payroll Details')

@section('content')
    <x-admin.page-header :title="'Payroll Run: '.$run->code" :description="$run->periodLabel().' — '.$run->period_start->toDateString().' to '.$run->period_end->toDateString()">
        @if (! in_array($run->status, ['approved', 'paid']))
            <a class="btn outline" href="{{ route('admin.hr.payroll.edit', $run) }}">Edit</a>
            <form method="POST" action="{{ route('admin.hr.payroll.process', $run) }}">
                @csrf
                <button type="submit" class="btn warning">Process Payroll</button>
            </form>
        @endif
        @if ($run->status === 'processed')
            <form method="POST" action="{{ route('admin.hr.payroll.approve', $run) }}">
                @csrf
                <button type="submit" class="btn primary">Approve Payroll</button>
            </form>
        @endif
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$run->total_employees" label="Employees"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($run->gross_amount, 2)" label="Gross Amount"/>
        <x-admin.metric-card color="red" :value="'SAR '.number_format($run->total_deductions, 2)" label="Total Deductions"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($run->net_amount, 2)" label="Net Payable"/>
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
            <tr><th>Notes</th><td>{{ $run->notes ?? '-' }}</td></tr>
        </tbody>
    </x-admin.data-table>

    <x-admin.data-table title="Payroll Items" :subtitle="$run->items->count().' employees'">
        <thead>
            <tr><th>Employee</th><th>Department</th><th>Present</th><th>Leave</th><th>Basic</th><th>Allowances</th><th>Overtime</th><th>Gross</th><th>Deductions</th><th>Net</th></tr>
        </thead>
        <tbody>
            @forelse ($run->items as $item)
                <tr>
                    <td><a href="{{ route('admin.hr.employees.show', $item->employee) }}" style="color:var(--blue);font-weight:700">{{ $item->employee->name }}</a></td>
                    <td>{{ $item->employee->department?->name ?? '-' }}</td>
                    <td>{{ $item->present_days }}</td>
                    <td>{{ $item->leave_days }}</td>
                    <td>SAR {{ number_format($item->basic_salary, 2) }}</td>
                    <td>SAR {{ number_format($item->total_allowances, 2) }}</td>
                    <td>SAR {{ number_format($item->overtime_amount, 2) }}</td>
                    <td>SAR {{ number_format($item->gross_amount, 2) }}</td>
                    <td>SAR {{ number_format($item->total_deductions, 2) }}</td>
                    <td><strong>SAR {{ number_format($item->net_amount, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">No payroll items yet. Process this payroll run to generate them.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <div class="note">
        Net salary = basic salary + allowances + approved overtime - deductions. Employees without a salary structure fall back to their profile basic salary.
    </div>
@endsection
