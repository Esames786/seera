@extends('layouts.admin')

@section('title', 'Payroll')
@section('breadcrumb', 'HR &amp; Payroll / Payroll')

@section('content')
    <x-admin.page-header title="Payroll Processing" description="Monthly payroll run creation, processing, review and approval">
        <a class="btn primary" href="{{ route('admin.hr.payroll.create') }}">+ Create Payroll Run</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$draftRuns" label="Draft Runs"/>
        <x-admin.metric-card color="blue" :value="$processedRuns" label="Processed Runs"/>
        <x-admin.metric-card color="green" :value="$approvedRuns" label="Approved / Paid Runs"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($currentNet, 2)" label="Net This Year"/>
    </div>

    <x-admin.filter-bar>
        <select class="select" style="width:150px" name="year">
            <option value="">All Years</option>
            @foreach ($years as $year)
                <option value="{{ $year }}" @selected(request('year') == $year)>{{ $year }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.hr.payroll.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Payroll Runs">
        <thead>
            <tr><th>Code</th><th>Month</th><th>Period</th><th>Branch</th><th>Project</th><th>Employees</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($runs as $run)
                <tr>
                    <td>{{ $run->code }}</td>
                    <td>{{ $run->periodLabel() }}</td>
                    <td>{{ $run->period_start->format('d M') }} - {{ $run->period_end->format('d M') }}</td>
                    <td>{{ $run->branch?->name ?? 'All' }}</td>
                    <td>{{ $run->project?->name ?? 'All' }}</td>
                    <td>{{ $run->items_count }}</td>
                    <td>SAR {{ number_format($run->gross_amount, 2) }}</td>
                    <td>SAR {{ number_format($run->total_deductions, 2) }}</td>
                    <td><strong>SAR {{ number_format($run->net_amount, 2) }}</strong></td>
                    <td><x-admin.status-badge :status="$run->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.hr.payroll.show', $run)"
                            :edit="in_array($run->status, ['approved', 'paid']) ? null : route('admin.hr.payroll.edit', $run)"
                            :delete="in_array($run->status, ['approved', 'paid']) ? null : route('admin.hr.payroll.destroy', $run)"
                            :name="$run->code">
                            @if (! in_array($run->status, ['approved', 'paid']))
                                <form method="POST" action="{{ route('admin.hr.payroll.process', $run) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Process</button>
                                </form>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">No payroll runs found.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $runs->firstItem() ?? 0 }}-{{ $runs->lastItem() ?? 0 }} of {{ $runs->total() }}</span>
            {{ $runs->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    <div class="note">
        Phase 3 payroll rule: net salary = basic salary + allowances + approved overtime - deductions. Full Saudi payroll rules come in a later phase.
    </div>
@endsection
