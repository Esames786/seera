@extends('layouts.admin')

@section('title', 'Employees')
@section('breadcrumb', 'HR & Payroll / Employees')

@section('content')
    <x-admin.page-header title="Employee Management" description="Central HR employee profiles linked to departments, designations, projects, sites and payroll">
        <a class="btn primary" href="{{ route('admin.hr.employees.create') }}">+ Add Employee</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalEmployees" label="Total Employees"/>
        <x-admin.metric-card color="green" :value="$activeEmployees" label="Active Employees"/>
        <x-admin.metric-card color="cyan" :value="$mobileEmployees" label="Mobile App Access"/>
        <x-admin.metric-card color="red" :value="$expiringIqamas" label="Expiring IQAMAs"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Code, name or IQAMA..."/>
        <select class="select" style="width:160px" name="department">
            <option value="">All Departments</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(request('department') == $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:160px" name="designation">
            <option value="">All Designations</option>
            @foreach ($designations as $designation)
                <option value="{{ $designation->id }}" @selected(request('designation') == $designation->id)>{{ $designation->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="branch">
            <option value="">All Branches</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(request('branch') == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="project">
            <option value="">All Projects</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="site">
            <option value="">All Sites</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}" @selected(request('site') == $site->id)>{{ $site->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:130px" name="status">
            <option value="">All Status</option>
            @foreach (['active', 'inactive', 'on leave', 'terminated'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <select class="select" style="width:160px" name="doc_type">
            <option value="">Any document</option>
            @foreach ($documentTypes as $type)
                <option value="{{ $type }}" @selected(request('doc_type') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        <select class="select" style="width:170px" name="doc_status">
            <option value="">Document validity</option>
            <option value="expired" @selected(request('doc_status') === 'expired')>Expired</option>
            <option value="expiring" @selected(request('doc_status') === 'expiring')>Expiring in 60 days</option>
            <option value="valid" @selected(request('doc_status') === 'valid')>Valid</option>
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.hr.employees.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Employee Listing">
        <thead>
            <tr>
                <th>Code</th><th>Name</th><th>Department</th><th>Designation</th>
                <th>Project / Site</th><th>Classification</th><th>IQAMA Expiry</th><th>Basic Salary</th>
                <th>Mobile</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $employee)
                <tr>
                    <td>{{ $employee->employee_code }}</td>
                    <td><a href="{{ route(auth()->user()->hasPermission('HR', 'edit') ? 'admin.hr.employees.edit' : 'admin.hr.employees.show', $employee) }}" style="color:var(--blue);font-weight:700">{{ $employee->name }}</a></td>
                    <td>{{ $employee->department?->name ?? '-' }}</td>
                    <td>{{ $employee->designation?->name ?? '-' }}</td>
                    <td>{{ $employee->project?->name ?? 'Head Office' }}@if($employee->site) / {{ $employee->site->name }}@endif</td>
                    <td><span class="badge blue">{{ $employee->employee_classification }}</span></td>
                    <td>
                        {{ $employee->iqama_expiry_date?->toDateString() ?? '-' }}
                        <x-admin.status-badge :status="$employee->iqamaStatus()"/>
                    </td>
                    <td>SAR {{ number_format($employee->basic_salary, 2) }}</td>
                    <td><x-admin.status-badge :status="$employee->mobile_access ? 'yes' : 'no'"/></td>
                    <td><x-admin.status-badge :status="$employee->status"/></td>
                    <td>
                        <div class="actions">
                            <a class="btn sm primary" href="{{ route(auth()->user()->hasPermission('HR', 'edit') ? 'admin.hr.employees.edit' : 'admin.hr.employees.show', $employee) }}">{{ __('Open') }}</a>
                            @if(auth()->user()->hasPermission('HR', 'delete'))
                                <button type="button" class="btn sm danger js-delete" data-delete-url="{{ route('admin.hr.employees.destroy', $employee) }}" data-delete-name="{{ $employee->name }}">{{ __('Deactivate') }}</button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">No employees found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $employees->firstItem() ?? 0 }}-{{ $employees->lastItem() ?? 0 }} of {{ $employees->total() }}</span>
            {{ $employees->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
