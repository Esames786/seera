<?php

namespace App\Support\Workspace;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Read-only HR context of one employee for the Employee View page (Wave 2
 * Batch D). Every section is derived from the employee in the URL through the
 * scoped HR relations; each section is present only when the viewer holds that
 * module's permission, so an HR viewer never sees payroll money without the
 * Payroll right. Nothing is written and nothing is recalculated: attendance,
 * leave balance, overtime amounts and payroll rows come from the stored
 * records and the existing calculators.
 */
final class EmployeeHrContext
{
    public const PER_PAGE = 10;

    /** @return array<string, mixed> */
    public static function data(Employee $employee, User $user, Request $request): array
    {
        $employee->loadMissing(['department', 'designation', 'branch', 'project', 'site', 'manager', 'documents', 'salaryStructures.items']);
        $selfUrl = route('admin.hr.employees.show', $employee, false);
        $page = fn (string $key) => $request->integer('page_'.$key) ?: null;

        $flags = [
            'canEditEmployee' => $user->hasPermission('HR', 'edit'),
            'canViewAttendance' => $user->hasPermission('Attendance', 'view'),
            'canCreateAttendance' => $user->hasPermission('Attendance', 'create'),
            'canEditAttendance' => $user->hasPermission('Attendance', 'edit'),
            'canViewLeaves' => $user->hasPermission('HR', 'view'),
            'canCreateLeave' => $user->hasPermission('HR', 'create'),
            'canEditLeave' => $user->hasPermission('HR', 'edit'),
            'canViewOvertime' => $user->hasPermission('Payroll', 'view'),
            'canCreateOvertime' => $user->hasPermission('Payroll', 'create'),
            'canEditOvertime' => $user->hasPermission('Payroll', 'edit'),
            'canViewPayroll' => $user->hasPermission('Payroll', 'view'),
            'canCreateStructure' => $user->hasPermission('Payroll', 'create'),
        ];

        $sections = ['overview' => __('workspace.hr_overview'), 'documents' => __('workspace.hr_documents')];
        if ($flags['canViewAttendance']) {
            $sections['attendance'] = __('workspace.hr_attendance');
        }
        $sections['leaves'] = __('workspace.hr_leaves');
        if ($flags['canViewOvertime']) {
            $sections['overtime'] = __('workspace.hr_overtime');
        }
        if ($flags['canViewPayroll']) {
            $sections['salary'] = __('workspace.hr_salary');
            $sections['payroll'] = __('workspace.hr_payroll');
        }
        $activity = DocumentActivity::latest($user, [$employee->name, $employee->employee_code], ['HR', 'Attendance', 'Payroll', 'Users']);
        if ($activity !== null) {
            $sections['activity'] = __('workspace.activity');
        }

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return $flags + [
            'employee' => $employee,
            'selfUrl' => $selfUrl,
            'sections' => $sections,
            'contract' => self::contract($employee),
            'attendance' => $flags['canViewAttendance']
                ? $employee->attendanceRecords()->with(['shift', 'project', 'site'])->latest('attendance_date')->latest('id')
                    ->paginate(self::PER_PAGE, ['*'], 'page_attendance', $page('attendance'))->fragment('attendance')
                : null,
            'presentDays' => $flags['canViewAttendance']
                ? $employee->attendanceRecords()->whereIn('status', ['present', 'late'])->whereBetween('attendance_date', [$monthStart, $monthEnd])->count()
                : null,
            'workedDays' => $flags['canViewAttendance']
                ? $employee->attendanceRecords()->whereBetween('attendance_date', [$monthStart, $monthEnd])->count()
                : null,
            'leaves' => $employee->leaveRequests()->with(['leaveType', 'approver'])->latest('start_date')->latest('id')
                ->paginate(self::PER_PAGE, ['*'], 'page_leaves', $page('leaves'))->fragment('leaves'),
            'leaveBalance' => $employee->leaveBalance(),
            'pendingLeaves' => $employee->leaveRequests()->where('status', 'pending')->count(),
            'overtime' => $flags['canViewOvertime']
                ? $employee->overtimeRecords()->with(['attendanceRecord', 'approver'])->latest('overtime_date')->latest('id')
                    ->paginate(self::PER_PAGE, ['*'], 'page_overtime', $page('overtime'))->fragment('overtime')
                : null,
            'pendingOvertime' => $flags['canViewOvertime'] ? $employee->overtimeRecords()->where('status', 'pending')->count() : null,
            'approvedOvertimeHours' => $flags['canViewOvertime'] ? round((float) $employee->overtimeRecords()->where('status', 'approved')->sum('hours'), 2) : null,
            'payrollItems' => $flags['canViewPayroll']
                ? $employee->payrollItems()->with('payrollRun')->latest('id')
                    ->paginate(self::PER_PAGE, ['*'], 'page_payroll', $page('payroll'))->fragment('payroll')
                : null,
            'activity' => $activity,
            'links' => self::links($employee, $flags, $selfUrl),
        ];
    }

