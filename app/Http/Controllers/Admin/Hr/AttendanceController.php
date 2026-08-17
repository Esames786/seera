<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Shift;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $records = AttendanceRecord::with(['employee.department', 'project', 'site', 'shift'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%"));
            })
            ->when($request->filled('department'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $request->integer('department'))))
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->integer('project')))
            ->when($request->filled('site'), fn ($q) => $q->where('site_id', $request->integer('site')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('attendance_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('attendance_date', '<=', $request->date('to')))
            ->orderByDesc('attendance_date')
            ->orderBy('employee_id')
            ->paginate(15)
            ->withQueryString();

        $today = now()->toDateString();

        return view('admin.hr.attendance.index', [
            'records' => $records,
            'presentToday' => AttendanceRecord::whereDate('attendance_date', $today)->where('status', 'present')->count(),
            'lateToday' => AttendanceRecord::whereDate('attendance_date', $today)->where('status', 'late')->count(),
            'absentToday' => AttendanceRecord::whereDate('attendance_date', $today)->where('status', 'absent')->count(),
            'outsideGeofence' => AttendanceRecord::whereDate('attendance_date', $today)->where('geofence_status', 'outside')->count(),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.hr.attendance.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $record = AttendanceRecord::create($this->validated($request));
        $record->load('employee');

        ActivityLog::record($request, 'Attendance', 'Created attendance record', $record->employee->name.' - '.$record->attendance_date->toDateString());

        return redirect()->route('admin.hr.attendance.index')
            ->with('status', 'Attendance record saved successfully.');
    }

    public function edit(AttendanceRecord $attendance_record): View
    {
        return view('admin.hr.attendance.edit', ['record' => $attendance_record] + $this->formOptions());
    }

    public function update(Request $request, AttendanceRecord $attendance_record): RedirectResponse
    {
        $attendance_record->update($this->validated($request, $attendance_record));
        $attendance_record->load('employee');

        ActivityLog::record($request, 'Attendance', 'Updated attendance record', $attendance_record->employee->name.' - '.$attendance_record->attendance_date->toDateString());

        return redirect()->route('admin.hr.attendance.index')
            ->with('status', 'Attendance record updated successfully.');
    }

    public function destroy(Request $request, AttendanceRecord $attendance_record): RedirectResponse
    {
        $label = $attendance_record->employee->name.' - '.$attendance_record->attendance_date->toDateString();
        $attendance_record->delete();

        ActivityLog::record($request, 'Attendance', 'Deleted attendance record', $label);

        return redirect()->route('admin.hr.attendance.index')
            ->with('status', 'Attendance record deleted successfully.');
    }

    private function validated(Request $request, ?AttendanceRecord $record = null): array
    {
        $employeeId = $request->input('employee_id');

        // Date columns are stored with a time component, so the duplicate check
        // compares on the date part rather than using a plain unique rule.
        $uniquePerDay = function (string $attribute, mixed $value, callable $fail) use ($employeeId, $record) {
            $exists = AttendanceRecord::where('employee_id', $employeeId)
                ->whereDate('attendance_date', $value)
                ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                ->exists();

            if ($exists) {
                $fail('This employee already has an attendance record on that date.');
            }
        };

        return $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'attendance_date' => ['required', 'date', $uniquePerDay],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'late_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'overtime_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'status' => ['required', 'in:present,late,absent,leave,half day'],
            'source' => ['required', 'in:manual,mobile,offline'],
            'geofence_status' => ['required', 'in:inside,outside,unknown'],
            'remarks' => ['nullable', 'string'],
        ]);
    }

    private function formOptions(): array
    {
        return [
            'employees' => Employee::orderBy('employee_code')->get(),
            'departments' => Department::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'shifts' => Shift::orderBy('code')->get(),
            'statuses' => AttendanceRecord::STATUSES,
            'sources' => AttendanceRecord::SOURCES,
            'geofenceStatuses' => AttendanceRecord::GEOFENCE_STATUSES,
        ];
    }
}
