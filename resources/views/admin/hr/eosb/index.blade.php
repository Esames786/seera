@extends('layouts.admin')

@section('title', 'End of Service')
@section('breadcrumb', 'HR &amp; Payroll / End of Service Benefits')

@section('content')
    <x-admin.page-header title="End of Service Benefits" description="Saudi EOSB calculations with immutable approved settlements">
        <a class="btn primary" href="{{ route('admin.hr.eosb.create') }}">+ Add EOSB Record</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$draftRecords" label="Draft Records"/>
        <x-admin.metric-card color="green" :value="$approvedRecords" label="Approved Records"/>
        <x-admin.metric-card color="blue" :value="$paidRecords" label="Paid Records"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($totalFinalAmount, 2)" label="Total Final Amount"/>
    </div>

    <div class="note">
        Gratuity follows the configured Saudi rules: half a month's wage for each of the first five years, a full month's wage for each year after that, on the final wage. Resignation scales the award by length of service; termination, contract completion and Article 87 exceptions pay in full. HR can document a manual override while the record is draft.
    </div>
    <br/>

    <x-admin.filter-bar>
        <input class="input" style="width:230px" type="search" name="search" value="{{ request('search') }}" placeholder="Employee code or name..."/>
        <select class="select" style="width:150px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.hr.eosb.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="End of Service Records">
        <thead>
            <tr><th>Employee</th><th>Termination Date</th><th>Reason</th><th>Service Years</th><th>Last Basic</th><th>EOSB</th><th>Leave Salary</th><th>Other Dues</th><th>Deductions</th><th>Final Amount</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td><a href="{{ route('admin.hr.employees.show', $record->employee) }}" style="color:var(--blue);font-weight:700">{{ $record->employee->name }}</a></td>
                    <td>{{ $record->termination_date->toDateString() }}</td>
                    <td>{{ $record->reasonLabel() }}</td>
                    <td>{{ $record->service_years }}</td>
                    <td>SAR {{ number_format($record->last_basic_salary, 2) }}</td>
                    <td>SAR {{ number_format($record->eosb_amount, 2) }}</td>
                    <td>SAR {{ number_format($record->leave_salary, 2) }}</td>
                    <td>SAR {{ number_format($record->other_dues, 2) }}</td>
                    <td>SAR {{ number_format($record->deductions, 2) }}</td>
                    <td><strong>SAR {{ number_format($record->final_amount, 2) }}</strong></td>
                    <td><x-admin.status-badge :status="$record->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.hr.eosb.show', $record)"
                            :edit="$record->isEditable() ? route('admin.hr.eosb.edit', $record) : null"
                            :delete="$record->isEditable() ? route('admin.hr.eosb.destroy', $record) : null"
                            :name="$record->employee->name.' EOSB'">
                            @if ($record->status === 'draft')
                                <form method="POST" action="{{ route('admin.hr.eosb.approve', $record) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Approve</button>
                                </form>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="12" class="table-empty">No end of service records found.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $records->firstItem() ?? 0 }}-{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</span>
            {{ $records->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