    /**
     * Contract / IQAMA summary from fields and documents that already exist.
     *
     * @return array<string, mixed>
     */
    public static function contract(Employee $employee): array
    {
        $documents = $employee->documents;
        $byStatus = $documents->groupBy(fn ($document) => $document->validityStatus());

        return [
            'contract_type' => $employee->contract_type,
            'contract_start' => $employee->contract_start_date?->toDateString(),
            'contract_end' => $employee->contract_end_date?->toDateString(),
            'contract_status' => $employee->contract_end_date ? Employee::expiryStatus($employee->contract_end_date->toDateString()) : 'open',
            'iqama_number' => $employee->iqama_number,
            'iqama_expiry' => $employee->iqama_expiry_date?->toDateString(),
            'iqama_status' => $employee->iqamaStatus(),
            'passport_status' => Employee::expiryStatus($employee->passport_expiry_date?->toDateString()),
            'insurance_status' => Employee::expiryStatus($employee->insurance_expiry_date?->toDateString()),
            'documents_total' => $documents->count(),
            'documents_expired' => $byStatus->get('expired', collect())->count(),
            'documents_expiring' => $byStatus->get('expiring soon', collect())->count(),
            'alerts' => array_values(array_filter([
                $employee->iqamaStatus() === 'expired' ? __('workspace.hr_alert_iqama_expired') : null,
                $employee->iqamaStatus() === 'expiring soon' ? __('workspace.hr_alert_iqama_expiring') : null,
                $employee->contract_end_date && Employee::expiryStatus($employee->contract_end_date->toDateString()) !== 'valid' ? __('workspace.hr_alert_contract', ['date' => $employee->contract_end_date->toDateString()]) : null,
            ])),
        ];
    }

    /**
     * Register and action links, each carrying this employee as the origin so
     * the register page returns here. Only links the viewer may use are built.
     *
     * @return array<string, string|null>
     */
    private static function links(Employee $employee, array $flags, string $selfUrl): array
    {
        $origin = fn (string $section) => $selfUrl.'#'.$section;

        return [
            'attendance_all' => $flags['canViewAttendance'] ? route('admin.hr.attendance.index', ['employee' => $employee->id]) : null,
            'attendance_create' => $flags['canCreateAttendance'] ? route('admin.hr.attendance.create', ['employee' => $employee->id, 'return_to' => $origin('attendance')]) : null,
            'leaves_all' => route('admin.hr.leaves.index', ['employee' => $employee->id]),
            'leaves_create' => $flags['canCreateLeave'] ? route('admin.hr.leaves.create', ['employee' => $employee->id, 'return_to' => $origin('leaves')]) : null,
            'overtime_all' => $flags['canViewOvertime'] ? route('admin.hr.overtime.index', ['employee' => $employee->id]) : null,
            'overtime_create' => $flags['canCreateOvertime'] ? route('admin.hr.overtime.create', ['employee' => $employee->id, 'return_to' => $origin('overtime')]) : null,
            'salary_all' => $flags['canViewPayroll'] ? route('admin.hr.salary-structures.index', ['search' => $employee->employee_code]) : null,
            'salary_create' => $flags['canCreateStructure'] ? route('admin.hr.salary-structures.create', ['employee' => $employee->id]) : null,
            'payroll_all' => $flags['canViewPayroll'] ? route('admin.hr.payroll.index') : null,
            'documents_all' => route('admin.hr.documents.index', ['employee' => $employee->id]),
            'origin_attendance' => $origin('attendance'),
            'origin_leaves' => $origin('leaves'),
            'origin_overtime' => $origin('overtime'),
        ];
    }
}
