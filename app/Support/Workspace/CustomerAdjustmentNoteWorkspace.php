<?php

namespace App\Support\Workspace;

use App\Models\CustomerAdjustmentNote;
use App\Models\User;

/**
 * Read-only context of the Customer Credit / Debit Note workspace. Posting and
 * the ZATCA actions stay explicit actions on their own routes; nothing here
 * writes.
 */
final class CustomerAdjustmentNoteWorkspace
{
    /** @return array<string, mixed> */
    public static function data(CustomerAdjustmentNote $note, User $user, ?string $returnTo): array
    {
        $flags = [
            'canEdit' => $note->isEditable() && $user->hasPermission('Accounts Receivable', 'edit'),
            'canPost' => $note->isDraft() && $user->hasPermission('Accounts Receivable', 'approve'),
            'canViewInvoice' => $user->hasPermission('Accounts Receivable', 'view'),
            'canViewCustomer' => $user->hasPermission('Customers', 'view'),
            'canViewProject' => $note->project !== null && $user->hasPermission('Projects', 'view'),
            'canViewJournals' => $user->hasPermission('Journal Entries', 'view'),
            'canViewZatca' => $user->hasPermission('ZATCA Invoicing', 'view'),
            'canPostZatca' => $user->hasPermission('ZATCA Invoicing', 'post'),
            'canRetryZatca' => $user->hasPermission('ZATCA Invoicing', 'retry'),
            'canExportZatca' => $user->hasPermission('ZATCA Invoicing', 'export'),
        ];

        $sections = ['summary' => __('customer_notes.sec_summary'), 'invoice' => __('customer_notes.sec_invoice'), 'lines' => __('customer_notes.sec_lines'), 'vat' => __('customer_notes.sec_vat')];
        if ($flags['canViewJournals']) {
            $sections['accounting'] = __('customer_notes.sec_accounting');
        }
        if ($flags['canViewZatca']) {
            $sections['zatca'] = __('zatca.section');
        }
        $activity = DocumentActivity::latest($user, [$note->note_number], ['Accounting', 'ZATCA']);
        if ($activity !== null) {
            $sections['activity'] = __('workspace.activity');
        }

        return $flags + [
            'note' => $note,
            'invoice' => $note->invoice,
            'returnTo' => $returnTo,
            'selfUrl' => route('admin.accounting.accounts-receivable.notes.show', $note, false),
            'sections' => $sections,
            'state' => self::state($note),
            'eInvoiceState' => self::eInvoiceState($note),
            'activity' => $activity,
        ];
    }

    public static function state(CustomerAdjustmentNote $note): string
    {
        return $note->isPosted() ? __('customer_notes.state_posted') : __('customer_notes.state_draft');
    }

    public static function eInvoiceState(CustomerAdjustmentNote $note): string
    {
        $record = $note->zatcaRecord;
        if (! $record) {
            return __('workspace.inv_einv_none');
        }
        $label = $record->statusLabel();

        return $record->isAccepted() ? $label : $label.' · '.__('zatca.not_verified');
    }
}
