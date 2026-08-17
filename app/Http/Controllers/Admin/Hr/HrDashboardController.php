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
        $today = now()->toDateString();
        $expiryWindow = now()->addDays(60)->toDateString();

        $onLeaveToday = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();

        $currentPayroll = PayrollRun::where('payroll_month', now()->month)
            ->where('payroll_year', now()->year)
            ->latest('id')
            ->first();

        return view('admin.hr.dashboard', [
            'totalEmployees' => Employee::count(),
            'activeEmployees' => Employee::where('status', 'active')->count(),
            'presentToday' => AttendanceRecord::whereDate('attendance_date', $today)->where('status', 'present')->count(),
            'lateToday' => AttendanceRecord::whereDate('attendance_date', $today)->where('status', 'late')->count(),
            'absentToday' => AttendanceRecord::whereDate('attendance_date', $today)->where('status', 'absent')->count(),
            'onLeaveToday' => $onLeaveToday,
            'pendingLeaves' => LeaveRequest::where('status', 'pending')->count(),
            'pendingOvertime' => OvertimeRecord::where('status', 'pending')->count(),
            'currentPayroll' => $currentPayroll,
            'expiringIqamas' => Employee::whereNotNull('iqama_expiry_date')
                ->whereDate('iqama_expiry_date', '<=', $expiryWindow)
                ->count(),
            'expiringDocuments' => EmployeeDocument::whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', $expiryWindow)
                ->count(),
            'pendingEosb' => EndOfServiceRecord::where('status', 'draft')->count(),
            'pendingPayrollApprovals' => PayrollRun::whereIn('status', ['draft', 'processed'])->count(),
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
