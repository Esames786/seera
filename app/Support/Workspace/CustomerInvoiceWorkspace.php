<?php

namespace App\Support\Workspace;

use App\Models\CustomerInvoice;
use App\Models\User;
use App\Support\AgeingBuckets;
use Illuminate\Support\Carbon;

/**
 * Read-only context of the Customer Invoice document workspace (Wave 2 Batch C).
 *
 * Everything here is derived from the invoice in the URL and from queries that
 * carry the user's access scope. Nothing is written: approval, receipts and
 * reopening stay explicit actions on their own routes, and the stored
 * received / balance columns (kept current by CustomerInvoice::refreshPaymentStatus)
 * remain the single source of the outstanding amount.
 */
final class CustomerInvoiceWorkspace
{
    public const OPEN_STATUSES = ['unpaid', 'partially_paid'];

    /** @return array<string, mixed> */
    public static function data(CustomerInvoice $invoice, User $user, ?string $returnTo, ?int $receiptsPage = null): array
    {
        $open = in_array($invoice->payment_status, self::OPEN_STATUSES, true);
        $overdueDays = $open && $invoice->due_date && $invoice->due_date->isPast()
            ? (int) $invoice->due_date->diffInDays(Carbon::today())
            : 0;

        $flags = [
            'canEdit' => $invoice->isEditable() && $user->hasPermission('Accounts Receivable', 'edit'),
            'canApprove' => $invoice->payment_status === 'draft' && $user->hasPermission('Accounts Receivable', 'approve'),
            'canReceive' => $open && $user->hasPermission('Accounts Receivable', 'process'),
            'canReopen' => $invoice->payment_status === 'unpaid'
                && $user->isSuperAdmin()
                && $user->hasPermission('Accounts Receivable', 'approve')
                && ! $invoice->receipts()->exists()
                && ! in_array($invoice->zatcaRecord?->clearance_status, ['cleared', 'reported'], true),
            'canViewCustomer' => $user->hasPermission('Customers', 'view'),
            'canManageCustomer' => $user->hasPermission('Customers', 'edit'),
            'canViewProject' => $invoice->project !== null && $user->hasPermission('Projects', 'view'),
            'canViewJournals' => $user->hasPermission('Journal Entries', 'view'),
            'canViewZatca' => $user->hasPermission('ZATCA Invoicing', 'view'),
        ];

        $sections = ['information' => __('workspace.inv_information'), 'lines' => __('workspace.inv_lines')];
        if ($flags['canViewCustomer']) {
            $sections['customer'] = __('workspace.customer');
        }
        if ($flags['canViewProject']) {
            $sections['project'] = __('workspace.inv_project');
        }
        $sections['vat'] = __('workspace.inv_vat');
        if ($flags['canViewJournals']) {
            $sections['accounting'] = __('workspace.inv_accounting');
        }
        $sections += ['receipts' => __('workspace.inv_receipts'), 'balance' => __('workspace.inv_balance')];
        if ($flags['canViewZatca']) {
            $sections['zatca'] = __('workspace.inv_zatca');
        }
        $activity = DocumentActivity::latest($user, [$invoice->invoice_number], ['Accounting']);
        if ($activity !== null) {
            $sections['activity'] = __('workspace.activity');
        }

        // Scoped: a customer's invoices outside the viewer's projects are neither listed nor counted.
        $customerOpen = CustomerInvoice::query()->where('customer_id', $invoice->customer_id)->whereIn('payment_status', self::OPEN_STATUSES);

        return $flags + [
            'invoice' => $invoice,
            'returnTo' => $returnTo,
            'selfUrl' => route('admin.accounting.accounts-receivable.show', $invoice, false),
            'sections' => $sections,
            'open' => $open,
            'paymentState' => self::paymentState($invoice, $overdueDays),
            'eInvoiceState' => self::eInvoiceState($invoice),
            'overdueDays' => $overdueDays,
            'ageingBucket' => $open ? self::bucket($invoice) : null,
            'customerOutstanding' => round((float) (clone $customerOpen)->sum('balance_amount'), 2),
            'customerOpenInvoices' => (clone $customerOpen)->count(),
            'receipts' => $invoice->receipts()->with(['receiptAccount', 'journalEntry'])
                ->latest('receipt_date')->latest('id')
                ->paginate(10, ['*'], 'page_receipts', $receiptsPage)->fragment('receipts'),
            'activity' => $activity,
        ];
    }

    public static function paymentState(CustomerInvoice $invoice, int $overdueDays): string
    {
        return match ($invoice->payment_status) {
            'draft' => __('workspace.inv_state_draft'),
            'unpaid' => $overdueDays > 0 ? __('workspace.inv_state_overdue', ['days' => $overdueDays]) : __('workspace.inv_state_awaiting'),
            'partially_paid' => __('workspace.inv_state_partly'),
            'paid' => __('workspace.inv_state_paid'),
            'cancelled' => __('workspace.inv_state_cancelled'),
            default => ucfirst(str_replace('_', ' ', (string) $invoice->payment_status)),
        };
    }

    /**
     * Truthful wording for the invoice's local e-invoice column. The system never
     * talks to ZATCA: every status is a local record state, not a clearance result.
     */
    public static function eInvoiceState(CustomerInvoice $invoice): string
    {
        return match ($invoice->zatca_status) {
            'draft', 'not_generated', null, '' => __('workspace.inv_einv_none'),
            'generated' => __('workspace.inv_einv_generated'),
            'pending_clearance' => __('workspace.inv_einv_pending'),
            'cleared' => __('workspace.inv_einv_cleared_local'),
            'failed' => __('workspace.inv_einv_failed'),
            default => __('workspace.inv_einv_other', ['status' => str_replace('_', ' ', (string) $invoice->zatca_status)]),
        };
    }

    /** The shared ageing rule applied to this one invoice. */
    private static function bucket(CustomerInvoice $invoice): string
    {
        foreach (AgeingBuckets::fromRows([$invoice]) as $bucket => $amount) {
            if ($amount > 0) {
                return $bucket;
            }
        }

        return AgeingBuckets::BUCKETS[0];
    }
}
