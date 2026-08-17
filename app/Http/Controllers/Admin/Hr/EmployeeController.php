<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $employees = Employee::with(['department', 'designation', 'project', 'site'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('iqama_number', 'like', "%{$search}%"));
            })
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('designation'), fn ($q) => $q->where('designation_id', $request->integer('designation')))
            ->when($request->filled('branch'), fn ($q) => $q->where('branch_id', $request->integer('branch')))
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->integer('project')))
            ->when($request->filled('site'), fn ($q) => $q->where('site_id', $request->integer('site')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('employee_code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.hr.employees.index', [
            'employees' => $employees,
            'totalEmployees' => Employee::count(),
            'activeEmployees' => Employee::where('status', 'active')->count(),
            'mobileEmployees' => Employee::where('mobile_access', true)->count(),
            'expiringIqamas' => Employee::whereNotNull('iqama_expiry_date')
                ->whereDate('iqama_expiry_date', '<=', now()->addDays(60))
                ->count(),
        ] + $this->filterOptions());
    }

    public function create(): View
    {
        return view('admin.hr.employees.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = Employee::create($this->validated($request));

        ActivityLog::record($request, 'HR', 'Created employee', $employee->name);

        return redirect()->route('admin.hr.employees.index')
            ->with('status', 'Employee "'.$employee->name.'" created successfully.');
    }

    public function show(Employee $employee): View
    {
        $employee->load([
            'department', 'designation', 'branch', 'project', 'site', 'manager', 'user',
            'documents', 'shiftAssignments.shift',
            'salaryStructures.items',
        ]);

        return view('admin.hr.employees.show', [
            'employee' => $employee,
            'attendance' => $employee->attendanceRecords()->with('shift')->latest('attendance_date')->limit(10)->get(),
            'leaves' => $employee->leaveRequests()->with('leaveType')->latest('id')->limit(10)->get(),
            'overtime' => $employee->overtimeRecords()->latest('overtime_date')->limit(10)->get(),
            'payrollItems' => $employee->payrollItems()->with('payrollRun')->latest('id')->limit(10)->get(),
            'presentDays' => $employee->attendanceRecords()
                ->whereIn('status', ['present', 'late'])
                ->whereBetween('attendance_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'workedDays' => $employee->attendanceRecords()
                ->whereBetween('attendance_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'pendingCount' => $employee->leaveRequests()->where('status', 'pending')->count()
                + $employee->overtimeRecords()->where('status', 'pending')->count(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        return view('admin.hr.employees.edit', ['employee' => $employee] + $this->formOptions());
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $employee->update($this->validated($request, $employee));

        ActivityLog::record($request, 'HR', 'Updated employee', $employee->name);

        return redirect()->route('admin.hr.employees.index')
            ->with('status', 'Employee "'.$employee->name.'" updated successfully.');
    }

    /**
     * Employees are deactivated rather than deleted so payroll history stays intact.
     */
    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $employee->update(['status' => 'inactive']);

        ActivityLog::record($request, 'HR', 'Deactivated employee', $employee->name);

        return redirect()->route('admin.hr.employees.index')
            ->with('status', 'Employee "'.$employee->name.'" deactivated. Historical records are kept.');
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:50', 'unique:employees,employee_code'.($employee ? ','.$employee->id : '')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'joining_date' => ['nullable', 'date'],
            'contract_type' => ['required', 'string', 'max:50'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'iqama_number' => ['nullable', 'string', 'max:50'],
            'iqama_expiry_date' => ['nullable', 'date'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'passport_expiry_date' => ['nullable', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'max:50'],
            'mobile_access' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive,on leave,terminated'],
        ]);

        $data['mobile_access'] = $request->boolean('mobile_access');

        return $data;
    }

    private function filterOptions(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
        ];
    }

    private function formOptions(): array
    {
        return $this->filterOptions() + [
            'users' => User::orderBy('name')->get(),
            'contractTypes' => ['Full Time', 'Part Time', 'Contract', 'Temporary'],
            'paymentMethods' => ['Bank Transfer', 'Cash'],
            'nationalities' => ['Saudi', 'Pakistani', 'Indian', 'Bangladeshi', 'Egyptian', 'Filipino', 'Other'],
        ];
    }
}
