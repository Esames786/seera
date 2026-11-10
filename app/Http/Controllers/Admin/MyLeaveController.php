<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Hr\LeaveRequestController;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Own records only: never infer identity from a supplied employee ID or code. */
class MyLeaveController extends Controller
{
    private function employee(Request $request): Employee
    {
        $matches = Employee::withoutGlobalScopes()->where('user_id', $request->user()->id)->get();
        abort_unless($matches->count() === 1, 403, __('feedback.link_employee_first'));
        $employee = $matches->first();
        abort_unless(in_array($employee->status, ['active', 'on leave'], true), 403);

        return $employee;
    }

    public function index(Request $request)
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $employee = $this->employee($request);

        return view('admin.my-leave', [
            'employee' => $employee, 'balance' => $employee->leaveBalance($request->integer('year') ?: null, true),
            'leaves' => LeaveRequest::withoutGlobalScopes()->where('employee_id', $employee->id)->with('leaveType')->latest('start_date')->paginate(15)->withQueryString(),
            'leaveTypes' => LeaveType::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $employee = $this->employee($request);
        $data = $request->validate([
            'employee_id' => ['prohibited'], 'status' => ['prohibited'], 'approved_by' => ['prohibited'], 'approved_at' => ['prohibited'], 'total_days' => ['prohibited'],
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('status', 'active')],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:9999-12-31'],
            'reason' => ['nullable', 'string', 'max:2000'], 'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);
        unset($data['attachment']);
        $countedDays = LeaveRequestController::countDays($data['start_date'], $data['end_date']);
        if ($countedDays > 9999) { // Existing total_days DECIMAL(5,1), not a new leave-entitlement policy.
            throw ValidationException::withMessages(['end_date' => __('feedback.leave_range_too_long')]);
        }
        $path = null;
        try {
            DB::transaction(function () use ($request, $employee, $data, $countedDays, &$path) {
                Employee::withoutGlobalScopes()->whereKey($employee->id)->where('user_id', $request->user()->id)
                    ->whereIn('status', ['active', 'on leave'])->lockForUpdate()->firstOrFail();
                if (LeaveRequest::withoutGlobalScopes()->where('employee_id', $employee->id)->where('leave_type_id', $data['leave_type_id'])
                    ->whereDate('start_date', $data['start_date'])->whereDate('end_date', $data['end_date'])->whereIn('status', ['pending', 'approved'])->exists()) {
                    throw ValidationException::withMessages(['start_date' => __('feedback.duplicate_leave')]);
                }
                if ($file = $request->file('attachment')) {
                    $path = $file->store(LeaveRequestController::ATTACHMENT_DIRECTORY, LeaveRequestController::ATTACHMENT_DISK);
                    if (! $path) {
                        throw new \RuntimeException('The leave attachment could not be stored.');
                    }
                }
                $leave = LeaveRequest::create($data + ['employee_id' => $employee->id, 'status' => 'pending',
                    'total_days' => $countedDays,
                    'attachment_path' => $path, 'attachment_name' => $request->file('attachment') ? mb_substr(preg_replace('/[^\pL\pN._ -]/u', '_', basename($request->file('attachment')->getClientOriginalName())), 0, 191) : null]);
                ActivityLog::record($request, 'HR', 'Requested own leave', '[Employee #'.$employee->id.'] [Leave #'.$leave->id.']');
            });
        } catch (Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return redirect()->route('admin.my-leave.index')->with('status', __('feedback.leave_requested'));
    }

    public function attachment(Request $request, int $leave)
    {
        $employee = $this->employee($request);
        $record = LeaveRequest::withoutGlobalScopes()->where('employee_id', $employee->id)->findOrFail($leave);
        abort_unless($record->attachment_path && Storage::disk('local')->exists($record->attachment_path), 404);

        return Storage::disk('local')->download($record->attachment_path, basename($record->attachment_name ?: 'attachment'), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
