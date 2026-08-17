<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EndOfServiceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EndOfServiceController extends Controller
{
    public function index(Request $request): View
    {
        $records = EndOfServiceRecord::with(['employee', 'approver'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('termination_date')
            ->paginate(10)
            ->withQueryString();

        return view('admin.hr.eosb.index', [
            'records' => $records,
            'draftRecords' => EndOfServiceRecord::where('status', 'draft')->count(),
            'approvedRecords' => EndOfServiceRecord::where('status', 'approved')->count(),
            'paidRecords' => EndOfServiceRecord::where('status', 'paid')->count(),
            'totalFinalAmount' => (float) EndOfServiceRecord::sum('final_amount'),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.hr.eosb.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $record = EndOfServiceRecord::create($this->validated($request));
        $record->load('employee');

        ActivityLog::record($request, 'HR', 'Created EOSB record', $record->employee->name);

        return redirect()->route('admin.hr.eosb.index')
            ->with('status', 'End of service record saved successfully.');
    }

    public function show(EndOfServiceRecord $end_of_service_record): View
    {
        $end_of_service_record->load(['employee.department', 'employee.designation', 'approver']);

        return view('admin.hr.eosb.show', ['record' => $end_of_service_record]);
    }

    public function edit(EndOfServiceRecord $end_of_service_record): View
    {
        return view('admin.hr.eosb.edit', ['record' => $end_of_service_record] + $this->formOptions());
    }

    public function update(Request $request, EndOfServiceRecord $end_of_service_record): RedirectResponse
    {
        $end_of_service_record->update($this->validated($request));
        $end_of_service_record->load('employee');

        ActivityLog::record($request, 'HR', 'Updated EOSB record', $end_of_service_record->employee->name);

        return redirect()->route('admin.hr.eosb.index')
            ->with('status', 'End of service record updated successfully.');
    }

    public function destroy(Request $request, EndOfServiceRecord $end_of_service_record): RedirectResponse
    {
        $label = $end_of_service_record->employee->name;
        $end_of_service_record->delete();

        ActivityLog::record($request, 'HR', 'Deleted EOSB record', $label);

        return redirect()->route('admin.hr.eosb.index')
            ->with('status', 'End of service record deleted successfully.');
    }

    public function approve(Request $request, EndOfServiceRecord $end_of_service_record): RedirectResponse
    {
        $end_of_service_record->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        ActivityLog::record($request, 'HR', 'Approved EOSB record', $end_of_service_record->employee->name);

        return back()->with('status', 'End of service record approved.');
    }

    /**
     * Amounts stay manual in Phase 3; Saudi EOSB rules land in a later business-rule phase.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'termination_date' => ['required', 'date'],
            'service_years' => ['required', 'numeric', 'min:0', 'max:60'],
            'last_basic_salary' => ['required', 'numeric', 'min:0'],
            'eosb_amount' => ['required', 'numeric', 'min:0'],
            'leave_salary' => ['required', 'numeric', 'min:0'],
            'other_dues' => ['required', 'numeric', 'min:0'],
            'deductions' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,approved,paid'],
        ]);

        $data['final_amount'] = round(
            (float) $data['eosb_amount'] + (float) $data['leave_salary']
            + (float) $data['other_dues'] - (float) $data['deductions'],
            2
        );

        return $data;
    }

    private function formOptions(): array
    {
        return [
            'employees' => Employee::orderBy('employee_code')->get(),
            'statuses' => EndOfServiceRecord::STATUSES,
        ];
    }
}
