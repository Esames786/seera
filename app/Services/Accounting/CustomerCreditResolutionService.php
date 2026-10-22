<?php

namespace App\Services\Accounting;

use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerAdjustmentNote;
use App\Models\CustomerCreditAllocation;
use App\Models\CustomerCreditRefund;
use App\Models\CustomerInvoice;
use App\Models\User;
use App\Support\SettlementReplay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What happens to the unapplied credit of a posted customer credit note.
 *
 * Available credit = the note's unapplied amount (fixed when the note was
 * posted) − active allocations − active refunds. Allocation to another open
 * invoice of the same customer is an AR sub-ledger event (no journal: the
 * credit note already credited AR and the target invoice already debited
 * it); a refund posts Dr Accounts Receivable / Cr bank or cash through
 * PostingService and never touches revenue, VAT or the ZATCA document. Lock
 * order: credit note first, then the target invoice (allocation) or nothing
 * else (refund). Reversals keep the original rows.
 */
final class CustomerCreditResolutionService
{
    public function __construct(private readonly PostingService $posting) {}

    /** The single authoritative figure, from persisted rows only. */
    public function availableCredit(CustomerAdjustmentNote $note): float
    {
        if (! $note->isCredit() || ! $note->isPosted()) {
            return 0.0;
        }
        $allocated = (float) CustomerCreditAllocation::query()->where('customer_adjustment_note_id', $note->id)->whereNull('reversed_at')->sum('amount');
        $refunded = (float) CustomerCreditRefund::query()->where('customer_adjustment_note_id', $note->id)->whereNull('reversed_at')->sum('amount');

        return round(max((float) $note->unapplied_amount - $allocated - $refunded, 0), 2);
    }

    /**
     * Credit, applied, refunded, available and a register status for one credit note.
     *
     * @return array{credit: float, applied: float, refunded: float, available: float, status: string}
     */
    public function summary(CustomerAdjustmentNote $note): array
    {
        $credit = $note->isCredit() && $note->isPosted() ? (float) $note->unapplied_amount : 0.0;
        $applied = (float) CustomerCreditAllocation::query()->where('customer_adjustment_note_id', $note->id)->whereNull('reversed_at')->sum('amount');
        $refunded = (float) CustomerCreditRefund::query()->where('customer_adjustment_note_id', $note->id)->whereNull('reversed_at')->sum('amount');
        $available = round(max($credit - $applied - $refunded, 0), 2);

        return ['credit' => round($credit, 2), 'applied' => round($applied, 2), 'refunded' => round($refunded, 2), 'available' => $available,
            'status' => $credit <= 0 ? 'none' : ($available <= 0.004 ? 'fully_used' : ($applied + $refunded > 0.004 ? 'partially_used' : 'available'))];
    }

    /** Available credit of every posted credit note of a customer (scoped by the caller's query). */
    public function customerAvailableCredit(Customer $customer): float
    {
        $total = 0.0;
        foreach (CustomerAdjustmentNote::query()->where('customer_id', $customer->id)->where('note_type', CustomerAdjustmentNote::TYPE_CREDIT)
            ->where('status', CustomerAdjustmentNote::STATUS_POSTED)->where('unapplied_amount', '>', 0)->get() as $note) {
            $total += $this->availableCredit($note);
        }

        return round($total, 2);
    }

