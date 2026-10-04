<?php

namespace App\Services\SiteExpenses;

use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\JournalEntry;
use App\Models\SiteExpense;
use App\Models\SupplierBill;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Accounting orchestration runs only AFTER the approval transaction commits. */
class SiteExpenseAccountingService
{
    public function __construct(private readonly PostingService $posting) {}

    public function attempt(int $id, ?int $actorId): void
    {
        try {
            DB::transaction(function () use ($id, $actorId) {
                // Same first lock for final approval, retry and bill creation.
                $expense = SiteExpense::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->firstOrFail();
                if ($expense->status !== 'approved_pending_posting') {
                    return;
                }
                if (! $expense->approvals()->where('status', 'approved')->exists()) {
                    $this->invalid('All required approvals must complete before accounting.');
                }
                if ($expense->payment_type === 'Supplier Credit' && $expense->supplier_bill_id) {
                    $this->syncLocked($expense);

                    return;
                }
                if ($expense->journal_entry_id) {
                    $this->syncLocked($expense);

                    return;
                }
                [$account, $costCenter] = $this->mapping($expense);
                if ($expense->payment_type === 'Supplier Credit') {
                    $supplier = $expense->supplier;
                    if (! $supplier || $supplier->status !== 'active') {
                        $this->invalid('Choose an active supplier before Finance can generate the bill.');
                    }
                    $bill = SupplierBill::create([
                        'approval_mode' => 'runtime', 'requested_by' => $expense->submitted_by_user_id,
                        'site_expense_id' => $expense->id, 'supplier_id' => $supplier->id,
                        'bill_number' => $expense->expense_number, 'bill_date' => $expense->expense_date,
                        'reference_number' => $expense->reference_number,
                        'project_id' => $expense->project_id, 'site_id' => $expense->site_id,
                        'cost_center_id' => $costCenter?->id, 'taxable_amount' => $expense->taxable_amount,
                        'vat_rate' => $expense->vat_rate, 'vat_amount' => $expense->vat_amount,
                        'total_amount' => $expense->total_amount, 'paid_amount' => 0,
                        'balance_amount' => $expense->total_amount, 'status' => 'draft',
                        'notes' => 'Approved Site Expense '.$expense->expense_number.'. Finance approval/posting required.',
                    ]);
                    $bill->lines()->create([
                        'description' => $expense->description, 'expense_category_id' => $expense->expense_category_id,
                        'chart_of_account_id' => $account->id, 'quantity' => 1, 'unit_price' => $expense->taxable_amount,
                        'taxable_amount' => $expense->taxable_amount, 'vat_rate' => $expense->vat_rate,
                        'vat_amount' => $expense->vat_amount, 'total_amount' => $expense->total_amount,
                        'cost_center_id' => $costCenter?->id,
                    ]);
                    $expense->update(['supplier_bill_id' => $bill->id, 'posting_error' => null]);
                    $this->log($expense, $actorId, 'Supplier bill created', 'Draft '.$bill->bill_number.' requires Finance approval/posting.');

                    return;
                }
                $credit = $this->creditAccount($expense);
                $entry = $this->posting->postSiteExpense($expense, $account, $credit, $costCenter?->id, $actorId);
                $expense->update(['journal_entry_id' => $entry->id, 'posting_error' => null]);
                $this->syncLocked($expense);
                $this->log($expense, $actorId, 'Accounting entry created', $entry->journal_number.' ('.$entry->status.')');
            }, 3);
        } catch (Throwable $error) {
            // Never reveal SQL, paths or credentials to a site user. Expected
            // configuration errors are retained for authorized Finance only.
            $message = $error instanceof ValidationException
                ? implode(' ', array_merge(...array_values($error->errors())))
                : 'Accounting could not complete. Finance can review configuration and retry; approval is retained.';
            Log::warning('Site Expense accounting attempt failed', ['expense_id' => $id, 'exception_class' => $error::class]);
            DB::transaction(function () use ($id, $actorId, $message) {
                $expense = SiteExpense::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->firstOrFail();
                // A competing retry may already have succeeded.
                if ($expense->status === 'approved_pending_posting' && ! $expense->journal_entry_id && ! $expense->supplier_bill_id) {
                    $expense->update(['posting_error' => mb_substr($message, 0, 2000)]);
                    $this->log($expense, $actorId, 'Accounting pending', $message);
                }
            }, 3);
        }
    }

    public function mapping(SiteExpense $expense): array
    {
        $category = $expense->category;
        $account = ChartOfAccount::find($category?->chart_of_account_id);
        if (! $account || $account->status !== 'active' || $account->account_type !== 'expense') {
            $this->invalid("Expense category '".($category?->name ?? 'Unknown')."' has no active expense accounting account configured.");
        }
        $centers = CostCenter::where('type', 'project')->where('linked_id', $expense->project_id)->where('status', 'active')->get();
        if ($centers->count() > 1 || ($account->cost_center_required && $centers->count() !== 1)) {
            $this->invalid('Configure one active Project cost center before posting this expense.');
        }

        return [$account, $centers->first()];
    }

