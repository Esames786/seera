@extends('layouts.admin')

@section('title', 'Shifts')
@section('breadcrumb', 'HR &amp; Payroll / Shifts')

@section('content')
    <x-admin.page-header title="Shift Management" description="Define shift timing, break, grace minutes and the overtime threshold">
        <a class="btn primary" href="{{ route('admin.hr.shifts.create') }}">+ Add Shift</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalShifts" label="Total Shifts"/>
        <x-admin.metric-card color="green" :value="$activeShifts" label="Active Shifts"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Shift name or code..."/>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            @foreach (['active', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn primary" href="{{ route('admin.hr.shifts.create') }}">+ Add Shift</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Shifts Listing">
        <thead>
            <tr><th>Code</th><th>Name</th><th>Start</th><th>End</th><th>Break</th><th>Grace</th><th>Overtime After</th><th>Assignments</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($shifts as $shift)
                <tr>
                    <td>{{ $shift->code }}</td>
                    <td>{{ $shift->name }}</td>
                    <td>{{ $shift->start_time }}</td>
                    <td>{{ $shift->end_time }}</td>
                    <td>{{ $shift->break_minutes }} min</td>
                    <td>{{ $shift->grace_minutes }} min</td>
                    <td>{{ $shift->overtime_after_minutes }} min</td>
                    <td>{{ $shift->assignments_count }}</td>
                    <td><x-admin.status-badge :status="$shift->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :edit="route('admin.hr.shifts.edit', $shift)"
                            :delete="route('admin.hr.shifts.destroy', $shift)"
                            :name="$shift->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">No shifts found.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $shifts->firstItem() ?? 0 }}-{{ $shifts->lastItem() ?? 0 }} of {{ $shifts->total() }}</span>
            {{ $shifts->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
