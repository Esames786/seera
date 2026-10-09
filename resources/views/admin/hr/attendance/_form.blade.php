@php /** @var \App\Models\AttendanceRecord|null $record */ $record = $record ?? null; @endphp

<form method="POST" action="{{ $record ? route('admin.hr.attendance.update', $record) : route('admin.hr.attendance.store') }}">
    @csrf
    @if ($record) @method('PUT') @endif

    <x-admin.form-section title="Attendance Entry" columns="3">
        <div>
            <label for="employee_id">Employee *</label>
            <select id="employee_id" name="employee_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(old('employee_id', $record?->employee_id ?? ($prefillEmployee ?? null)?->id) == $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="attendance_date">Date *</label><input id="attendance_date" name="attendance_date" type="date" class="input" value="{{ old('attendance_date', $record?->attendance_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div>
            <label for="shift_id">Shift</label>
            <select id="shift_id" name="shift_id" class="select">
                <option value="">Select...</option>
                @foreach ($shifts as $shift)
                    <option value="{{ $shift->id }}" @selected(old('shift_id', $record?->shift_id) == $shift->id)>{{ $shift->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="project_id">Project</label>
            <select id="project_id" name="project_id" class="select">
                <option value="">Head Office</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $record?->project_id) == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="site_id">Site</label>
            <select id="site_id" name="site_id" class="select">
                <option value="">Select...</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" data-parent="{{ $site->project_id }}" @selected(old('site_id', $record?->site_id) == $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="check_in">Check In</label><input id="check_in" name="check_in" type="time" class="input" value="{{ old('check_in', $record ? substr((string) $record->check_in, 0, 5) : '') }}"/></div>
        <div><label for="check_out">Check Out</label><input id="check_out" name="check_out" type="time" class="input" value="{{ old('check_out', $record ? substr((string) $record->check_out, 0, 5) : '') }}"/></div>
        <div><label for="late_minutes">Late (minutes) *</label><input id="late_minutes" name="late_minutes" type="number" min="0" class="input" value="{{ old('late_minutes', $record?->late_minutes ?? 0) }}" required/></div>
        <div><label for="overtime_minutes">Overtime (minutes) *</label><input id="overtime_minutes" name="overtime_minutes" type="number" min="0" class="input" value="{{ old('overtime_minutes', $record?->overtime_minutes ?? 0) }}" required/></div>
    </x-admin.form-section>

    <x-admin.form-section title="Source & Geo-Fence" columns="3">
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $record?->status ?? 'present') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="source">Source *</label>
            <select id="source" name="source" class="select" required @disabled($record?->isGpsRecord())>
                @foreach ($sources as $source)
                    <option value="{{ $source }}" @selected(old('source', $record?->source ?? 'manual') === $source)>{{ ucfirst($source) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="geofence_status">Geo-Fence Status *</label>
            <select id="geofence_status" name="geofence_status" class="select" required @disabled($record?->isGpsRecord())>
                @foreach ($geofenceStatuses as $status)
                    <option value="{{ $status }}" @selected(old('geofence_status', $record?->geofence_status ?? 'inside') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="remarks">Remarks</label><textarea id="remarks" name="remarks" class="textarea">{{ old('remarks', $record?->remarks) }}</textarea></div>
    </x-admin.form-section>

    @if($record?->isGpsRecord())
        <x-admin.form-section :title="__('mobile_attendance.location_evidence')" columns="2">
            <div><strong>{{ __('mobile_attendance.check_in_evidence') }}</strong><br/>{{ $record->check_in_latitude }}, {{ $record->check_in_longitude }} · {{ __('mobile_attendance.accuracy') }} {{ $record->check_in_accuracy_meters !== null ? number_format($record->check_in_accuracy_meters).' m' : '-' }} · {{ $record->check_in_distance_meters !== null ? number_format($record->check_in_distance_meters).' m' : '-' }} · {{ \App\Models\AttendanceRecord::geofenceLabel($record->check_in_geofence_status) }} · {{ __('mobile_attendance.recorded') }} {{ $record->check_in_recorded_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
            <div><strong>{{ __('mobile_attendance.check_out_evidence') }}</strong><br/>@if($record->check_out_recorded_at){{ $record->check_out_latitude }}, {{ $record->check_out_longitude }} · {{ __('mobile_attendance.accuracy') }} {{ $record->check_out_accuracy_meters !== null ? number_format($record->check_out_accuracy_meters).' m' : '-' }} · {{ $record->check_out_distance_meters !== null ? number_format($record->check_out_distance_meters).' m' : '-' }} · {{ \App\Models\AttendanceRecord::geofenceLabel($record->check_out_geofence_status) }} · {{ __('mobile_attendance.recorded') }} {{ $record->check_out_recorded_at->format('Y-m-d H:i:s') }}@else - @endif</div>
            <div class="full help-box">{{ __('mobile_attendance.gps_protected') }}</div>
        </x-admin.form-section>
    @else
        <div class="help-box">
            Manual entries record what you type here and are marked "Manual". Location-validated records are created only by employees through Mobile Attendance (source "GPS / Mobile"); offline attendance is not yet operational.
        </div>
    @endif

    <x-admin.form-actions :cancel="route('admin.hr.attendance.index')"/>
</form>

<x-admin.dependent-select parent="project_id" child="site_id" placeholder="sites"/>
