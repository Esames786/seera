<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LookupValue;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Support\CodeGenerator;
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
            // Document filters on the employee list (client change request NR-14).
            ->when($request->filled('doc_status') || $request->filled('doc_type'), function ($query) use ($request) {
                $today = now()->startOfDay();
                $query->whereHas('documents', function ($documents) use ($request, $today) {
                    $documents->where('status', 'active')
                        ->when($request->filled('doc_type'), fn ($q) => $q->where('document_type', $request->string('doc_type')))
                        ->when($request->string('doc_status')->toString() === 'expired', fn ($q) => $q->whereNotNull('expiry_date')->whereDate('expiry_date', '<', $today))
                        ->when($request->string('doc_status')->toString() === 'expiring', fn ($q) => $q->whereNotNull('expiry_date')->whereDate('expiry_date', '>=', $today)->whereDate('expiry_date', '<=', $today->copy()->addDays(60)))
                        ->when($request->string('doc_status')->toString() === 'valid', fn ($q) => $q->whereNotNull('expiry_date')->whereDate('expiry_date', '>', $today->copy()->addDays(60)));
                });
            })
            ->orderBy('employee_code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.hr.employees.index', [
            'documentTypes' => EmployeeDocument::types(),
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
                $employee->syncDocumentSummary();
                $this->syncUserClassification($employee);
                // The pay typed here becomes the employee's first salary structure (FR-04).
                $structure = $employee->ensureSalaryStructure();

                return [$employee, $structure];
            });
            [$employee, $structure] = $employee;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        ActivityLog::record($request, 'HR', 'Created employee', $employee->name);

        return $this->savedDestination($request, $employee)
            ->with('status', 'Employee "'.$employee->name.'" created with code '.$employee->employee_code.'.'
                .($structure ? ' Their salary structure was created from the payroll information, effective '.$structure->effective_from->toDateString().'.' : ''));
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
            'leaves' => $employee->leaveRequests()->with('leaveType')->latest('start_date')->limit(10)->get(),
            'leaveBalance' => $employee->leaveBalance(),
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
        if (auth()->user()->hasPermission('Payroll', 'view')) {
            $employee->load('activeSalaryStructure.items');
        }

        return view('admin.hr.employees.edit', ['employee' => $employee] + $this->formOptions($employee));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validated($request, $employee);
        $documents = $this->validatedDocuments($request);
        $existing = $this->validatedExistingDocuments($request, $employee);
        $storedPaths = [];
        $replacedPaths = [];

        try {
            $structure = DB::transaction(function () use ($request, $employee, $data, $documents, $existing, &$storedPaths, &$replacedPaths) {
                $employee->update($data);
                $this->syncDocuments($request, $employee, $documents, $storedPaths);
                $this->updateExistingDocuments($request, $employee, $existing, $storedPaths, $replacedPaths);
                $employee->syncDocumentSummary();
                $this->syncUserClassification($employee);

                // Employees saved before this release get their first structure now.
                // An existing structure is never rewritten: that would change payroll history.
                return $employee->ensureSalaryStructure();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        // Old files of renewed documents go only once the new ones are safely saved.
        Storage::disk('local')->delete($replacedPaths);

        ActivityLog::record($request, 'HR', 'Updated employee', $employee->name);

        $message = 'Employee "'.$employee->name.'" updated successfully.';

        if ($structure) {
            $message .= ' Their salary structure was created from the payroll information, effective '.$structure->effective_from->toDateString().'.';
        } elseif ($employee->load('activeSalaryStructure')->salaryStructureOutOfDate()) {
            $message .= ' The payroll information no longer matches their active salary structure; open the employee to raise a new structure from the new figures.';
        }

        return $this->savedDestination($request, $employee)->with('status', $message);
    }

    private function savedDestination(Request $request, Employee $employee): RedirectResponse
    {
        if (! in_array($request->input('_save_action'), ['stay', 'next'], true)) {
            return redirect()->route('admin.hr.employees.index');
        }
        $sections = ['personal', 'employment', 'payroll', 'documents', 'access'];
        $section = in_array($request->input('_workspace_section'), $sections, true) ? $request->input('_workspace_section') : 'personal';
        if ($request->input('_save_action') === 'next') {
            $section = $sections[min(array_search($section, $sections, true) + 1, count($sections) - 1)];
        }

        return redirect()->to(route('admin.hr.employees.edit', $employee).'#'.$section);
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
     * The classification lives on both the HR record and the login it is linked
     * to; saving either side updates the other so there is one answer per person.
     */
    private function syncUserClassification(Employee $employee): void
    {
        if (! $employee->user_id) {
            return;
        }

        User::whereKey($employee->user_id)
            ->where(fn ($q) => $q->whereNull('employee_classification')->orWhere('employee_classification', '!=', $employee->employee_classification))
            ->update(['employee_classification' => $employee->employee_classification]);
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
                'document_subtype' => filled($row['document_subtype'] ?? null) ? trim($row['document_subtype']) : null,
                'document_number' => $row['document_number'] ?? null,
                'issue_date' => $row['issue_date'] ?? null,
                'expiry_date' => $row['expiry_date'] ?? null,
                'file_path' => $path,
                'status' => 'active',
            ]);
        }
    }

    /**
     * Renewals (client change request NR-13): change the number, detail or
     * dates of a saved document and optionally replace its file. Only rows that
     * belong to this employee are accepted.
     */
    private function updateExistingDocuments(Request $request, Employee $employee, array $rows, array &$storedPaths, array &$replacedPaths): void
    {
        if ($rows === []) {
            return;
        }

        $documents = $employee->documents()->whereIn('id', array_keys($rows))->get()->keyBy('id');

        foreach ($rows as $id => $row) {
            $document = $documents->get((int) $id);
            if (! $document) {
                continue;
            }

            $attributes = [
                'document_subtype' => filled($row['document_subtype'] ?? null) ? trim($row['document_subtype']) : null,
                'document_number' => $row['document_number'] ?? null,
                'issue_date' => $row['issue_date'] ?? null,
                'expiry_date' => $row['expiry_date'] ?? null,
            ];

            if ($request->hasFile("existing_documents.{$id}.file")) {
                $path = $request->file("existing_documents.{$id}.file")->store('hr-documents', 'local');
                if (! $path) {
                    throw new RuntimeException('The renewed document could not be stored.');
                }
                $storedPaths[] = $path;
                if ($document->file_path) {
                    $replacedPaths[] = $document->file_path;
                }
                $attributes['file_path'] = $path;
            }

            $document->update($attributes);
        }
    }

    private function validatedExistingDocuments(Request $request, Employee $employee): array
    {
        $rows = $request->validate([
            'existing_documents' => ['nullable', 'array'],
            'existing_documents.*.document_subtype' => ['nullable', 'string', 'max:100'],
            'existing_documents.*.document_number' => ['nullable', 'string', 'max:100'],
            'existing_documents.*.issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'existing_documents.*.expiry_date' => ['nullable', 'date', 'after_or_equal:existing_documents.*.issue_date'],
            'existing_documents.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ])['existing_documents'] ?? [];

        $ownIds = $employee->documents()->pluck('id')->map(fn ($id) => (string) $id)->all();

        return array_intersect_key($rows, array_flip($ownIds));
    }

    private function validatedDocuments(Request $request): array
    {
        return $request->validate([
            'documents' => ['nullable', 'array'],
            'documents.*.document_type' => ['nullable', 'string', 'max:100'],
            'documents.*.document_subtype' => ['nullable', 'string', 'max:100'],
            'documents.*.document_number' => ['nullable', 'string', 'max:100'],
            'documents.*.issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'documents.*.expiry_date' => ['nullable', 'date', 'after_or_equal:documents.*.issue_date'],
            'documents.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ])['documents'] ?? [];
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            // Blank on a new employee means "number it for me" (NR-03): the prefix follows the classification.
            'employee_code' => [$employee ? 'required' : 'nullable', 'string', 'max:50', 'unique:employees,employee_code'.($employee ? ','.$employee->id : '')],
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
            'joining_date' => ['nullable', 'date', 'before_or_equal:today'],
            'contract_type' => ['required', 'string', 'max:50'],
            'employee_classification' => ['required', Rule::in(Employee::CLASSIFICATIONS)],
            // A contract starts today or earlier, exactly like the joining date
            // (client feedback FR-01). The end date may still be in the future.
            'contract_start_date' => ['nullable', 'date', 'before_or_equal:today'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'annual_leave_entitlement' => ['nullable', 'integer', 'min:0', 'max:365'],
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

        if (! $employee && blank($data['employee_code'] ?? null)) {
            $prefix = config('seera.employee_codes.'.$data['employee_classification'], 'EMP-');
            $data['employee_code'] = CodeGenerator::sequential('employees', 'employee_code', $prefix);
        }

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

    private function formOptions(?Employee $employee = null): array
    {
        return $this->filterOptions() + [
            'users' => User::orderBy('name')->get(),
            'contractTypes' => ['Full Time', 'Part Time', 'Contract', 'Temporary'],
            'classifications' => Employee::CLASSIFICATIONS,
            'codePrefixes' => config('seera.employee_codes'),
            // Shown on the Add Employee form so the automatic number is visible
            // before saving (client feedback FR-06). It is a preview: the number
            // is taken at save, so two people filling the form cannot collide.
            'nextCodes' => collect(config('seera.employee_codes'))
                ->map(fn (string $prefix) => CodeGenerator::sequential('employees', 'employee_code', $prefix)),
            'documentTypes' => EmployeeDocument::types(),
            'documentSubtypes' => EmployeeDocument::whereNotNull('document_subtype')->distinct()->orderBy('document_subtype')->pluck('document_subtype'),
            'paymentMethods' => ['Bank Transfer', 'Cash'],
            'nationalities' => LookupValue::options('nationality', $employee?->nationality),
        ];
    }
}
