<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\SalaryStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SalaryStructureController extends Controller
{
    public function index(Request $request): View
    {
        $structures = SalaryStructure::with(['employee', 'items'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('effective_from')
            ->paginate(10)
            ->withQueryString();

        return view('admin.hr.salary-structures.index', [
            'structures' => $structures,
            'totalStructures' => SalaryStructure::count(),
            'activeStructures' => SalaryStructure::where('status', 'active')->count(),
            'employeesWithoutStructure' => Employee::where('status', 'active')->whereDoesntHave('salaryStructures')->count(),
            'monthlyBasic' => (float) SalaryStructure::where('status', 'active')->sum('basic_salary'),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.hr.salary-structures.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $items] = $this->validated($request);

        $structure = DB::transaction(function () use ($data, $items) {
            $structure = SalaryStructure::create($data);
            $structure->items()->createMany($items);

            return $structure;
        });

        $structure->load('employee');

        ActivityLog::record($request, 'Payroll', 'Created salary structure', $structure->employee->name);

        return redirect()->route('admin.hr.salary-structures.index')
            ->with('status', 'Salary structure saved successfully.');
    }

    public function show(SalaryStructure $salary_structure): View
    {
        $salary_structure->load(['employee.department', 'employee.designation', 'items']);

        return view('admin.hr.salary-structures.show', ['structure' => $salary_structure]);
    }

    public function edit(SalaryStructure $salary_structure): View
    {
        $salary_structure->load('items');

        return view('admin.hr.salary-structures.edit', ['structure' => $salary_structure] + $this->formOptions());
    }

    public function update(Request $request, SalaryStructure $salary_structure): RedirectResponse
    {
        [$data, $items] = $this->validated($request);

        DB::transaction(function () use ($salary_structure, $data, $items) {
            $salary_structure->update($data);
            $salary_structure->items()->delete();
            $salary_structure->items()->createMany($items);
        });

        $salary_structure->load('employee');

        ActivityLog::record($request, 'Payroll', 'Updated salary structure', $salary_structure->employee->name);

        return redirect()->route('admin.hr.salary-structures.index')
            ->with('status', 'Salary structure updated successfully.');
    }

    public function destroy(Request $request, SalaryStructure $salary_structure): RedirectResponse
    {
        $label = $salary_structure->employee->name;
        $salary_structure->delete();

        ActivityLog::record($request, 'Payroll', 'Deleted salary structure', $label);

        return redirect()->route('admin.hr.salary-structures.index')
            ->with('status', 'Salary structure deleted successfully.');
    }

    /**
     * @return array{0: array, 1: array} Structure attributes and normalized additional items.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'housing_allowance' => ['required', 'numeric', 'min:0'],
            'transport_allowance' => ['required', 'numeric', 'min:0'],
            'food_allowance' => ['required', 'numeric', 'min:0'],
            'other_allowance' => ['required', 'numeric', 'min:0'],
            'fixed_deduction' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', 'in:active,inactive'],
            'items' => ['nullable', 'array'],
            'items.*.item_type' => ['required_with:items.*.name', 'nullable', 'in:allowance,deduction'],
            'items.*.name' => ['nullable', 'string', 'max:100'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_taxable' => ['nullable', 'boolean'],
        ]);

        $items = collect($data['items'] ?? [])
            ->filter(fn ($item) => filled($item['name'] ?? null))
            ->map(fn ($item) => [
                'item_type' => $item['item_type'] ?? 'allowance',
                'name' => $item['name'],
                'amount' => $item['amount'] ?? 0,
                'is_taxable' => (bool) ($item['is_taxable'] ?? false),
            ])
            ->values()
            ->all();

        unset($data['items']);

        return [$data, $items];
    }

    private function formOptions(): array
    {
        return [
            'employees' => Employee::orderBy('employee_code')->get(),
        ];
    }
}
