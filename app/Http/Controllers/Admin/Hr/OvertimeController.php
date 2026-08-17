<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OvertimeRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OvertimeController extends Controller
{
    public function index(Request $request): View
    {
        $records = OvertimeRecord::with(['employee', 'approver'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('overtime_date')
            ->paginate(10)
            ->withQueryString();

        return view('admin.hr.overtime.index', [
            'records' => $records,
            'pendingOvertime' => OvertimeRecord::where('status', 'pending')->count(),
            'approvedOvertime' => OvertimeRecord::where('status', 'approved')->count(),
            'totalHours' => (float) OvertimeRecord::where('status', 'approved')->sum('hours'),
            'totalAmount' => (float) OvertimeRecord::where('status', 'approved')->sum('amount'),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.hr.overtime.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $record = OvertimeRecord::create($this->validated($request));
        $record->load('employee');

        ActivityLog::record($request, 'HR', 'Created overtime record', $record->employee->name);

        return redirect()->route('admin.hr.overtime.index')
            ->with('status', 'Overtime record saved successfully.');
    }

    public function edit(OvertimeRecord $overtime_record): View
    {
        return view('admin.hr.overtime.edit', ['record' => $overtime_record] + $this->formOptions());
    }

    public function update(Request $request, OvertimeRecord $overtime_record): RedirectResponse
    {
        $overtime_record->update($this->validated($request));
        $overtime_record->load('employee');

        ActivityLog::record($request, 'HR', 'Updated overtime record', $overtime_record->employee->name);

        return redirect()->route('admin.hr.overtime.index')
            ->with('status', 'Overtime record updated successfully.');
    }

    public function destroy(Request $request, OvertimeRecord $overtime_record): RedirectResponse
    {
        $label = $overtime_record->employee->name;
        $overtime_record->delete();

        ActivityLog::record($request, 'HR', 'Deleted overtime record', $label);

        return redirect()->route('admin.hr.overtime.index')
            ->with('status', 'Overtime record deleted successfully.');
    }

    public function approve(Request $request, OvertimeRecord $overtime_record): RedirectResponse
    {
        $overtime_record->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        ActivityLog::record($request, 'HR', 'Approved overtime record', $overtime_record->employee->name);

        return back()->with('status', 'Overtime approved.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'attendance_record_id' => ['nullable', 'exists:attendance_records,id'],
            'overtime_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'rate' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $data['amount'] = round((float) $data['hours'] * (float) $data['rate'], 2);

        return $data;
    }

    private function formOptions(): array
    {
        return [
            'employees' => Employee::orderBy('employee_code')->get(),
            'attendanceRecords' => AttendanceRecord::with('employee')
                ->orderByDesc('attendance_date')
                ->limit(100)
                ->get(),
            'statuses' => OvertimeRecord::STATUSES,
        ];
    }
}
