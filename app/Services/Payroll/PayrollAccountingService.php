<?php

namespace App\Services\Payroll;

use App\Models\ActivityLog;
use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Payroll → GL, Phase 1. Runs only after the HR approval has committed.
 *
 * One approved payroll run produces at most one original journal
 * (Dr salary expense per employee with the employee's project / cost center,
 * Cr payroll payable for the net, Cr deduction liability for deductions).
 * Accounts come from the Finance-configured "Payroll / Payroll Approved"
 * posting rule; nothing is guessed from names or codes. The run row is the
 * mutex, so a retry or a concurrent request finds the journal already linked.
 * Approval is never rolled back because accounting failed: the error is kept
 * for Finance and Retry is idempotent. Posting ends with the payroll payable;
 * salary payment is a later phase.
 */
class PayrollAccountingService
{
    public const MODULE = 'Payroll';

    public const EVENT = 'Payroll Approved';

    public function __construct(private readonly PostingService $posting) {}

    /** Post (or retry posting) the approved run. Safe to call any number of times. */
    public function attempt(int $runId, ?int $actorId): void
    {
        try {
            DB::transaction(function () use ($runId, $actorId) {
                $run = PayrollRun::whereKey($runId)->lockForUpdate()->firstOrFail();
                if (! $run->isApproved()) {
                    $this->invalid('Only an approved payroll run can be posted to accounting.');
                }
                if ($run->reversal_journal_id) {
                    return; // reversed history: never re-posted automatically
                }
                if ($run->journal_entry_id) {
                    $this->syncLocked($run);

                    return;
                }
                // A journal already written for this run (an earlier attempt that lost its link) is reused, never duplicated.
                $existing = JournalEntry::withoutGlobalScopes()->where('source_module', self::MODULE)->where('source_id', $run->id)
                    ->whereIn('status', ['draft', 'approved', 'posted'])->orderBy('id')->first();
                if ($existing) {
                    $run->update(['journal_entry_id' => $existing->id, 'posting_error' => null]);
                    $this->syncLocked($run);

                    return;
                }

                [$rule, $expense, $payable, $deduction] = $this->mapping($run);
                $lines = $this->lines($run, $expense, $payable, $deduction);
                $entry = $this->posting->postPayrollRun($run, $lines, $actorId);
                $run->update(['journal_entry_id' => $entry->id, 'posting_error' => null]);
                $this->syncLocked($run);
                $this->log($run, $actorId, 'Payroll accounting entry created', $entry->journal_number.' ('.$entry->status.')');
            }, 3);
        } catch (Throwable $error) {
            $message = $error instanceof ValidationException
                ? implode(' ', array_merge(...array_values($error->errors())))
                : 'Accounting could not complete. Finance can review the Payroll posting rule and retry; the approval is retained.';
            Log::warning('Payroll accounting attempt failed', ['payroll_run_id' => $runId, 'exception_class' => $error::class]);
            DB::transaction(function () use ($runId, $actorId, $message) {
                $run = PayrollRun::whereKey($runId)->lockForUpdate()->firstOrFail();
                // A competing attempt may already have succeeded: never overwrite a linked journal with a failure.
                if ($run->isApproved() && ! $run->journal_entry_id && ! $run->reversal_journal_id) {
                    $run->update(['accounting_status' => PayrollRun::ACCOUNTING_FAILED, 'posting_error' => mb_substr($message, 0, 2000)]);
                    $this->log($run, $actorId, 'Payroll accounting pending', $message);
                }
            }, 3);
            if ($error instanceof ValidationException) {
                throw $error;
            }
        }
    }

