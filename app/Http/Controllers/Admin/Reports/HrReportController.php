<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HrReportController extends Controller
{
    /**
     * Read-only operational summary from existing scoped HR records.
     * This is not a payroll, GOSI or WPS report.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $period = Carbon::createFromFormat('!Y-m', $filters['month'] ?? now()->format('Y-m'));
        $from = $period->copy()->startOfMonth()->toDateString();
        $to = $period->copy()->endOfMonth()->toDateString();
        $canViewAttendance = $request->user()->hasPermission('Attendance', 'view');

        // Global user_access scopes on Employee, LeaveRequest and AttendanceRecord
        // exclude employees and records outside the actor's organization scope.
        $employeeCount = Employee::count();
        $activeEmployees = Employee::where('status', 'active')->count();

        // Leave requests are counted by their START date in the selected month.
        // A request crossing months is counted only in its starting month.
        // Compare by calendar date: the date casts persist "Y-m-d 00:00:00", so a plain string
        // whereBetween would drop the last day of the month on engines that keep the time part.
        $leaves = LeaveRequest::whereDate('start_date', '>=', $from)->whereDate('start_date', '<=', $to);
        $leaveCounts = [
            'pending' => (clone $leaves)->where('status', 'pending')->count(),
            'approved' => (clone $leaves)->where('status', 'approved')->count(),
            'rejected' => (clone $leaves)->where('status', 'rejected')->count(),
        ];

        $attendanceCounts = null;
        if ($canViewAttendance) {
            // Count attendance RECORDS, not unique people or payroll days.
            $attendance = AttendanceRecord::whereDate('attendance_date', '>=', $from)->whereDate('attendance_date', '<=', $to);
            $attendanceCounts = [
                'present' => (clone $attendance)->where('status', 'present')->count(),
                'late' => (clone $attendance)->where('status', 'late')->count(),
                'absent' => (clone $attendance)->where('status', 'absent')->count(),
                'leave' => (clone $attendance)->where('status', 'leave')->count(),
                'half day' => (clone $attendance)->where('status', 'half day')->count(),
            ];
        }

        return view('admin.reports.hr.index', [
            'selectedMonth' => $period->format('Y-m'),
            'from' => $from,
            'to' => $to,
            'employeeCount' => $employeeCount,
            'activeEmployees' => $activeEmployees,
            'leaveCounts' => $leaveCounts,
            'attendanceCounts' => $attendanceCounts,
            'canViewAttendance' => $canViewAttendance,
        ]);
    }
}
