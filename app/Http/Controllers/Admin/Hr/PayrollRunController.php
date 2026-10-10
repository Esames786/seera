<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\OvertimeRecord;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\SalaryStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Payroll\PayrollAccountingService;
use App\Support\SaveAction;
use App\Support\Workspace\DocumentActivity;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollRunController extends Controller
{
    public function __construct(private readonly PayrollAccountingService $accounting) {}

    public function index(Request $request): View
    {
        $runs = PayrollRun::with(['branch', 'project'])
            ->withCount('items')
            ->when($request->filled('year'), fn ($q) => $q->where('payroll_year', $request->integer('year')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('payroll_year')
            ->orderByDesc('payroll_month')
            ->paginate(10)
            ->withQueryString();

        return view('admin.hr.payroll.index', [
            'runs' => $runs,
            'draftRuns' => PayrollRun::where('status', 'draft')->count(),
            'processedRuns' => PayrollRun::where('status', 'processed')->count(),
            'approvedRuns' => PayrollRun::whereIn('status', ['approved', 'paid'])->count(),
            'currentNet' => (float) PayrollRun::where('payroll_year', now()->year)->sum('net_amount'),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.hr.payroll.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['code'] = $this->nextCode($data['payroll_year'], $data['payroll_month']);
        $data['status'] = 'draft';

        $run = PayrollRun::create($data);

        ActivityLog::record($request, 'Payroll', 'Created payroll run', $run->code.' - '.$run->periodLabel());

        return redirect()->route('admin.hr.payroll.show', $run)
            ->with('status', 'Payroll run "'.$run->code.'" created. Process it to generate salary items.');
    }

    /**
     * Payroll run workspace: summary header, paged items with payslip links,
     * accounting state with Finance actions, activity. Read-only.
     */
    public function show(Request $request, PayrollRun $payroll_run): View
    {
        $payroll_run->load(['branch', 'project', 'approver', 'journalEntry.lines.account', 'reversalJournal']);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $user = $request->user();
        $items = $payroll_run->items()->with(['employee.department', 'employee.designation', 'employee.project'])
            ->orderBy('id')->paginate(25)->withQueryString()->fragment('items');
        $totals = $payroll_run->items()->selectRaw('COALESCE(SUM(basic_salary),0) as basic, COALESCE(SUM(total_allowances),0) as allowances, COALESCE(SUM(overtime_amount),0) as overtime, COALESCE(SUM(total_deductions),0) as deductions, COALESCE(SUM(gross_amount),0) as gross, COALESCE(SUM(net_amount),0) as net, COUNT(*) as employees')->first();

        return view('admin.hr.payroll.show', [
            'run' => $payroll_run,
            'items' => $items,
            'totals' => $totals,
            'canProcess' => ! $payroll_run->isApproved() && $user->hasPermission('Payroll', 'create'),
            'canEdit' => ! $payroll_run->isApproved() && $user->hasPermission('Payroll', 'edit'),
            'canApprove' => $payroll_run->status === 'processed' && $user->hasPermission('Payroll', 'approve'),
            'canPost' => $payroll_run->isApproved() && ! $payroll_run->reversal_journal_id && $payroll_run->accounting_status !== PayrollRun::ACCOUNTING_POSTED && $user->hasPermission('Payroll', 'post'),
            'canReverse' => $payroll_run->accounting_status === PayrollRun::ACCOUNTING_POSTED && $user->hasPermission('Payroll', 'post') && $user->hasPermission('Payroll', 'approve'),
            'canViewJournal' => $user->hasPermission('Journal Entries', 'view'),
            'canViewEmployee' => $user->hasPermission('HR', 'view'),
            'activity' => DocumentActivity::latest($user, [$payroll_run->code], ['Payroll', 'Accounting']),
        ]);
    }

    /** Finance: post the approved run to accounting, or retry after a failure. Idempotent. */
    public function post(Request $request, PayrollRun $payroll_run): RedirectResponse
    {
        if (! $payroll_run->isApproved()) {
            return back()->withErrors(['posting' => 'Only an approved payroll run can be posted to accounting.']);
        }
        try {
            $this->accounting->attempt($payroll_run->id, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
        $payroll_run->refresh();

        return redirect()->route('admin.hr.payroll.show', $payroll_run)
            ->with('status', $payroll_run->accounting_status === PayrollRun::ACCOUNTING_POSTED
                ? 'Payroll accounting posted: '.$payroll_run->journalEntry?->journal_number.'.'
                : 'Payroll accounting checked: '.$payroll_run->accountingLabel().'. Existing journals are never duplicated.');
    }

    /** Finance: reverse the posted payroll journal; the run stays approved and immutable. */
    public function reverse(Request $request, PayrollRun $payroll_run): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('Payroll', 'post') && $request->user()->hasPermission('Payroll', 'approve'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Give the reason for reversing; it is kept on the run.']);
        try {
            $this->accounting->reverse($payroll_run->id, trim($data['reason']), $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
        ActivityLog::record($request, 'Payroll', 'Reversed payroll accounting', $payroll_run->code.': '.trim($data['reason']));

        return redirect()->route('admin.hr.payroll.show', $payroll_run)
            ->with('status', 'Reversing journal posted; the original payroll journal and the approved run stay in history.');
    }

    /** Printable payslip from the stored payroll row (historical values, never recalculated). */
    public function payslip(Request $request, PayrollRun $payroll_run, int $item): View
    {
        // The item must belong to this run and be visible to the viewer's scope (payroll_run_items are scoped through the employee).
        $row = $payroll_run->items()->with(['employee.department', 'employee.designation', 'employee.project', 'employee.branch'])->findOrFail($item);

        return view('admin.hr.payroll.payslip', [
            'run' => $payroll_run,
            'item' => $row,
            'company' => CompanyProfile::query()->first(),
            'generatedAt' => now(),
            'returnTo' => SaveAction::returnTo($request) ?? route('admin.hr.payroll.show', $payroll_run, false),
        ]);
    }

    public function edit(PayrollRun $payroll_run): View
    {
        return view('admin.hr.payroll.edit', ['run' => $payroll_run] + $this->formOptions());
    }

    public function update(Request $request, PayrollRun $payroll_run): RedirectResponse
    {
        if ($payroll_run->isApproved() || $payroll_run->isFinanciallyLocked()) {
            return back()->withErrors(['payroll' => 'An approved payroll run can no longer be edited.']);
        }

        $payroll_run->update($this->validated($request));

        ActivityLog::record($request, 'Payroll', 'Updated payroll run', $payroll_run->code);

        return redirect()->route('admin.hr.payroll.show', $payroll_run)
            ->with('status', 'Payroll run updated successfully.');
    }

    public function destroy(Request $request, PayrollRun $payroll_run): RedirectResponse
    {
        if ($payroll_run->isApproved() || $payroll_run->isFinanciallyLocked()) {
            return back()->withErrors(['payroll' => 'An approved payroll run cannot be deleted.']);
        }

        $code = $payroll_run->code;
        $payroll_run->delete();

        ActivityLog::record($request, 'Payroll', 'Deleted payroll run', $code);

        return redirect()->route('admin.hr.payroll.index')
            ->with('status', 'Payroll run "'.$code.'" deleted successfully.');
    }

    /**
     * Phase 3 rule: net = basic + allowances + approved overtime - deductions.
     */
    public function process(Request $request, PayrollRun $payroll_run): RedirectResponse
    {
        if ($payroll_run->isApproved() || $payroll_run->isFinanciallyLocked()) {
            return back()->withErrors(['payroll' => 'An approved payroll run cannot be reprocessed.']);
        }

        $employees = Employee::where('status', 'active')
            ->when($payroll_run->branch_id, fn ($q) => $q->where('branch_id', $payroll_run->branch_id))
            ->when($payroll_run->project_id, fn ($q) => $q->where('project_id', $payroll_run->project_id))
            ->get();

        if ($employees->isEmpty()) {
            return back()->withErrors(['payroll' => 'No active employees match this payroll run scope.']);
        }

        DB::transaction(function () use ($payroll_run, $employees) {
            $payroll_run->items()->delete();

            $gross = 0.0;
            $deductions = 0.0;
            $net = 0.0;

            foreach ($employees as $employee) {
                $item = $this->buildItem($payroll_run, $employee);

                $payroll_run->items()->create($item);

                $gross += $item['gross_amount'];
                $deductions += $item['total_deductions'];
                $net += $item['net_amount'];
            }

            $payroll_run->update([
                'total_employees' => $employees->count(),
                'gross_amount' => round($gross, 2),
                'total_deductions' => round($deductions, 2),
                'net_amount' => round($net, 2),
                'status' => 'processed',
                'processed_at' => now(),
            ]);
        });

        ActivityLog::record($request, 'Payroll', 'Processed payroll run', $payroll_run->code.' - '.$employees->count().' employees');

        return redirect()->route('admin.hr.payroll.show', $payroll_run)
            ->with('status', 'Payroll processed for '.$employees->count().' employees.');
    }

    public function approve(Request $request, PayrollRun $payroll_run): RedirectResponse
    {
        $approved = DB::transaction(function () use ($request, $payroll_run) {
            // Re-check under lock: two Approve clicks or a race with Process cannot approve twice.
            $run = PayrollRun::whereKey($payroll_run->id)->lockForUpdate()->firstOrFail();
            if ($run->status !== 'processed') {
                return false;
            }
            $run->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);

            return true;
        });
        if (! $approved) {
            return back()->withErrors(['payroll' => 'Only a processed payroll run can be approved.']);
        }

        ActivityLog::record($request, 'Payroll', 'Approved payroll run', $payroll_run->code);

        // Accounting runs AFTER the approval has committed and never undoes it: a failure
        // leaves the run approved with the reason for Finance, who can retry.
        try {
            $this->accounting->attempt($payroll_run->id, $request->user()->id);
        } catch (ValidationException) {
            // Recorded on the run as posting_error; shown in the Accounting section.
        }
        $payroll_run->refresh();

        return redirect()->route('admin.hr.payroll.show', $payroll_run)
            ->with('status', 'Payroll run "'.$payroll_run->code.'" approved. Accounting: '.$payroll_run->accountingLabel().'.');
    }

    private function buildItem(PayrollRun $run, Employee $employee): array
    {
        $structure = SalaryStructure::with('items')
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $run->period_end)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $run->period_start))
            ->orderByDesc('effective_from')
            ->first();

        // Without a structure the employee's own profile is used for the allowances
        // too, not the basic salary alone: dropping them silently understated the
        // pay of anyone whose structure had not been created yet (client feedback FR-04).
        $basic = (float) ($structure?->basic_salary ?? $employee->basic_salary);
        $allowances = $structure ? (float) $structure->totalAllowances() : $employee->defaultAllowanceTotal();
        $deductions = (float) ($structure?->totalDeductions() ?? 0);

        $overtime = (float) OvertimeRecord::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('overtime_date', '>=', $run->period_start)
            ->whereDate('overtime_date', '<=', $run->period_end)
            ->sum('amount');

        $attendance = $employee->attendanceRecords()
            ->whereDate('attendance_date', '>=', $run->period_start)
            ->whereDate('attendance_date', '<=', $run->period_end);

        $gross = $basic + $allowances + $overtime;

        return [
            'employee_id' => $employee->id,
            'basic_salary' => round($basic, 2),
            'total_allowances' => round($allowances, 2),
            'overtime_amount' => round($overtime, 2),
            'total_deductions' => round($deductions, 2),
            'gross_amount' => round($gross, 2),
            'net_amount' => round($gross - $deductions, 2),
            'present_days' => (clone $attendance)->whereIn('status', ['present', 'late'])->count(),
            'leave_days' => (clone $attendance)->where('status', 'leave')->count(),
            'remarks' => $structure ? null : 'No salary structure - the employee profile basic salary and allowances were used.',
        ];
    }

    private function nextCode(int $year, int $month): string
    {
        $prefix = sprintf('PR-%04d%02d', $year, $month);
        return app(\App\Services\DocumentNumberService::class)
            ->next('payroll-'.$year.'-'.$month, $prefix.'-', 'payroll_runs', 'code', 2);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'payroll_month' => ['required', 'integer', 'min:1', 'max:12'],
            'payroll_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $month = Carbon::create($data['payroll_year'], $data['payroll_month'], 1);

        $data['period_start'] = $data['period_start'] ?? $month->copy()->startOfMonth()->toDateString();
        $data['period_end'] = $data['period_end'] ?? $month->copy()->endOfMonth()->toDateString();

        return $data;
    }

    private function formOptions(): array
    {
        return [
            'branches' => Branch::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'months' => collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => Carbon::create(null, $m, 1)->format('F')])->all(),
            'years' => range(now()->year - 2, now()->year + 1),
            'statuses' => PayrollRun::STATUSES,
        ];
    }
}