    /**
     * Resolve the Finance-configured mapping or refuse with a readable message.
     *
     * @return array{0: AutomaticPostingRule, 1: ChartOfAccount, 2: ChartOfAccount, 3: ?ChartOfAccount}
     */
    public function mapping(PayrollRun $run): array
    {
        $rule = AutomaticPostingRule::where('source_module', self::MODULE)->where('trigger_event', self::EVENT)->where('status', 'active')->first();
        if (! $rule) {
            $this->invalid('No active automatic posting rule "Payroll / Payroll Approved" is configured. Finance must create it with the salary expense and payroll payable accounts before payroll can be posted.');
        }
        $expense = $this->account($rule->debit_account_id, 'expense', 'Salary / payroll expense (debit account on the Payroll posting rule)');
        $payable = $this->account($rule->credit_account_id, 'liability', 'Payroll payable (credit account on the Payroll posting rule)');
        $deduction = null;
        if ((float) $run->total_deductions > 0) {
            if (! $rule->deduction_account_id) {
                $this->invalid('Payroll deductions of SAR '.number_format((float) $run->total_deductions, 2).' have no accounting account configured. Set the deduction liability account on the Payroll posting rule before posting.');
            }
            $deduction = $this->account($rule->deduction_account_id, 'liability', 'Payroll deduction liability (deduction account on the Payroll posting rule)');
        }
        $vatIds = $this->posting->vatAccountIds();
        foreach ([$expense, $payable, $deduction] as $account) {
            if ($account && in_array($account->id, $vatIds, true)) {
                $this->invalid('Payroll must not post to a VAT control account ('.$account->label().').');
            }
        }

        return [$rule, $expense, $payable, $deduction];
    }

    /**
     * Journal lines from the stored items: one expense line per employee carrying
     * the employee's current project (the accepted rule) and its single active
     * project cost center when one exists; one payable line for the net; one
     * deduction line for the deductions. Debits = credits by construction
     * (gross = net + deductions on every item).
     *
     * @return array<int, array<string, mixed>>
     */
    public function lines(PayrollRun $run, ChartOfAccount $expense, ChartOfAccount $payable, ?ChartOfAccount $deduction): array
    {
        $items = $run->items()->with(['employee:id,employee_code,first_name,last_name,project_id,site_id,department_id'])->orderBy('id')->get();
        if ($items->isEmpty()) {
            $this->invalid('This payroll run has no employee rows to post. Process it first.');
        }
        $centers = CostCenter::where('type', 'project')->where('status', 'active')->get()->groupBy('linked_id');
        $lines = [];
        $net = 0.0;
        $deductions = 0.0;
        foreach ($items as $item) {
            $employee = $item->employee;
            $gross = round((float) $item->gross_amount, 2);
            if (abs($gross - ((float) $item->net_amount + (float) $item->total_deductions)) >= 0.01) {
                $this->invalid('Payroll row for '.($employee?->name ?? 'employee #'.$item->employee_id).' does not reconcile (gross ≠ net + deductions). Reprocess before approval.');
            }
            $projectCenters = $employee?->project_id ? $centers->get($employee->project_id, collect()) : collect();
            if ($expense->cost_center_required && $projectCenters->count() !== 1) {
                $this->invalid('Account '.$expense->label().' requires a cost center, but '.($employee?->name ?? 'employee #'.$item->employee_id).' has no single active project cost center. Configure the cost center or map another account.');
            }
            $lines[] = [
                'chart_of_account_id' => $expense->id,
                'description' => 'Salary '.$run->periodLabel().' - '.($employee ? $employee->employee_code.' '.$employee->name : 'employee #'.$item->employee_id),
                'debit' => $gross,
                'credit' => 0,
                'project_id' => $employee?->project_id,
                'site_id' => $employee?->site_id,
                'cost_center_id' => $projectCenters->count() === 1 ? $projectCenters->first()->id : null,
            ];
            $net += (float) $item->net_amount;
            $deductions += (float) $item->total_deductions;
        }
        $lines[] = [
            'chart_of_account_id' => $payable->id,
            'description' => 'Payroll payable '.$run->code.' ('.$run->periodLabel().')',
            'debit' => 0,
            'credit' => round($net, 2),
            'project_id' => $run->project_id,
            'cost_center_id' => null,
        ];
        if (round($deductions, 2) > 0) {
            $lines[] = [
                'chart_of_account_id' => $deduction->id,
                'description' => 'Payroll deductions '.$run->code.' ('.$run->periodLabel().')',
                'debit' => 0,
                'credit' => round($deductions, 2),
                'project_id' => $run->project_id,
                'cost_center_id' => null,
            ];
        }

        return $lines;
    }