    /**
     * Apply credit to another open invoice of the same customer.
     *
     * @param  array{amount: float|string, allocation_date: string, reference?: ?string, notes?: ?string, idempotency_key?: ?string}  $data
     * @return array{0: CustomerCreditAllocation, 1: bool} the allocation and whether it was replayed
     */
    public function allocate(CustomerAdjustmentNote $note, CustomerInvoice $target, array $data, User $actor): array
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('customer_notes.err_amount_positive')]);
        }

        return DB::transaction(function () use ($note, $target, $data, $amount, $actor) {
            // Lock order: credit note, then the target invoice.
            $source = CustomerAdjustmentNote::withoutGlobalScopes()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            $invoice = CustomerInvoice::withoutGlobalScopes()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (! empty($data['idempotency_key'])) {
                $existing = CustomerCreditAllocation::withoutGlobalScopes()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    SettlementReplay::assertMatches($existing, [
                        'customer_adjustment_note_id' => $source->id, 'customer_invoice_id' => $invoice->id, 'amount' => $amount, 'allocation_date' => $data['allocation_date'],
                    ], 'allocation_date');

                    return [$existing, true];
                }
            }
            $this->assertCreditSource($source);
            if ((int) $invoice->customer_id !== (int) $source->customer_id) {
                throw ValidationException::withMessages(['customer_invoice_id' => __('customer_notes.err_target_other_customer')]);
            }
            if ($invoice->id === $source->customer_invoice_id) {
                throw ValidationException::withMessages(['customer_invoice_id' => __('customer_notes.err_target_is_original')]);
            }
            if (! in_array($invoice->payment_status, ['unpaid', 'partially_paid'], true) || (float) $invoice->balance_amount <= 0) {
                throw ValidationException::withMessages(['customer_invoice_id' => __('customer_notes.err_target_not_open')]);
            }
            $available = $this->availableCredit($source);
            if ($amount > $available + 0.004) {
                throw ValidationException::withMessages(['amount' => __('customer_notes.err_exceeds_available', ['available' => number_format($available, 2)])]);
            }
            if ($amount > (float) $invoice->balance_amount + 0.004) {
                throw ValidationException::withMessages(['amount' => __('customer_notes.err_exceeds_outstanding', ['outstanding' => number_format((float) $invoice->balance_amount, 2)])]);
            }
            $allocation = CustomerCreditAllocation::create([
                'customer_adjustment_note_id' => $source->id, 'customer_invoice_id' => $invoice->id, 'customer_id' => $source->customer_id, 'amount' => $amount,
                'allocation_date' => $data['allocation_date'], 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null, 'created_by' => $actor->id,
            ]);
            $invoice->refreshPaymentStatus();
            $this->log($actor, 'Customer credit applied to invoice', $source->note_number.' → '.$invoice->invoice_number.' · SAR '.number_format($amount, 2));

            return [$allocation, false];
        });
    }

    public function reverseAllocation(CustomerCreditAllocation $allocation, string $reason, User $actor): CustomerCreditAllocation
    {
        return DB::transaction(function () use ($allocation, $reason, $actor) {
            CustomerAdjustmentNote::withoutGlobalScopes()->whereKey($allocation->customer_adjustment_note_id)->lockForUpdate()->firstOrFail();
            $invoice = CustomerInvoice::withoutGlobalScopes()->whereKey($allocation->customer_invoice_id)->lockForUpdate()->firstOrFail();
            $locked = CustomerCreditAllocation::withoutGlobalScopes()->whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            if ($locked->isReversed()) {
                return $locked; // already reversed: nothing more to undo
            }
            if (in_array($invoice->payment_status, ['draft', 'cancelled'], true)) {
                throw ValidationException::withMessages(['allocation' => __('customer_notes.err_target_not_open')]);
            }
            $locked->forceFill(['reversed_at' => now(), 'reversed_by' => $actor->id, 'reversal_reason' => mb_substr(trim($reason), 0, 500)])->save();
            $invoice->refreshPaymentStatus();
            $this->log($actor, 'Customer credit allocation reversed', $locked->note()->withoutGlobalScopes()->value('note_number').' → '.$invoice->invoice_number.' · SAR '.number_format((float) $locked->amount, 2).' · '.$reason);

            return $locked->fresh();
        });
    }

    /**
     * Refund available credit from a bank / cash account.
     *
     * @param  array{amount: float|string, payment_date: string, payment_account_id: int|string, payment_method?: ?string, reference?: ?string, notes?: ?string, idempotency_key?: ?string}  $data
     * @return array{0: CustomerCreditRefund, 1: bool}
     */
    public function refund(CustomerAdjustmentNote $note, array $data, User $actor): array
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('customer_notes.err_amount_positive')]);
        }

        return DB::transaction(function () use ($note, $data, $amount, $actor) {
            $source = CustomerAdjustmentNote::withoutGlobalScopes()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            if (! empty($data['idempotency_key'])) {
                $existing = CustomerCreditRefund::withoutGlobalScopes()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    SettlementReplay::assertMatches($existing, [
                        'customer_adjustment_note_id' => $source->id, 'payment_account_id' => (int) $data['payment_account_id'], 'amount' => $amount, 'payment_date' => $data['payment_date'],
                    ], 'payment_date');

                    return [$existing, true];
                }
            }
            $this->assertCreditSource($source);
            $customer = Customer::withoutGlobalScopes()->whereKey($source->customer_id)->lockForUpdate()->firstOrFail();
            $account = $this->paymentAccount((int) $data['payment_account_id'], $customer);
            $method = $data['payment_method'] ?? ($account->account_code === PostingService::CASH ? 'Cash' : 'Bank Transfer');
            if (! in_array($method, CustomerCreditRefund::METHODS, true) || (($account->account_code === PostingService::CASH) !== ($method === 'Cash'))) {
                throw ValidationException::withMessages(['payment_method' => __('customer_notes.err_method_account')]);
            }
            $available = $this->availableCredit($source);
            if ($amount > $available + 0.004) {
                throw ValidationException::withMessages(['amount' => __('customer_notes.err_exceeds_available', ['available' => number_format($available, 2)])]);
            }
            $refund = CustomerCreditRefund::create([
                'customer_adjustment_note_id' => $source->id, 'customer_id' => $source->customer_id, 'payment_date' => $data['payment_date'], 'payment_account_id' => $account->id,
                'payment_method' => $method, 'amount' => $amount, 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null, 'created_by' => $actor->id,
            ]);
            // A refund without its journal is refused and rolled back together.
            $entry = $this->posting->postCustomerCreditRefund($refund, $actor->id);
            $refund->forceFill(['journal_entry_id' => $entry->id])->save();
            $this->log($actor, 'Customer refund recorded', $source->note_number.' · SAR '.number_format($amount, 2).' · '.$account->account_code.($refund->reference ? ' · '.$refund->reference : ''));

            return [$refund->fresh(), false];
        });
    }

    public function reverseRefund(CustomerCreditRefund $refund, string $reason, User $actor): CustomerCreditRefund
    {
        $reason = mb_substr(trim($reason), 0, 500);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('customer_notes.err_reason_required')]);
        }

        return DB::transaction(function () use ($refund, $reason, $actor) {
            $source = CustomerAdjustmentNote::withoutGlobalScopes()->whereKey($refund->customer_adjustment_note_id)->lockForUpdate()->firstOrFail();
            $locked = CustomerCreditRefund::withoutGlobalScopes()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->isReversed()) {
                return $locked; // already reversed
            }
            $entry = $locked->journalEntry()->withoutGlobalScopes()->firstOrFail();
            $reversal = $this->posting->reverseEntry($entry, 'Reversed customer refund '.$source->note_number.': '.$reason, $actor->id);
            $locked->forceFill(['reversed_at' => now(), 'reversed_by' => $actor->id, 'reversal_reason' => $reason, 'reversal_journal_id' => $reversal->id])->save();
            $this->log($actor, 'Customer refund reversed', $source->note_number.' · SAR '.number_format((float) $locked->amount, 2).' · '.$reason);

            return $locked->fresh();
        });
    }

    /** Open invoices of the customer the credit may be applied to (the caller's scope applies through the query). */
    public function eligibleTargets(CustomerAdjustmentNote $note)
    {
        return CustomerInvoice::query()->where('customer_id', $note->customer_id)->where('id', '!=', $note->customer_invoice_id)
            ->whereIn('payment_status', ['unpaid', 'partially_paid'])->where('balance_amount', '>', 0)->orderBy('due_date')->orderBy('id')->get();
    }

    private function assertCreditSource(CustomerAdjustmentNote $note): void
    {
        if (! $note->isCredit() || ! $note->isPosted()) {
            throw ValidationException::withMessages(['note' => __('customer_notes.err_not_credit_source')]);
        }
    }

    /** An active cash / bank account this customer accepts (same catalogue and channel rule as receipts). */
    private function paymentAccount(int $id, Customer $customer): ChartOfAccount
    {
        $account = ChartOfAccount::whereKey($id)->where('status', 'active')->whereIn('account_code', [PostingService::CASH, PostingService::BANK])->first();
        if (! $account || ! in_array($account->account_code, $customer->allowedPaymentAccountCodes(), true)) {
            throw ValidationException::withMessages(['payment_account_id' => __('customer_notes.err_payment_account')]);
        }

        return $account;
    }

    private function log(User $actor, string $action, string $description): void
    {
        ActivityLog::create(['user_id' => $actor->id, 'user_name' => $actor->name, 'module' => 'Accounting', 'action' => $action, 'status' => 'success', 'description' => mb_substr($description, 0, 500)]);
    }
}
