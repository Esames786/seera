<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\EmployeeDocument;
use App\Models\Employee;
use App\Models\EndOfServiceRecord;
use App\Models\LeaveRequest;
use App\Models\OvertimeRecord;
use App\Models\PayrollRun;
use Illuminate\View\View;

class HrDashboardController extends Controller
{
    public function index(): View
    {
        $canViewAttendance = request()->user()->hasPermission('Attendance', 'view');
        $canViewPayroll = request()->user()->hasPermission('Payroll', 'view');
        $today = now()->toDateString();
        $expiryWindow = now()->addDays(60)->toDateString();

        $onLeaveToday = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();

        $currentPayroll = $canViewPayroll ? PayrollRun::where('payroll_month', now()->month)
            ->where('payroll_year', now()->year)
            ->latest('id')
            ->first() : null;

        return view('admin.hr.dashboard', [
            'canViewAttendance' => $canViewAttendance,
            'canViewPayroll' => $canViewPayroll,
            'totalEmployees' => Employee::count(),
            'activeEmployees' => Employee::where('status', 'active')->count(),
            'presentToday' => $canViewAttendance ? AttendanceRecord::whereDate('attendance_date', $today)->where('status', 'present')->count() : null,
            'lateToday' => $canViewAttendance ? AttendanceRecord::whereDate('attendance_date', $today)->where('status', 'late')->count() : null,
            'absentToday' => $canViewAttendance ? AttendanceRecord::whereDate('attendance_date', $today)->where('status', 'absent')->count() : null,
            'onLeaveToday' => $onLeaveToday,
            'pendingLeaves' => LeaveRequest::where('status', 'pending')->count(),
            'pendingOvertime' => $canViewPayroll ? OvertimeRecord::where('status', 'pending')->count(),
            'currentPayroll' => $currentPayroll,
            'expiringIqamas' => Employee::whereNotNull('iqama_expiry_date')
                ->whereDate('iqama_expiry_date', '<=', $expiryWindow)
                ->count(),
            'expiringDocuments' => EmployeeDocument::whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', $expiryWindow)
                ->count(),
            'pendingEosb' => $canViewPayroll ? EndOfServiceRecord::where('status', 'draft')->count() : null,
            'pendingPayrollApprovals' => $canViewPayroll ? PayrollRun::whereIn('status', ['draft', 'processed'])->count() : null,
            'expiringEmployees' => Employee::with(['department', 'designation'])
                ->whereNotNull('iqama_expiry_date')
                ->whereDate('iqama_expiry_date', '<=', $expiryWindow)
                ->orderBy('iqama_expiry_date')
                ->limit(6)
                ->get(),
            'recentLeaves' => LeaveRequest::with(['employee', 'leaveType'])
                ->latest('id')
                ->limit(6)
                ->get(),
        ]);
    }
}
