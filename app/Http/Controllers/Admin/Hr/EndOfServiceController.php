<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EndOfServiceRecord;
use App\Services\Hr\GratuityCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EndOfServiceController extends Controller
{
    public function __construct(private readonly GratuityCalculator $gratuity) {}

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
        abort_unless($end_of_service_record->isEditable(), 403, 'An approved end-of-service record is read-only.');

        return view('admin.hr.eosb.edit', ['record' => $end_of_service_record] + $this->formOptions());
    }

    public function update(Request $request, EndOfServiceRecord $end_of_service_record): RedirectResponse
    {
        if (! $end_of_service_record->isEditable()) {
            return back()->withErrors(['eosb' => 'An approved end-of-service record is read-only.']);
        }

        $end_of_service_record->update($this->validated($request));
        $end_of_service_record->load('employee');

        ActivityLog::record($request, 'HR', 'Updated EOSB record', $end_of_service_record->employee->name);

        return redirect()->route('admin.hr.eosb.index')
            ->with('status', 'End of service record updated successfully.');
    }

    public function destroy(Request $request, EndOfServiceRecord $end_of_service_record): RedirectResponse
    {
        if (! $end_of_service_record->isEditable()) {
            return back()->withErrors(['eosb' => 'An approved end-of-service record cannot be deleted.']);
        }

        $label = $end_of_service_record->employee->name;
        $end_of_service_record->delete();

        ActivityLog::record($request, 'HR', 'Deleted EOSB record', $label);

        return redirect()->route('admin.hr.eosb.index')
            ->with('status', 'End of service record deleted successfully.');
    }

    public function approve(Request $request, EndOfServiceRecord $end_of_service_record): RedirectResponse
    {
        DB::transaction(function () use ($request, $end_of_service_record) {
            $record = EndOfServiceRecord::whereKey($end_of_service_record->id)->lockForUpdate()->firstOrFail();
            if (! $record->isEditable()) {
                throw ValidationException::withMessages(['eosb' => 'Only a draft end-of-service record can be approved.']);
            }
            $record->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        });

        ActivityLog::record($request, 'HR', 'Approved EOSB record', $end_of_service_record->employee->name);

        return back()->with('status', 'End of service record approved.');
    }

    /**
     * Gratuity follows the Saudi rules unless HR ticks the manual override.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'termination_date' => ['required', 'date'],
            'termination_reason' => ['required', 'in:'.implode(',', GratuityCalculator::REASONS)],
            'service_years' => ['required', 'numeric', 'min:0', 'max:60'],
            'last_basic_salary' => ['required', 'numeric', 'min:0'],
            'eosb_amount' => ['nullable', 'numeric', 'min:0'],
            'manual_override' => ['nullable', 'boolean'],
            'leave_salary' => ['required', 'numeric', 'min:0'],
            'other_dues' => ['required', 'numeric', 'min:0'],
            'deductions' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string'],
        ]);

        $override = $request->boolean('manual_override');

        $calculation = $this->gratuity->calculate(
            (float) $data['last_basic_salary'],
            (float) $data['service_years'],
            $data['termination_reason']
        );

        $data['manual_override'] = $override;
        $data['status'] = 'draft';
        $data['gratuity_before_adjustment'] = $calculation['base'];
        $data['entitlement_percentage'] = $calculation['percentage'];
        $data['eosb_amount'] = $override
            ? (float) ($data['eosb_amount'] ?? 0)
            : $calculation['gratuity'];

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
            'reasons' => GratuityCalculator::reasonLabels(),
        ];
    }
}
