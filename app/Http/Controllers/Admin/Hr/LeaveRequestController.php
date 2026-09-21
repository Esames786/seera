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
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LeaveRequestController extends Controller
{
    public const ATTACHMENT_DISK = 'local';

    public const ATTACHMENT_DIRECTORY = 'leave-attachments';

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
        $data = $this->validated($request);
        $storedPath = null;

        try {
            $leave = LeaveRequest::create($data + $this->storeAttachment($request, $storedPath));
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk(self::ATTACHMENT_DISK)->delete($storedPath);
            }
            throw $exception;
        }

        $leave->load('employee');

        ActivityLog::record($request, 'HR', 'Created leave request', $leave->employee->name);

        return redirect()->route('admin.hr.leaves.index')
            ->with('status', 'Leave request created successfully.');
    }

    public function show(LeaveRequest $leave_request): View
    {
        $leave_request->load(['employee.department', 'employee.designation', 'leaveType', 'approver']);

        return view('admin.hr.leaves.show', [
            'leave' => $leave_request,
            'balance' => $leave_request->employee->leaveBalance((int) $leave_request->start_date->year),
        ]);
    }

    public function edit(LeaveRequest $leave_request): View
    {
        return view('admin.hr.leaves.edit', ['leave' => $leave_request] + $this->formOptions());
    }

    public function update(Request $request, LeaveRequest $leave_request): RedirectResponse
    {
        $data = $this->validated($request);
        $storedPath = null;
        $previous = $leave_request->attachment_path;

        try {
            $attachment = $this->storeAttachment($request, $storedPath);
            $leave_request->update($data + $attachment);
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk(self::ATTACHMENT_DISK)->delete($storedPath);
            }
            throw $exception;
        }

        if ($storedPath && $previous && $previous !== $storedPath) {
            Storage::disk(self::ATTACHMENT_DISK)->delete($previous);
        }

        $leave_request->load('employee');

        ActivityLog::record($request, 'HR', 'Updated leave request', $leave_request->employee->name);

        return redirect()->route('admin.hr.leaves.index')
            ->with('status', 'Leave request updated successfully.');
    }

    public function destroy(Request $request, LeaveRequest $leave_request): RedirectResponse
    {
        $label = $leave_request->employee->name;
        $path = $leave_request->attachment_path;
        $leave_request->delete();

        if ($path) {
            Storage::disk(self::ATTACHMENT_DISK)->delete($path);
        }

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

    /** Supporting document for a leave (doctor's note, tickets) — client change request NR-17. */
    public function attachment(LeaveRequest $leave_request): StreamedResponse
    {
        abort_unless($leave_request->attachment_path && Storage::disk(self::ATTACHMENT_DISK)->exists($leave_request->attachment_path), 404);

        return Storage::disk(self::ATTACHMENT_DISK)->download($leave_request->attachment_path, $leave_request->attachment_name ?: 'leave-attachment');
    }

    /**
     * @return array<string, string> Attachment columns to merge into the request data (empty when no file).
     */
    private function storeAttachment(Request $request, ?string &$storedPath): array
    {
        if (! $request->hasFile('attachment')) {
            return [];
        }

        $file = $request->file('attachment');
        $storedPath = $file->store(self::ATTACHMENT_DIRECTORY, self::ATTACHMENT_DISK);

        if (! $storedPath) {
            throw new RuntimeException('The leave attachment could not be stored.');
        }

        return ['attachment_path' => $storedPath, 'attachment_name' => $file->getClientOriginalName()];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_days' => ['nullable', 'numeric', 'min:0'],
            'total_days_override' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'status' => ['required', 'in:pending,approved,rejected,cancelled'],
            'rejection_reason' => ['nullable', 'string'],
        ], [
            'attachment.mimes' => 'Attach a PDF or an image (JPG, PNG, WEBP).',
            'attachment.max' => 'The attachment may be at most 5 MB.',
        ]);

        unset($data['attachment']);

        // The dates decide the number of days (client feedback FR-05). The form
        // shows the same count live, and recalculating here means a total left
        // over from earlier dates can never be saved. A half day or another
        // agreed exception needs the override box, which is validated separately.
        $counted = self::countDays($data['start_date'], $data['end_date']);
        $override = (bool) ($data['total_days_override'] ?? false);

        $data['total_days'] = $override && filled($data['total_days'] ?? null)
            ? (float) $data['total_days']
            : $counted;

        if ($override && $data['total_days'] > $counted) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'total_days' => 'The chosen dates cover '.$counted.' '.\Illuminate\Support\Str::plural('day', $counted).'; the total cannot be more than that.',
            ]);
        }

        unset($data['total_days_override']);

        return $data;
    }

    /** Inclusive calendar-day count, so a one-day leave counts as 1. */
    public static function countDays(string $start, string $end): int
    {
        return (int) Carbon::parse($start)->startOfDay()->diffInDays(Carbon::parse($end)->startOfDay()) + 1;
    }

    private function formOptions(): array
    {
        return [
            'employees' => Employee::orderBy('employee_code')->get(),
            'leaveTypes' => LeaveType::where('status', 'active')->orderBy('name')->get(),
            'statuses' => LeaveRequest::STATUSES,
        ];
    }
}
