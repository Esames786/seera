@php /** @var \App\Models\OvertimeRecord|null $record */ $record = $record ?? null; @endphp

<form method="POST" action="{{ $record ? route('admin.hr.overtime.update', $record) : route('admin.hr.overtime.store') }}">
    @csrf
    @if ($record) @method('PUT') @endif

    <x-admin.form-section title="Overtime Claim" columns="3">
        <div>
            <label for="employee_id">Employee *</label>
            <select id="employee_id" name="employee_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(old('employee_id', $record?->employee_id) == $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="overtime_date">Date *</label><input id="overtime_date" name="overtime_date" type="date" class="input" value="{{ old('overtime_date', $record?->overtime_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div>
            <label for="attendance_record_id">Attendance Record</label>
            <select id="attendance_record_id" name="attendance_record_id" class="select">
                <option value="">Not linked</option>
                @foreach ($attendanceRecords as $attendance)
                    <option value="{{ $attendance->id }}" @selected(old('attendance_record_id', $record?->attendance_record_id) == $attendance->id)>{{ $attendance->attendance_date->toDateString() }} - {{ $attendance->employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="hours">Hours *</label><input id="hours" name="hours" type="number" step="0.25" min="0" max="24" class="input" value="{{ old('hours', $record?->hours ?? 0) }}" required/></div>
        <div><label for="rate">Hourly Rate (SAR) *</label><input id="rate" name="rate" type="number" step="0.01" min="0" class="input" value="{{ old('rate', $record?->rate ?? 0) }}" required/></div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $record?->status ?? 'pending') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="reason">Reason</label><textarea id="reason" name="reason" class="textarea">{{ old('reason', $record?->reason) }}</textarea></div>
    </x-admin.form-section>

    <div class="help-box">Amount is calculated automatically as hours × hourly rate.</div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.hr.overtime.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $record ? 'Update Overtime' : 'Save Overtime' }}</button>
    </div>
</form>
