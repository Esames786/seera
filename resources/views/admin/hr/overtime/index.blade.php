@extends('layouts.admin')

@section('title', 'Overtime')
@section('breadcrumb', 'HR &amp; Payroll / Overtime')

@section('content')
    <x-admin.page-header title="Overtime Management" description="Overtime claims linked to attendance records and the approval workflow">
        <a class="btn primary" href="{{ route('admin.hr.overtime.create') }}">+ Add Overtime</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$pendingOvertime" label="Pending Claims"/>
        <x-admin.metric-card color="green" :value="$approvedOvertime" label="Approved Claims"/>
        <x-admin.metric-card color="blue" :value="number_format($totalHours, 2)" label="Approved Hours"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($totalAmount, 2)" label="Approved Amount"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:230px" type="search" name="search" value="{{ request('search') }}" placeholder="Employee code or name..."/>
        <select class="select" style="width:150px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.hr.overtime.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Overtime Records">
        <thead>
            <tr><th>Employee</th><th>Date</th><th>Hours</th><th>Rate</th><th>Amount</th><th>Reason</th><th>Approved By</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td><a href="{{ route('admin.hr.employees.show', $record->employee) }}" style="color:var(--blue);font-weight:700">{{ $record->employee->name }}</a></td>
                    <td>{{ $record->overtime_date->toDateString() }}</td>
                    <td>{{ $record->hours }}</td>
                    <td>SAR {{ number_format($record->rate, 2) }}</td>
                    <td>SAR {{ number_format($record->amount, 2) }}</td>
                    <td>{{ $record->reason ? Str::limit($record->reason, 30) : '-' }}</td>
                    <td>{{ $record->approver?->name ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$record->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :edit="route('admin.hr.overtime.edit', $record)"
                            :delete="route('admin.hr.overtime.destroy', $record)"
                            :name="$record->employee->name.' overtime'">
                            @if ($record->status === 'pending')
                                <form method="POST" action="{{ route('admin.hr.overtime.approve', $record) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Approve</button>
                                </form>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No overtime records found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $records->firstItem() ?? 0 }}-{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</span>
            {{ $records->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    <div class="note">
        Approved overtime inside a payroll period is added to the employee's net salary when the payroll run is processed.
    </div>
@endsection