    /** Finance-only: reverse the posted journal; the run stays approved and immutable, its accounting is marked reversed. */
    public function reverse(int $runId, string $reason, int $actorId): void
    {
        DB::transaction(function () use ($runId, $reason, $actorId) {
            $run = PayrollRun::whereKey($runId)->lockForUpdate()->firstOrFail();
            if ($run->reversal_journal_id) {
                if ($run->reversal_reason === $reason) {
                    return; // same request repeated
                }
                $this->invalid('This payroll posting has already been reversed.');
            }
            if ($run->accounting_status !== PayrollRun::ACCOUNTING_POSTED || ! $run->journal_entry_id) {
                $this->invalid('Only a payroll run whose journal is posted can be reversed.');
            }
            $entry = JournalEntry::withoutGlobalScopes()->whereKey($run->journal_entry_id)->lockForUpdate()->firstOrFail();
            if ($entry->status !== 'posted') {
                $this->invalid('The payroll journal is not posted; nothing to reverse.');
            }
            $reversal = $this->posting->reverseEntry($entry, 'Reverse payroll '.$run->code.': '.$reason, $actorId);
            $run->update([
                'accounting_status' => PayrollRun::ACCOUNTING_REVERSED,
                'reversal_journal_id' => $reversal->id,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'posting_error' => null,
            ]);
            $this->log($run, $actorId, 'Payroll accounting reversed', $reversal->journal_number.': '.$reason);
        }, 3);
    }

    /** Called after a journal status change commits (review journal posted by Finance). */
    public function sync(int $runId): void
    {
        DB::transaction(function () use ($runId) {
            $run = PayrollRun::whereKey($runId)->lockForUpdate()->first();
            if ($run && $run->journal_entry_id && ! $run->reversal_journal_id) {
                $this->syncLocked($run);
            }
        }, 3);
    }

    private function syncLocked(PayrollRun $run): void
    {
        $entry = JournalEntry::withoutGlobalScopes()->find($run->journal_entry_id);
        $posted = $entry?->status === 'posted';
        $run->update([
            'accounting_status' => $posted ? PayrollRun::ACCOUNTING_POSTED : ($entry && $entry->status !== 'cancelled' ? PayrollRun::ACCOUNTING_REVIEW : PayrollRun::ACCOUNTING_FAILED),
            'posted_at' => $posted ? $entry->posted_at : null,
            'posting_error' => $entry && $entry->status === 'cancelled' ? 'The payroll journal '.$entry->journal_number.' was cancelled in Accounting; Finance must review.' : null,
        ]);
    }

    private function account(?int $id, string $type, string $purpose): ChartOfAccount
    {
        $account = $id ? ChartOfAccount::find($id) : null;
        if (! $account || $account->status !== 'active') {
            $this->invalid($purpose.' is not configured or not active. Choose an active account on the Payroll posting rule.');
        }
        if ($account->account_type !== $type) {
            $this->invalid($purpose.' must be a '.$type.' account; '.$account->label().' is '.$account->account_type.'.');
        }

        return $account;
    }

    private function log(PayrollRun $run, ?int $actorId, string $action, string $detail): void
    {
        ActivityLog::create(['user_id' => $actorId, 'user_name' => 'Accounting', 'module' => 'Payroll',
            'action' => $action, 'description' => $run->code.' - '.$detail, 'status' => 'success']);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['posting' => $message]);
    }
}
