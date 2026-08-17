<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $leaves = LeaveRequest::with(['employee', 'leaveType', 'approver'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%"));
            })
            ->when($request->filled('leave_type'), fn ($q) => $q->where('leave_type_id', $request->integer('leave_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('start_date')
            ->paginate(10)
            ->withQueryString();

        return view('admin.hr.leaves.index', [
            'leaves' => $leaves,
            'pendingLeaves' => LeaveRequest::where('status', 'pending')->count(),
            'approvedLeaves' => LeaveRequest::where('status', 'approved')->count(),
            'rejectedLeaves' => LeaveRequest::where('status', 'rejected')->count(),
            'totalLeaveDays' => (float) LeaveRequest::where('status', 'approved')->sum('total_days'),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.hr.leaves.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $leave = LeaveRequest::create($this->validated($request));
        $leave->load('employee');

        ActivityLog::record($request, 'HR', 'Created leave request', $leave->employee->name);

        return redirect()->route('admin.hr.leaves.index')
            ->with('status', 'Leave request created successfully.');
    }

    public function show(LeaveRequest $leave_request): View
    {
        $leave_request->load(['employee.department', 'employee.designation', 'leaveType', 'approver']);

        return view('admin.hr.leaves.show', ['leave' => $leave_request]);
    }

    public function edit(LeaveRequest $leave_request): View
    {
        return view('admin.hr.leaves.edit', ['leave' => $leave_request] + $this->formOptions());
    }

    public function update(Request $request, LeaveRequest $leave_request): RedirectResponse
    {
        $leave_request->update($this->validated($request));
        $leave_request->load('employee');

        ActivityLog::record($request, 'HR', 'Updated leave request', $leave_request->employee->name);

        return redirect()->route('admin.hr.leaves.index')
            ->with('status', 'Leave request updated successfully.');
    }

    public function destroy(Request $request, LeaveRequest $leave_request): RedirectResponse
    {
        $label = $leave_request->employee->name;
        $leave_request->delete();

        ActivityLog::record($request, 'HR', 'Deleted leave request', $label);

        return redirect()->route('admin.hr.leaves.index')
            ->with('status', 'Leave request deleted successfully.');
    }

    public function approve(Request $request, LeaveRequest $leave_request): RedirectResponse
    {
        $leave_request->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        ActivityLog::record($request, 'HR', 'Approved leave request', $leave_request->employee->name);

        return back()->with('status', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leave_request): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $leave_request->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        ActivityLog::record($request, 'HR', 'Rejected leave request', $leave_request->employee->name);

        return back()->with('status', 'Leave request rejected.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_days' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,approved,rejected,cancelled'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        // Inclusive day count so a one-day leave counts as 1.
        $data['total_days'] = $data['total_days']
            ?? Carbon::parse($data['start_date'])->startOfDay()->diffInDays(Carbon::parse($data['end_date'])->startOfDay()) + 1;

        return $data;
    }

    private function formOptions(): array
    {
        return [
            'employees' => Employee::orderBy('employee_code')->get(),
            'leaveTypes' => LeaveType::orderBy('name')->get(),
            'statuses' => LeaveRequest::STATUSES,
        ];
    }
}
