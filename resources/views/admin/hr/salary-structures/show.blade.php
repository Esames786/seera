@extends('layouts.admin')

@section('title', 'Salary Structure')
@section('breadcrumb', 'HR &amp; Payroll / Salary Structures / Details')

@section('content')
    <x-admin.page-header :title="'Salary Structure: '.$structure->employee->name" :description="'Effective from '.$structure->effective_from->toDateString().' to '.($structure->effective_to?->toDateString() ?? 'open')">
        <a class="btn primary" href="{{ route('admin.hr.salary-structures.edit', $structure) }}">Edit Structure</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($structure->basic_salary, 2)" label="Basic Salary"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($structure->totalAllowances(), 2)" label="Total Allowances"/>
        <x-admin.metric-card color="red" :value="'SAR '.number_format($structure->totalDeductions(), 2)" label="Total Deductions"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($structure->netSalary(), 2)" label="Net Salary"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Structure Components" class="detail-table">
            <tbody>
                <tr><th>Employee</th><td><a href="{{ route('admin.hr.employees.show', $structure->employee) }}" style="color:var(--blue);font-weight:700">{{ $structure->employee->name }}</a> ({{ $structure->employee->employee_code }})</td></tr>
                <tr><th>Department</th><td>{{ $structure->employee->department?->name ?? '-' }}</td></tr>
                <tr><th>Designation</th><td>{{ $structure->employee->designation?->name ?? '-' }}</td></tr>
                <tr><th>Basic Salary</th><td>SAR {{ number_format($structure->basic_salary, 2) }}</td></tr>
                <tr><th>Housing Allowance</th><td>SAR {{ number_format($structure->housing_allowance, 2) }}</td></tr>
                <tr><th>Transport Allowance</th><td>SAR {{ number_format($structure->transport_allowance, 2) }}</td></tr>
                <tr><th>Food Allowance</th><td>SAR {{ number_format($structure->food_allowance, 2) }}</td></tr>
                <tr><th>Other Allowance</th><td>SAR {{ number_format($structure->other_allowance, 2) }}</td></tr>
                <tr><th>Fixed Deduction</th><td>SAR {{ number_format($structure->fixed_deduction, 2) }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$structure->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table title="Additional Salary Items">
            <thead>
                <tr><th>Type</th><th>Name</th><th>Amount</th><th>Taxable</th></tr>
            </thead>
            <tbody>
                @forelse ($structure->items as $item)
                    <tr>
                        <td><span class="badge {{ $item->item_type === 'allowance' ? 'green' : 'red' }}">{{ ucfirst($item->item_type) }}</span></td>
                        <td>{{ $item->name }}</td>
                        <td>SAR {{ number_format($item->amount, 2) }}</td>
                        <td>{{ $item->is_taxable ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="table-empty">No additional salary items.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    </div>
@endsection
