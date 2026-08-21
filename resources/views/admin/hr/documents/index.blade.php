@extends('layouts.admin')

@section('title', 'Employee Documents')
@section('breadcrumb', 'HR &amp; Payroll / Documents / IQAMA')

@section('content')
    <x-admin.page-header title="Employee Documents / IQAMA" description="Read-only register of every employee document on the system. Documents are attached from the employee form.">    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalDocuments" label="Total Documents"/>
        <x-admin.metric-card color="green" :value="$validDocuments" label="Valid"/>
        <x-admin.metric-card color="yellow" :value="$expiringDocuments" label="Expiring Soon (60 days)"/>
        <x-admin.metric-card color="red" :value="$expiredDocuments" label="Expired"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:230px" type="search" name="search" value="{{ request('search') }}" placeholder="Employee or document number..."/>
        <select class="select" style="width:200px" name="employee">
            <option value="">All Employees</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" @selected(request('employee') == $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:180px" name="type">
            <option value="">All Types</option>
            @foreach ($documentTypes as $type)
                <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        <select class="select" style="width:160px" name="validity">
            <option value="">All Validity</option>
            <option value="valid" @selected(request('validity') === 'valid')>Valid</option>
            <option value="expiring" @selected(request('validity') === 'expiring')>Expiring Soon</option>
            <option value="expired" @selected(request('validity') === 'expired')>Expired</option>
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.hr.documents.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Documents Listing">
        <thead>
            <tr><th>Employee</th><th>Type</th><th>Number</th><th>Issue</th><th>Expiry</th><th>Validity</th><th>File</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($documents as $document)
                <tr>
                    <td><a href="{{ route('admin.hr.employees.show', $document->employee) }}" style="color:var(--blue);font-weight:700">{{ $document->employee->name }}</a></td>
                    <td>{{ $document->document_type }}</td>
                    <td>{{ $document->document_number ?? '-' }}</td>
                    <td>{{ $document->issue_date?->toDateString() ?? '-' }}</td>
                    <td>{{ $document->expiry_date?->toDateString() ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$document->validityStatus()"/></td>
                    <td>
                        @if ($document->file_path)
                            <a href="{{ route('admin.hr.documents.download', $document) }}" style="color:var(--blue);font-weight:700">Download</a>
                        @else
                            <span class="small">Not uploaded</span>
                        @endif
                    </td>
                    <td><x-admin.status-badge :status="$document->status"/></td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No documents found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $documents->firstItem() ?? 0 }}-{{ $documents->lastItem() ?? 0 }} of {{ $documents->total() }}</span>
            {{ $documents->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
