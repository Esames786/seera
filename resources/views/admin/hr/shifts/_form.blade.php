@php /** @var \App\Models\Shift|null $shift */ $shift = $shift ?? null; @endphp

<form method="POST" action="{{ $shift ? route('admin.hr.shifts.update', $shift) : route('admin.hr.shifts.store') }}">
    @csrf
    @if ($shift) @method('PUT') @endif

    <x-admin.form-section title="Shift Information" columns="3">
        <div><label for="name">Shift Name *</label><input id="name" name="name" class="input" value="{{ old('name', $shift?->name) }}" placeholder="Day Shift" required/></div>
        <div><label for="code">Shift Code *</label><input id="code" name="code" class="input" value="{{ old('code', $shift?->code) }}" placeholder="DAY" required/></div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['active', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $shift?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="start_time">Start Time *</label><input id="start_time" name="start_time" type="time" class="input" value="{{ old('start_time', $shift ? substr($shift->start_time, 0, 5) : '08:00') }}" required/></div>
        <div><label for="end_time">End Time *</label><input id="end_time" name="end_time" type="time" class="input" value="{{ old('end_time', $shift ? substr($shift->end_time, 0, 5) : '17:00') }}" required/></div>
        <div><label for="break_minutes">Break (minutes) *</label><input id="break_minutes" name="break_minutes" type="number" min="0" class="input" value="{{ old('break_minutes', $shift?->break_minutes ?? 60) }}" required/></div>
        <div><label for="grace_minutes">Grace (minutes) *</label><input id="grace_minutes" name="grace_minutes" type="number" min="0" class="input" value="{{ old('grace_minutes', $shift?->grace_minutes ?? 10) }}" required/></div>
        <div><label for="overtime_after_minutes">Overtime After (minutes) *</label><input id="overtime_after_minutes" name="overtime_after_minutes" type="number" min="0" class="input" value="{{ old('overtime_after_minutes', $shift?->overtime_after_minutes ?? 540) }}" required/></div>
    </x-admin.form-section>

    <div class="help-box">
        Grace minutes decide when a check-in is marked late. Overtime after minutes is the worked duration beyond which overtime starts accruing.
    </div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.hr.shifts.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $shift ? 'Update Shift' : 'Save Shift' }}</button>
    </div>
</form>
