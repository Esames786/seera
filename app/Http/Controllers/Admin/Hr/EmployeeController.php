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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

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
        $data = $this->validated($request);
        $documents = $this->validatedDocuments($request);
        $storedPaths = [];

        try {
            $employee = DB::transaction(function () use ($request, $data, $documents, &$storedPaths) {
                $employee = Employee::create($data);
                $this->syncDocuments($request, $employee, $documents, $storedPaths);

                return $employee;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

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
        $data = $this->validated($request, $employee);
        $documents = $this->validatedDocuments($request);
        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $employee, $data, $documents, &$storedPaths) {
                $employee->update($data);
                $this->syncDocuments($request, $employee, $documents, $storedPaths);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

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

    /**
     * Documents are attached from the employee form itself; the Documents
     * screen is a read-only register of everything already on the system.
     */
    private function syncDocuments(Request $request, Employee $employee, array $rows, array &$storedPaths): void
    {
        foreach ($rows as $index => $row) {
            if (blank($row['document_type'] ?? null)) {
                continue;
            }

            $path = null;
            if ($request->hasFile("documents.{$index}.file")) {
                $path = $request->file("documents.{$index}.file")->store('hr-documents', 'local');
                if (! $path) {
                    throw new RuntimeException('The employee document could not be stored.');
                }
                $storedPaths[] = $path;
            }

            $employee->documents()->create([
                'document_type' => $row['document_type'],
                'document_number' => $row['document_number'] ?? null,
                'issue_date' => $row['issue_date'] ?? null,
                'expiry_date' => $row['expiry_date'] ?? null,
                'file_path' => $path,
                'status' => 'active',
            ]);
        }
    }

    private function validatedDocuments(Request $request): array
    {
        return $request->validate([
            'documents' => ['nullable', 'array'],
            'documents.*.document_type' => ['nullable', 'string', 'max:100'],
            'documents.*.document_number' => ['nullable', 'string', 'max:100'],
            'documents.*.issue_date' => ['nullable', 'date'],
            'documents.*.expiry_date' => ['nullable', 'date', 'after_or_equal:documents.*.issue_date'],
            'documents.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ])['documents'] ?? [];
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
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where(fn ($query) => $query->where('department_id', $request->input('department_id')))],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', Rule::exists('sites', 'id')->where(fn ($query) => $query->where('project_id', $request->input('project_id')))],
            'manager_id' => ['nullable', 'exists:users,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'joining_date' => ['nullable', 'date'],
            'contract_type' => ['required', 'string', 'max:50'],
            'employee_classification' => ['required', 'in:Sponsorship,Freelancer'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'iqama_number' => ['nullable', 'string', 'max:50'],
            'iqama_expiry_date' => ['nullable', 'date'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'passport_expiry_date' => ['nullable', 'date'],
            'insurance_number' => ['nullable', 'string', 'max:50'],
            'insurance_expiry_date' => ['nullable', 'date'],
            'driving_license_number' => ['nullable', 'string', 'max:50'],
            'driving_license_expiry_date' => ['nullable', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'food_allowance' => ['nullable', 'numeric', 'min:0'],
            'fuel_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowance' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'max:50'],
            'mobile_access' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive,on leave,terminated'],
        ]);

        $data['mobile_access'] = $request->boolean('mobile_access');

        // Allowances are optional on the form but always stored as a number.
        foreach (['housing_allowance', 'transport_allowance', 'food_allowance', 'fuel_allowance', 'other_allowance'] as $allowance) {
            $data[$allowance] = (float) ($data[$allowance] ?? 0);
        }

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
            'classifications' => ['Sponsorship', 'Freelancer'],
            'paymentMethods' => ['Bank Transfer', 'Cash'],
            'nationalities' => ['Saudi', 'Pakistani', 'Indian', 'Bangladeshi', 'Egyptian', 'Filipino', 'Other'],
        ];
    }
}
