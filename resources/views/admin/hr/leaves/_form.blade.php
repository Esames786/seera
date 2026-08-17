@php /** @var \App\Models\LeaveRequest|null $leave */ $leave = $leave ?? null; @endphp

<form method="POST" action="{{ $leave ? route('admin.hr.leaves.update', $leave) : route('admin.hr.leaves.store') }}">
    @csrf
    @if ($leave) @method('PUT') @endif

    <x-admin.form-section title="Leave Request" columns="3">
        <div>
            <label for="employee_id">Employee *</label>
            <select id="employee_id" name="employee_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(old('employee_id', $leave?->employee_id) == $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="leave_type_id">Leave Type *</label>
            <select id="leave_type_id" name="leave_type_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($leaveTypes as $type)
                    <option value="{{ $type->id }}" @selected(old('leave_type_id', $leave?->leave_type_id) == $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $leave?->status ?? 'pending') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="start_date">Start Date *</label><input id="start_date" name="start_date" type="date" class="input" value="{{ old('start_date', $leave?->start_date?->toDateString()) }}" required/></div>
        <div><label for="end_date">End Date *</label><input id="end_date" name="end_date" type="date" class="input" value="{{ old('end_date', $leave?->end_date?->toDateString()) }}" required/></div>
        <div><label for="total_days">Total Days</label><input id="total_days" name="total_days" type="number" step="0.5" min="0" class="input" value="{{ old('total_days', $leave?->total_days) }}" placeholder="Auto from date range"/></div>
        <div class="full"><label for="reason">Reason</label><textarea id="reason" name="reason" class="textarea">{{ old('reason', $leave?->reason) }}</textarea></div>
        <div class="full"><label for="rejection_reason">Rejection Reason</label><textarea id="rejection_reason" name="rejection_reason" class="textarea">{{ old('rejection_reason', $leave?->rejection_reason) }}</textarea></div>
    </x-admin.form-section>

    <div class="help-box">
        Leave the total days blank to calculate it from the date range (inclusive of both start and end date).
    </div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.hr.leaves.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $leave ? 'Update Leave Request' : 'Save Leave Request' }}</button>
    </div>
</form>