    /** One full settlement per expense; partial and batch reimbursements are not implemented. */
    public function settle(int $id, int $accountId, int $actorId): void
    {
        DB::transaction(function () use ($id, $accountId, $actorId) {
            $expense = SiteExpense::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($expense->settlement_journal_id) {
                if ((int) $expense->settlement_account_id !== $accountId) {
                    $this->invalid('This expense has already been reimbursed from another account.');
                }

                return;
            }
            if ($expense->payment_type !== 'Employee Reimbursement' || $expense->status !== 'posted' || ! $expense->accounting_posted) {
                $this->invalid('Only a posted, unpaid employee reimbursement can be settled.');
            }
            $bank = self::paymentAccounts('Cash')->whereKey($accountId)->first() ?? self::paymentAccounts('Bank')->whereKey($accountId)->first();
            if (! $bank) {
                $this->invalid('Select an active permitted Cash or Bank account.');
            }
            $entry = $this->posting->settleSiteExpense($expense, $bank, $actorId);
            $expense->update(['settlement_journal_id' => $entry->id, 'settlement_account_id' => $bank->id, 'settled_at' => now()]);
            $this->log($expense, $actorId, 'Employee reimbursed', $entry->journal_number);
        }, 3);
    }

    public function reverse(int $id, string $reason, int $actorId): void
    {
        DB::transaction(function () use ($id, $reason, $actorId) {
            $expense = SiteExpense::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($expense->status === 'reversed' && $expense->reversal_reason === $reason) {
                return;
            }
            if ($expense->status !== 'posted' || ! $expense->accounting_posted || $expense->supplier_bill_id || $expense->settlement_journal_id) {
                $this->invalid('Only an unsettled posted Cash/Bank/Reimbursement expense can be reversed here. Supplier Credit follows the linked bill; settled reimbursements need a separate Finance correction.');
            }
            $entry = JournalEntry::withoutGlobalScopes()->whereKey($expense->journal_entry_id)->lockForUpdate()->firstOrFail();
            $this->posting->withdrawVat('Site Expense', $expense->id);
            $reversal = $this->posting->reverseEntry($entry, 'Reverse '.$expense->expense_number.': '.$reason, $actorId);
            $expense->update(['status' => 'reversed', 'accounting_posted' => false, 'reversed_at' => now(), 'reversal_journal_id' => $reversal->id, 'reversal_reason' => $reason]);
            $this->log($expense, $actorId, 'Reversed expense', $reversal->journal_number.': '.$reason);
        }, 3);
    }

    public static function paymentAccounts(string $type)
    {
        // Match the existing Finance payment-account catalogue; no arbitrary GL.
        return ChartOfAccount::where('status', 'active')->where('account_type', 'asset')
            ->where('account_code', $type === 'Cash' ? PostingService::CASH : PostingService::BANK);
    }

    private function creditAccount(SiteExpense $expense): ChartOfAccount
    {
        if ($expense->payment_type === 'Employee Reimbursement') {
            if (! $expense->employee_id) {
                $this->invalid('Employee reimbursement requires the submitter to have a linked employee profile.');
            }
            $account = ChartOfAccount::where('account_code', '2310')->where('account_type', 'liability')
                ->where('account_name', 'Employee Expense Reimbursement Payable')->where('status', 'active')->first();
            if (! $account) {
                $this->invalid('Configure the dedicated Employee Expense Reimbursement Payable account (2310); an existing conflicting account was not overwritten.');
            }

            return $account;
        }
        if (! in_array($expense->payment_type, ['Cash', 'Bank'], true)) {
            $this->invalid('Unsupported Site Expense payment type.');
        }
        $account = self::paymentAccounts($expense->payment_type)->find($expense->payment_account_id);
        if (! $account) {
            $this->invalid('The selected payment account is not an active permitted '.$expense->payment_type.' account.');
        }

        return $account;
    }

    /** Called after Finance commits, never during a read-only GET. */
    public function sync(int $id): void
    {
        DB::transaction(function () use ($id) {
            $expense = SiteExpense::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->first();
            if ($expense && in_array($expense->status, ['posted', 'approved_pending_posting'], true)) {
                $this->syncLocked($expense);
            }
        }, 3);
    }

    private function syncLocked(SiteExpense $expense): void
    {
        $bill = $expense->supplier_bill_id ? SupplierBill::withoutGlobalScopes()->find($expense->supplier_bill_id) : null;
        $journalId = $bill ? $bill->journal_entry_id : $expense->journal_entry_id;
        $entry = $journalId ? JournalEntry::withoutGlobalScopes()->find($journalId) : null;
        $posted = $entry?->status === 'posted' && (! $bill || ! in_array($bill->status, ['draft', 'cancelled'], true));
        $expense->update(['accounting_posted' => $posted, 'status' => $posted ? 'posted' : 'approved_pending_posting',
            'posted_at' => $posted ? $entry->posted_at : null, 'posting_error' => null]);
    }

    private function log(SiteExpense $expense, ?int $actorId, string $action, string $detail): void
    {
        ActivityLog::create(['user_id' => $actorId, 'user_name' => 'Accounting', 'module' => 'Site Expenses',
            'action' => $action, 'description' => '[SiteExpense #'.$expense->id.'] '.$detail, 'status' => 'success']);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['posting' => $message]);
    }
}
