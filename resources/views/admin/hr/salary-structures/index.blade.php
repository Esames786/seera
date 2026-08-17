@extends('layouts.admin')

@section('title', 'Salary Structures')
@section('breadcrumb', 'HR &amp; Payroll / Salary Structures')

@section('content')
    <x-admin.page-header title="Salary Structures" description="Basic salary, allowances, deductions and additional salary items per employee">
        <a class="btn primary" href="{{ route('admin.hr.salary-structures.create') }}">+ Add Salary Structure</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalStructures" label="Total Structures"/>
        <x-admin.metric-card color="green" :value="$activeStructures" label="Active Structures"/>
        <x-admin.metric-card color="yellow" :value="$employeesWithoutStructure" label="Employees Without Structure"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($monthlyBasic, 2)" label="Monthly Basic Total"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:230px" type="search" name="search" value="{{ request('search') }}" placeholder="Employee code or name..."/>
        <select class="select" style="width:150px" name="status">
            <option value="">All Status</option>
            @foreach (['active', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.hr.salary-structures.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Salary Structures Listing">
        <thead>
            <tr><th>Employee</th><th>Basic</th><th>Housing</th><th>Transport</th><th>Food</th><th>Other</th><th>Deduction</th><th>Net</th><th>Effective From</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($structures as $structure)
                <tr>
                    <td><a href="{{ route('admin.hr.employees.show', $structure->employee) }}" style="color:var(--blue);font-weight:700">{{ $structure->employee->name }}</a></td>
                    <td>SAR {{ number_format($structure->basic_salary, 2) }}</td>
                    <td>SAR {{ number_format($structure->housing_allowance, 2) }}</td>
                    <td>SAR {{ number_format($structure->transport_allowance, 2) }}</td>
                    <td>SAR {{ number_format($structure->food_allowance, 2) }}</td>
                    <td>SAR {{ number_format($structure->other_allowance, 2) }}</td>
                    <td>SAR {{ number_format($structure->totalDeductions(), 2) }}</td>
                    <td><strong>SAR {{ number_format($structure->netSalary(), 2) }}</strong></td>
                    <td>{{ $structure->effective_from->toDateString() }}</td>
                    <td><x-admin.status-badge :status="$structure->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.hr.salary-structures.show', $structure)"
                            :edit="route('admin.hr.salary-structures.edit', $structure)"
                            :delete="route('admin.hr.salary-structures.destroy', $structure)"
                            :name="$structure->employee->name.' salary structure'"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">No salary structures found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $structures->firstItem() ?? 0 }}-{{ $structures->lastItem() ?? 0 }} of {{ $structures->total() }}</span>
            {{ $structures->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
