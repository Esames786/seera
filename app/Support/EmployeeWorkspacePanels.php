<?php

namespace App\Support;

use App\Http\Controllers\Admin\Hr\AttendanceController;
use App\Http\Controllers\Admin\Hr\EndOfServiceController;
use App\Http\Controllers\Admin\Hr\LeaveRequestController;
use App\Http\Controllers\Admin\Hr\OvertimeController;
use App\Http\Controllers\Admin\Hr\SalaryStructureController;
use App\Http\Controllers\Admin\UserController;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\EndOfServiceRecord;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRecord;
use App\Models\PayrollRunItem;
use App\Models\Role;
use App\Models\SalaryStructure;
use App\Models\Shift;
use App\Models\User;
use App\Services\Hr\GratuityCalculator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmployeeWorkspacePanels
{
    public const PANELS = [
        'salary' => ['Salary Structures', 'Payroll', SalaryStructure::class, SalaryStructureController::class],
        'attendance' => ['Attendance', 'Attendance', AttendanceRecord::class, AttendanceController::class],
        'leaves' => ['Leaves', 'HR', LeaveRequest::class, LeaveRequestController::class],
        'overtime' => ['Overtime', 'Payroll', OvertimeRecord::class, OvertimeController::class],
        'shifts' => ['Shift Assignments', 'HR', EmployeeShiftAssignment::class, null],
        'eosb' => ['End of Service', 'Payroll', EndOfServiceRecord::class, EndOfServiceController::class],
        'payroll-history' => ['Payroll History', 'Payroll', PayrollRunItem::class, null],
        'account' => ['System Account', 'Users', User::class, UserController::class],
    ];

    public static function query(Employee $employee, string $panel)
    {
        $model = self::PANELS[$panel][2];

        return $panel === 'account' ? $model::whereKey($employee->user_id ?? 0) : $model::where('employee_id', $employee->id);
    }

    public static function editable(string $panel, Model $record): bool
    {
        return match ($panel) {
            'salary', 'payroll-history' => false,
            'eosb' => $record->isEditable(),
            'leaves', 'overtime' => $record->status === 'pending',
            default => true,
        };
    }

    public static function fields(Employee $employee, string $panel, ?Model $record): array
    {
        $fields = [];
        $add = function (string $name, string $type = 'text', mixed $default = '', array $options = [], bool $required = false) use (&$fields, $record) {
            $value = $type === 'password' ? '' : ($record?->{$name} ?? $default);
            if ($value instanceof CarbonInterface) {
                $value = $value->toDateString();
            }
            if (is_bool($value)) {
                $value = (int) $value;
            }
            if ($type === 'time' && $value) {
                $value = substr($value, 0, 5);
            }
            $fields[] = compact('name', 'type', 'value', 'options', 'required') + ['label' => Str::headline($name)];
        };
        $choices = fn (array $values) => array_combine($values, $values);
        $amounts = fn () => $employee->payrollDefaults();
        if ($panel === 'salary') {
            foreach ($amounts() as $name => $value) {
                $add($name, 'number', $value, required: true);
            }
            $add('fixed_deduction', 'number', 0, required: true);
            $add('effective_from', 'date', today()->toDateString(), required: true);
            $add('effective_to', 'date');
            $add('status', 'select', 'active', $choices(['active', 'inactive']), true);
        } elseif ($panel === 'attendance') {
            $add('attendance_date', 'date', today()->toDateString(), required: true);
            $add('shift_id', 'select', '', Shift::orderBy('name')->pluck('name', 'id')->all());
            $add('check_in', 'time');
            $add('check_out', 'time');
            $add('late_minutes', 'number', 0, required: true);
            $add('overtime_minutes', 'number', 0, required: true);
            $add('status', 'select', 'present', $choices(AttendanceRecord::STATUSES), true);
            $add('geofence_status', 'select', 'unknown', $choices(AttendanceRecord::GEOFENCE_STATUSES), true);
            $add('remarks', 'textarea');
        } elseif ($panel === 'leaves') {
            $add('leave_type_id', 'select', '', LeaveType::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(), true);
            $add('start_date', 'date', required: true);
            $add('end_date', 'date', required: true);
            $override = $record && (float) $record->total_days !== (float) ($record->start_date->diffInDays($record->end_date) + 1);
            $add('total_days_override', 'select', (int) $override, [0 => 'Calculate from dates', 1 => 'Agreed exception / half day'], true);
            $add('total_days', 'number', '');
            $add('reason', 'textarea');
            $add('attachment', 'file');
        } elseif ($panel === 'overtime') {
            $add('overtime_date', 'date', today()->toDateString(), required: true);
            $add('attendance_record_id', 'select', '', $employee->attendanceRecords()->latest('attendance_date')->limit(100)->get()->mapWithKeys(fn ($row) => [$row->id => $row->attendance_date->toDateString()])->all());
            $add('hours', 'number', 0, required: true);
            $add('rate', 'number', 0, required: true);
            $add('reason', 'textarea');
        } elseif ($panel === 'shifts') {
            $add('shift_id', 'select', '', Shift::orderBy('name')->pluck('name', 'id')->all(), true);
            $add('effective_from', 'date', today()->toDateString(), required: true);
            $add('effective_to', 'date');
            $add('status', 'select', 'active', $choices(['active', 'inactive']), true);
        } elseif ($panel === 'eosb') {
            $add('termination_date', 'date', today()->toDateString(), required: true);
            $add('termination_reason', 'select', 'termination', GratuityCalculator::reasonLabels(), true);
            $add('service_years', 'number', $employee->joining_date ? round(max(0, $employee->joining_date->diffInDays(today())) / 365, 2) : 0, required: true);
            // Human must review the applicable final wage; no new legal formula.
            $add('last_basic_salary', 'number', array_sum($amounts()), required: true);
            $add('manual_override', 'select', 0, [0 => 'Automatic calculation', 1 => 'Manual override'], true);
            foreach (['eosb_amount', 'leave_salary', 'other_dues', 'deductions'] as $field) {
                $add($field, 'number', 0);
            }
            $add('reason', 'textarea');
        } elseif ($panel === 'account') {
            $add('name', default: $employee->name, required: true);
            $add('email', 'email', $employee->email, required: true);
            $add('phone', default: $employee->phone);
            $add('username');
            $add('language', 'select', 'English', $choices(['English', 'Arabic']), true);
            $add('role_id', 'select', $record?->primaryRole()?->id ?? '', Role::orderBy('name')->pluck('name', 'id')->all(), true);
            $add('status', 'select', 'active', $choices(['active', 'inactive', 'locked', 'pending']), true);
            $add('password', 'password');
            foreach (['mobile_access', 'two_factor_enabled', 'temporary_access'] as $field) {
                $add($field, 'select', 0, [0 => 'No', 1 => 'Yes']);
            }
            $add('access_start_date', 'date');
            $add('access_end_date', 'date');
        }

        return $fields;
    }

    public static function columns(string $panel): array
    {
        return match ($panel) {
            'salary' => ['effective_from', 'effective_to', 'basic_salary', 'housing_allowance', 'status'],
            'attendance' => ['attendance_date', 'check_in', 'check_out', 'status', 'source'],
            'leaves' => ['start_date', 'end_date', 'total_days', 'reason', 'status'],
            'overtime' => ['overtime_date', 'hours', 'rate', 'amount', 'status'],
            'shifts' => ['shift_id', 'effective_from', 'effective_to', 'status'],
            'eosb' => ['termination_date', 'service_years', 'eosb_amount', 'final_amount', 'status'],
            'payroll-history' => ['payroll_run_id', 'basic_salary', 'total_allowances', 'overtime_amount', 'total_deductions', 'net_amount'],
            'account' => ['name', 'email', 'username', 'language', 'status'],
        };
    }
}
