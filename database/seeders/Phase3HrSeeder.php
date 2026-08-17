<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeShiftAssignment;
use App\Models\EndOfServiceRecord;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRecord;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\SalaryStructure;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class Phase3HrSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = $this->seedShifts();
        $leaveTypes = $this->seedLeaveTypes();
        $employees = $this->seedEmployees();

        $this->seedDocuments($employees);
        $this->seedShiftAssignments($employees, $shifts);
        $this->seedAttendance($employees, $shifts);
        $this->seedLeaveRequests($employees, $leaveTypes);
        $this->seedOvertime($employees);
        $this->seedSalaryStructures($employees);
        $this->seedPayrollRun($employees);
        $this->seedEndOfService($employees);
    }

    private function seedShifts(): array
    {
        $shifts = [
            ['name' => 'Day Shift', 'code' => 'DAY', 'start_time' => '08:00', 'end_time' => '17:00', 'break_minutes' => 60, 'grace_minutes' => 10, 'overtime_after_minutes' => 540],
            ['name' => 'Night Shift', 'code' => 'NIGHT', 'start_time' => '20:00', 'end_time' => '05:00', 'break_minutes' => 60, 'grace_minutes' => 15, 'overtime_after_minutes' => 540],
            ['name' => 'Split Shift', 'code' => 'SPLIT', 'start_time' => '07:00', 'end_time' => '19:00', 'break_minutes' => 180, 'grace_minutes' => 10, 'overtime_after_minutes' => 540],
        ];

        foreach ($shifts as $shift) {
            Shift::create($shift + ['status' => 'active']);
        }

        return Shift::pluck('id', 'code')->all();
    }

    private function seedLeaveTypes(): array
    {
        $types = [
            ['name' => 'Annual Leave', 'code' => 'ANNUAL', 'max_days_per_year' => 21, 'is_paid' => true],
            ['name' => 'Sick Leave', 'code' => 'SICK', 'max_days_per_year' => 30, 'is_paid' => true],
            ['name' => 'Emergency Leave', 'code' => 'EMERGENCY', 'max_days_per_year' => 5, 'is_paid' => true],
            ['name' => 'Unpaid Leave', 'code' => 'UNPAID', 'max_days_per_year' => 30, 'is_paid' => false],
        ];

        foreach ($types as $type) {
            LeaveType::create($type + ['status' => 'active']);
        }

        return LeaveType::pluck('id', 'code')->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function seedEmployees()
    {
        $departments = Department::pluck('id', 'code');
        $designations = Designation::pluck('id', 'name');
        $branches = Branch::pluck('id', 'code');
        $projects = Project::pluck('id', 'code');
        $sites = Site::pluck('id', 'code');
        $users = User::pluck('id', 'email');

        $manager = $users['ashfaq@example.com'];
        $hrManager = $users['zubair@example.com'];

        $rows = [
            ['Omar', 'Al Otaibi', 'ADMIN', null, 'BR-RYD', null, null, 25000, 'Saudi', $users['admin@example.com'], true],
            ['Nabeel', 'Ahmed', 'SITE', 'Site Supervisor', 'BR-RYD', 'PRJ-001', 'SITE-A', 8500, 'Pakistani', $users['nabeel@example.com'], true],
            ['Kamran', 'Iqbal', 'EQP', 'Mechanic', 'BR-JED', 'PRJ-002', 'SITE-YARD', 4200, 'Pakistani', $users['kamran@example.com'], true],
            ['Fatima', 'Al Harbi', 'FIN', 'Finance Manager', 'BR-RYD', null, null, 18000, 'Saudi', $users['fatima@example.com'], false],
            ['Zubair', 'Khan', 'HR', 'HR Manager', 'BR-JED', null, null, 16000, 'Pakistani', $hrManager, false],
            ['Waiz', 'Rahman', 'SITE', 'Site Supervisor', 'BR-JED', 'PRJ-002', 'SITE-YARD', 8000, 'Bangladeshi', $users['waiz@example.com'], true],
            ['Uzaid', 'Malik', 'SITE', 'Site Supervisor', 'BR-DMM', 'PRJ-003', 'SITE-D1', 7800, 'Pakistani', $users['uzaid@example.com'], true],
            ['Imran', 'Shah', 'EQP', 'Mechanic', 'BR-DMM', 'PRJ-003', 'SITE-D1', 4000, 'Pakistani', $users['imran@example.com'], true],
            ['Pradip', 'Kumar', 'SITE', null, 'BR-RYD', 'PRJ-001', 'SITE-A', 3200, 'Indian', null, true],
            ['Rashid', 'Al Zahrani', 'PRJ', 'Project Manager', 'BR-RYD', 'PRJ-001', null, 20000, 'Saudi', $manager, true],
            ['Sami', 'Ullah', 'SITE', null, 'BR-RYD', 'PRJ-001', 'SITE-B', 3000, 'Pakistani', null, true],
            ['Jose', 'Ramirez', 'SITE', null, 'BR-RYD', 'PRJ-001', 'SITE-B', 3400, 'Filipino', null, true],
            ['Ahmed', 'Mostafa', 'EQP', 'Store Keeper', 'BR-JED', 'PRJ-002', 'SITE-YARD', 4600, 'Egyptian', null, true],
            ['Bilal', 'Hussain', 'SITE', null, 'BR-JED', 'PRJ-002', 'SITE-YARD', 3100, 'Pakistani', null, true],
            ['Rakib', 'Hasan', 'SITE', null, 'BR-DMM', 'PRJ-003', 'SITE-D1', 2950, 'Bangladeshi', null, true],
            ['Vinod', 'Sharma', 'SITE', null, 'BR-DMM', 'PRJ-003', 'SITE-D1', 3050, 'Indian', null, true],
            ['Khalid', 'Al Dosari', 'PRJ', null, 'BR-DMM', 'PRJ-003', null, 12000, 'Saudi', null, false],
            ['Noor', 'Alam', 'HR', null, 'BR-RYD', null, null, 7000, 'Pakistani', null, false],
            ['Salman', 'Farooq', 'FIN', null, 'BR-RYD', null, null, 9000, 'Pakistani', null, false],
            ['Yusuf', 'Idris', 'EQP', 'Mechanic', 'BR-JED', 'PRJ-002', 'SITE-YARD', 4300, 'Egyptian', null, true],
        ];

        foreach ($rows as $index => [$first, $last, $deptCode, $designation, $branchCode, $projectCode, $siteCode, $salary, $nationality, $userId, $mobile]) {
            $joining = Carbon::create(2024, 1, 1)->addDays($index * 37);

            Employee::create([
                'employee_code' => sprintf('EMP-%03d', $index + 1),
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower($first).'.'.strtolower(str_replace(' ', '', $last)).'@company.sa',
                'phone' => '+966 5'.(($index % 6) + 1).' '.str_pad((string) (100 + $index), 3, '0', STR_PAD_LEFT).' '.str_pad((string) (2000 + $index), 4, '0', STR_PAD_LEFT),
                'emergency_contact' => '+966 55 900 '.str_pad((string) (1000 + $index), 4, '0', STR_PAD_LEFT),
                'nationality' => $nationality,
                'department_id' => $departments[$deptCode] ?? null,
                'designation_id' => $designation ? ($designations[$designation] ?? null) : null,
                'branch_id' => $branches[$branchCode] ?? null,
                'project_id' => $projectCode ? ($projects[$projectCode] ?? null) : null,
                'site_id' => $siteCode ? ($sites[$siteCode] ?? null) : null,
                'manager_id' => $manager,
                'user_id' => $userId,
                'joining_date' => $joining->toDateString(),
                'contract_type' => $index % 5 === 0 ? 'Contract' : 'Full Time',
                'contract_start_date' => $joining->toDateString(),
                'contract_end_date' => $joining->copy()->addYears(2)->toDateString(),
                'iqama_number' => $nationality === 'Saudi' ? null : '24'.str_pad((string) (500000 + $index * 137), 8, '0', STR_PAD_LEFT),
                // A spread of expiry dates so expired / expiring soon / valid are all visible.
                'iqama_expiry_date' => $nationality === 'Saudi' ? null : now()->addDays(($index * 47) - 30)->toDateString(),
                'passport_number' => 'AB'.str_pad((string) (1000000 + $index * 913), 7, '0', STR_PAD_LEFT),
                'passport_expiry_date' => now()->addYears(2)->addDays($index * 11)->toDateString(),
                'basic_salary' => $salary,
                'payment_method' => $index % 7 === 0 ? 'Cash' : 'Bank Transfer',
                'bank_name' => $index % 7 === 0 ? null : 'Al Rajhi Bank',
                'iban' => $index % 7 === 0 ? null : 'SA'.str_pad((string) (4400000000000000 + $index), 22, '0', STR_PAD_LEFT),
                'mobile_access' => $mobile,
                'status' => $index === 19 ? 'inactive' : 'active',
            ]);
        }

        return Employee::orderBy('id')->get();
    }

    private function seedDocuments($employees): void
    {
        foreach ($employees as $index => $employee) {
            if ($employee->iqama_number) {
                EmployeeDocument::create([
                    'employee_id' => $employee->id,
                    'document_type' => 'IQAMA',
                    'document_number' => $employee->iqama_number,
                    'issue_date' => $employee->iqama_expiry_date?->copy()->subYear()->toDateString(),
                    'expiry_date' => $employee->iqama_expiry_date?->toDateString(),
                    'file_path' => null,
                    'status' => 'active',
                    'notes' => 'Imported with the employee profile.',
                ]);
            }

            EmployeeDocument::create([
                'employee_id' => $employee->id,
                'document_type' => 'Passport',
                'document_number' => $employee->passport_number,
                'issue_date' => $employee->passport_expiry_date?->copy()->subYears(5)->toDateString(),
                'expiry_date' => $employee->passport_expiry_date?->toDateString(),
                'status' => 'active',
            ]);

            if ($index % 4 === 0) {
                EmployeeDocument::create([
                    'employee_id' => $employee->id,
                    'document_type' => 'Driving License',
                    'document_number' => 'DL-'.str_pad((string) (8000 + $index), 4, '0', STR_PAD_LEFT),
                    'issue_date' => now()->subYears(2)->toDateString(),
                    'expiry_date' => now()->addDays(30 + $index * 21)->toDateString(),
                    'status' => 'active',
                ]);
            }

            if ($index % 5 === 0) {
                EmployeeDocument::create([
                    'employee_id' => $employee->id,
                    'document_type' => 'Contract',
                    'document_number' => 'CT-'.$employee->employee_code,
                    'issue_date' => $employee->contract_start_date?->toDateString(),
                    'expiry_date' => $employee->contract_end_date?->toDateString(),
                    'status' => 'active',
                ]);
            }
        }
    }

    private function seedShiftAssignments($employees, array $shifts): void
    {
        $codes = array_keys($shifts);

        foreach ($employees as $index => $employee) {
            EmployeeShiftAssignment::create([
                'employee_id' => $employee->id,
                'shift_id' => $shifts[$codes[$index % count($codes)]],
                'effective_from' => now()->startOfYear()->toDateString(),
                'status' => 'active',
            ]);
        }
    }

    private function seedAttendance($employees, array $shifts): void
    {
        $codes = array_keys($shifts);
        $today = now()->startOfDay();
        $start = $today->copy()->startOfWeek();

        // Early in the week there is barely anything to show, so reach back into
        // the previous week to keep at least six days of demo attendance.
        if ((int) $start->diffInDays($today) < 5) {
            $start = $today->copy()->subDays(6);
        }

        $days = (int) $start->diffInDays($today) + 1;

        foreach ($employees->where('status', 'active') as $index => $employee) {
            for ($day = 0; $day < $days; $day++) {
                $date = $start->copy()->addDays($day);

                if ($date->isFriday()) {
                    continue;
                }

                [$status, $checkIn, $checkOut, $late, $overtime] = match (($index + $day) % 7) {
                    0 => ['late', '08:34', '17:10', 24, 0],
                    5 => ['absent', null, null, 0, 0],
                    6 => ['leave', null, null, 0, 0],
                    default => ['present', '08:0'.($day % 9), '17:2'.($day % 9), 0, ($day % 3) * 15],
                };

                AttendanceRecord::create([
                    'employee_id' => $employee->id,
                    'project_id' => $employee->project_id,
                    'site_id' => $employee->site_id,
                    'shift_id' => $shifts[$codes[$index % count($codes)]],
                    'attendance_date' => $date->toDateString(),
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'late_minutes' => $late,
                    'overtime_minutes' => $overtime,
                    'status' => $status,
                    'source' => match (($index + $day) % 3) {
                        0 => 'mobile',
                        1 => 'manual',
                        default => 'offline',
                    },
                    'geofence_status' => ($index + $day) % 11 === 0 ? 'outside' : 'inside',
                    'remarks' => null,
                ]);
            }
        }
    }

    private function seedLeaveRequests($employees, array $leaveTypes): void
    {
        $hrManager = User::where('email', 'zubair@example.com')->value('id');
        $codes = array_keys($leaveTypes);

        foreach ($employees->take(8)->values() as $index => $employee) {
            $start = now()->addDays(($index * 3) - 6);
            $end = $start->copy()->addDays($index % 3 + 1);
            $status = match ($index % 3) {
                0 => 'pending',
                1 => 'approved',
                default => 'rejected',
            };

            LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveTypes[$codes[$index % count($codes)]],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'total_days' => $start->diffInDays($end) + 1,
                'reason' => match ($index % 4) {
                    0 => 'Family matters',
                    1 => 'Medical appointment',
                    2 => 'Travel to home country',
                    default => 'Personal',
                },
                'status' => $status,
                'approved_by' => $status === 'pending' ? null : $hrManager,
                'approved_at' => $status === 'pending' ? null : now()->subDay(),
                'rejection_reason' => $status === 'rejected' ? 'Peak site activity during requested dates.' : null,
            ]);
        }
    }

    private function seedOvertime($employees): void
    {
        $supervisor = User::where('email', 'nabeel@example.com')->value('id');

        foreach ($employees->take(6)->values() as $index => $employee) {
            $hours = 1.5 + ($index % 4) * 0.5;
            $rate = round(((float) $employee->basic_salary / 30 / 8) * 1.5, 2);
            $status = $index % 2 === 0 ? 'approved' : 'pending';

            OvertimeRecord::create([
                'employee_id' => $employee->id,
                'attendance_record_id' => $employee->attendanceRecords()->orderByDesc('attendance_date')->value('id'),
                'overtime_date' => now()->startOfWeek()->addDays($index % 4)->toDateString(),
                'hours' => $hours,
                'rate' => $rate,
                'amount' => round($hours * $rate, 2),
                'reason' => $index % 2 === 0 ? 'Concrete pouring extended' : 'Emergency equipment repair',
                'status' => $status,
                'approved_by' => $status === 'approved' ? $supervisor : null,
                'approved_at' => $status === 'approved' ? now()->subDays(2) : null,
            ]);
        }
    }

    private function seedSalaryStructures($employees): void
    {
        foreach ($employees as $index => $employee) {
            $basic = (float) $employee->basic_salary;

            $structure = SalaryStructure::create([
                'employee_id' => $employee->id,
                'basic_salary' => $basic,
                'housing_allowance' => round($basic * 0.25, 2),
                'transport_allowance' => round($basic * 0.10, 2),
                'food_allowance' => $index % 3 === 0 ? 300 : 0,
                'other_allowance' => 0,
                'fixed_deduction' => $index % 6 === 0 ? 150 : 0,
                'effective_from' => now()->startOfYear()->toDateString(),
                'status' => 'active',
            ]);

            if ($index % 4 === 0) {
                $structure->items()->createMany([
                    ['item_type' => 'allowance', 'name' => 'Site Allowance', 'amount' => 400, 'is_taxable' => false],
                    ['item_type' => 'deduction', 'name' => 'Advance Recovery', 'amount' => 200, 'is_taxable' => false],
                ]);
            }
        }
    }

    private function seedPayrollRun($employees): void
    {
        $period = now();

        $run = PayrollRun::create([
            'code' => sprintf('PR-%04d%02d-01', $period->year, $period->month),
            'payroll_month' => $period->month,
            'payroll_year' => $period->year,
            'period_start' => $period->copy()->startOfMonth()->toDateString(),
            'period_end' => $period->copy()->endOfMonth()->toDateString(),
            'status' => 'draft',
            'notes' => 'Seeded draft payroll run for the current month.',
        ]);

        $gross = 0.0;
        $deductions = 0.0;
        $net = 0.0;
        $active = $employees->where('status', 'active');

        foreach ($active as $employee) {
            $structure = SalaryStructure::with('items')->where('employee_id', $employee->id)->where('status', 'active')->first();

            $basic = (float) ($structure?->basic_salary ?? $employee->basic_salary);
            $allowances = (float) ($structure?->totalAllowances() ?? 0);
            $itemDeductions = (float) ($structure?->totalDeductions() ?? 0);

            $overtime = (float) OvertimeRecord::where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereDate('overtime_date', '>=', $run->period_start)
                ->whereDate('overtime_date', '<=', $run->period_end)
                ->sum('amount');

            $itemGross = $basic + $allowances + $overtime;

            $run->items()->create([
                'employee_id' => $employee->id,
                'basic_salary' => round($basic, 2),
                'total_allowances' => round($allowances, 2),
                'overtime_amount' => round($overtime, 2),
                'total_deductions' => round($itemDeductions, 2),
                'gross_amount' => round($itemGross, 2),
                'net_amount' => round($itemGross - $itemDeductions, 2),
                'present_days' => $employee->attendanceRecords()
                    ->whereIn('status', ['present', 'late'])
                    ->whereDate('attendance_date', '>=', $run->period_start)
                    ->whereDate('attendance_date', '<=', $run->period_end)
                    ->count(),
                'leave_days' => $employee->attendanceRecords()
                    ->where('status', 'leave')
                    ->whereDate('attendance_date', '>=', $run->period_start)
                    ->whereDate('attendance_date', '<=', $run->period_end)
                    ->count(),
            ]);

            $gross += $itemGross;
            $deductions += $itemDeductions;
            $net += $itemGross - $itemDeductions;
        }

        $run->update([
            'total_employees' => $active->count(),
            'gross_amount' => round($gross, 2),
            'total_deductions' => round($deductions, 2),
            'net_amount' => round($net, 2),
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    private function seedEndOfService($employees): void
    {
        $employee = $employees->firstWhere('status', 'inactive') ?? $employees->last();
        $basic = (float) $employee->basic_salary;
        $serviceYears = 3.5;
        $eosb = round(($basic / 2) * 5 + $basic * ($serviceYears - 5 > 0 ? $serviceYears - 5 : 0), 2);

        EndOfServiceRecord::create([
            'employee_id' => $employee->id,
            'termination_date' => now()->endOfMonth()->toDateString(),
            'service_years' => $serviceYears,
            'last_basic_salary' => $basic,
            'eosb_amount' => $eosb,
            'leave_salary' => 2000,
            'other_dues' => 500,
            'deductions' => 500,
            'final_amount' => round($eosb + 2000 + 500 - 500, 2),
            'reason' => 'Contract completed.',
            'status' => 'draft',
        ]);
    }
}
