<?php

namespace App\Services\Accounting;

use App\Models\ActivityLog;
use App\Models\CustomerAdjustmentNote;
use App\Models\CustomerAdjustmentNoteLine;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\User;
use App\Services\Zatca\ZatcaSnapshot;
use App\Services\Zatca\ZatcaSubmissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer credit / debit notes: the correction documents of a posted customer
 * invoice. Draft save writes nothing to the ledger; posting (one transaction,
 * original invoice locked first) posts the journal and the VAT correction,
 * applies the note to the original invoice balance, freezes the snapshots and
 * opens the ZATCA record. Amounts are always recalculated here from quantity
 * and unit price; submitted totals are ignored. Cumulative credit per original
 * line can never exceed the line's taxable amount.
 */
final class CustomerNoteService
{
    public function __construct(
        private readonly PostingService $posting,
        private readonly ZatcaSnapshot $snapshot,
        private readonly ZatcaSubmissionService $zatca,
    ) {}

    /**
     * Validate the business relation and the lines, and compute the amounts.
     *
     * @param  array<int, array<string, mixed>>  $lines  [{invoice_line_id?, description?, quantity, unit_price, revenue_account_id?, cost_center_id?}]
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    public function prepare(CustomerInvoice $invoice, string $type, array $input, array $lines, ?CustomerAdjustmentNote $existing = null): array
    {
        if (! in_array($type, CustomerAdjustmentNote::TYPES, true)) {
            throw ValidationException::withMessages(['note_type' => __('customer_notes.err_type')]);
        }
        if (! $invoice->isCorrectable()) {
            throw ValidationException::withMessages(['customer_invoice_id' => __('customer_notes.err_invoice_not_posted')]);
        }
        if ($existing && ($existing->customer_invoice_id !== $invoice->id || $existing->note_type !== $type)) {
            throw ValidationException::withMessages(['customer_invoice_id' => __('customer_notes.err_relation_fixed')]);
        }
        $invoice->loadMissing('lines');
        $credit = $type === CustomerAdjustmentNote::TYPE_CREDIT;
        $rate = (float) $invoice->vat_rate;
        $rows = [];
        foreach ($lines as $index => $line) {
            $sourceLine = null;
            if (! empty($line['invoice_line_id'])) {
                $sourceLine = $invoice->lines->firstWhere('id', (int) $line['invoice_line_id']);
                if (! $sourceLine) {
                    throw ValidationException::withMessages(['lines.'.$index.'.invoice_line_id' => __('customer_notes.err_line_not_on_invoice')]);
                }
            } elseif ($credit) {
                throw ValidationException::withMessages(['lines.'.$index.'.invoice_line_id' => __('customer_notes.err_credit_line_source')]);
            }
            $quantity = (float) ($line['quantity'] ?? ($sourceLine?->quantity ?? 1));
            $price = (float) ($line['unit_price'] ?? ($sourceLine?->unit_price ?? 0));
            $description = trim((string) ($line['description'] ?? '')) ?: (string) ($sourceLine?->description ?? '');
            if ($quantity <= 0 || $price <= 0 || $description === '') {
                continue; // blank or zero rows are ignored, like the invoice form
            }
            $taxable = round($quantity * $price, 2);
            $vat = round($taxable * $rate / 100, 2);
            $rows[] = [
                'customer_invoice_line_id' => $sourceLine?->id,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $price,
                'taxable_amount' => $taxable,
                'vat_rate' => $rate,
                'vat_amount' => $vat,
                'total_amount' => round($taxable + $vat, 2),
                'revenue_account_id' => $line['revenue_account_id'] ?? $sourceLine?->revenue_account_id,
                'cost_center_id' => $line['cost_center_id'] ?? $sourceLine?->cost_center_id,
            ];
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['lines' => __('customer_notes.err_no_lines')]);
        }
        if ($credit) {
            $this->assertWithinRemaining($invoice, $rows, $existing?->id);
        }
        $taxable = round(array_sum(array_column($rows, 'taxable_amount')), 2);
        $vat = round(array_sum(array_column($rows, 'vat_amount')), 2);
        $attributes = [
            'note_type' => $type,
            'customer_invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'project_id' => $invoice->project_id,
            'cost_center_id' => $invoice->cost_center_id,
            'invoice_type' => $invoice->invoice_type,
            'note_date' => $input['note_date'],
            'supply_date' => $invoice->supply_date?->toDateString(),
            'reason_code' => $input['reason_code'],
            'reason' => trim((string) $input['reason']),
            'notes' => $input['notes'] ?? null,
            'taxable_amount' => $taxable,
            'vat_rate' => $rate,
            'vat_amount' => $vat,
            'total_amount' => round($taxable + $vat, 2),
        ];

        return [$attributes, $rows];
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function createDraft(CustomerInvoice $invoice, string $type, array $input, array $lines, User $actor): CustomerAdjustmentNote
    {
        [$attributes, $rows] = $this->prepare($invoice, $type, $input, $lines);

        return DB::transaction(function () use ($attributes, $rows, $type, $actor) {
            $note = CustomerAdjustmentNote::create($attributes + [
                'note_number' => CustomerAdjustmentNote::nextNumber($type, (int) date('Y', strtotime($attributes['note_date']))),
                'status' => CustomerAdjustmentNote::STATUS_DRAFT,
                'created_by' => $actor->id,
            ]);
            $note->lines()->createMany($rows);

            return $note;
        });
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function updateDraft(CustomerAdjustmentNote $note, array $input, array $lines, User $actor): CustomerAdjustmentNote
    {
        return DB::transaction(function () use ($note, $input, $lines) {
            $locked = CustomerAdjustmentNote::withoutGlobalScopes()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isEditable()) {
                throw ValidationException::withMessages(['note' => __('customer_notes.err_not_editable')]);
            }
            $invoice = CustomerInvoice::withoutGlobalScopes()->whereKey($locked->customer_invoice_id)->firstOrFail();
            [$attributes, $rows] = $this->prepare($invoice, $locked->note_type, $input, $lines, $locked);
            $locked->update($attributes);
            $locked->lines()->delete();
            $locked->lines()->createMany($rows);

            return $locked->fresh(['lines']);
        });
    }

    /**
     * Post the note: journal + VAT correction, balance application, snapshots,
     * ZATCA record. Idempotent: a note that is already posted is returned as is.
     */
    public function post(CustomerAdjustmentNote $note, User $actor): CustomerAdjustmentNote
    {
        $posted = DB::transaction(function () use ($note, $actor) {
            // Lock order: the original invoice first (it is the shared limit), then the note.
            $invoice = CustomerInvoice::withoutGlobalScopes()->whereKey($note->customer_invoice_id)->lockForUpdate()->firstOrFail();
            $locked = CustomerAdjustmentNote::withoutGlobalScopes()->whereKey($note->id)->lockForUpdate()->with('lines')->firstOrFail();
            if ($locked->isPosted()) {
                return $locked;
            }
            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['note' => __('customer_notes.err_not_draft')]);
            }
            if (! $invoice->isCorrectable()) {
                throw ValidationException::withMessages(['note' => __('customer_notes.err_invoice_not_posted')]);
            }
            if ($locked->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => __('customer_notes.err_no_lines')]);
            }
            if ($locked->isCredit()) {
                $this->assertWithinRemaining($invoice, $locked->lines->map(fn ($l) => ['customer_invoice_line_id' => $l->customer_invoice_line_id, 'taxable_amount' => (float) $l->taxable_amount])->all(), $locked->id);
            }

            $entry = $locked->isCredit()
                ? $this->posting->postCustomerCreditNote($locked, $actor->id)
                : $this->posting->postCustomerDebitNote($locked, $actor->id);

            // Application to the original balance: a credit note settles what is still open and keeps
            // the rest as customer credit for Finance; a debit note is added to the receivable in full.
            $open = max((float) $invoice->balance_amount, 0);
            $applied = $locked->isCredit() ? min((float) $locked->total_amount, $open) : (float) $locked->total_amount;

            $locked->forceFill([
                'status' => CustomerAdjustmentNote::STATUS_POSTED,
                'journal_entry_id' => $entry->id,
                'applied_amount' => round($applied, 2),
                'unapplied_amount' => round((float) $locked->total_amount - $applied, 2),
                'posted_at' => now(),
                'posted_by' => $actor->id,
                'issue_time' => $locked->issue_time ?: now()->format('H:i:s'),
            ])->save();
            $this->snapshot->freeze($locked);
            $this->zatca->ensureRecord($locked);
            $invoice->refreshPaymentStatus();

            return $locked;
        });

        if ($posted->wasChanged('status') || $posted->posted_by === $actor->id && $posted->posted_at?->gt(now()->subSeconds(5))) {
            ActivityLog::create(['user_id' => $actor->id, 'user_name' => $actor->name, 'module' => 'Accounting', 'action' => $posted->isCredit() ? 'Posted customer credit note' : 'Posted customer debit note',
                'status' => 'success', 'description' => $posted->note_number.' against '.$posted->zatcaBillingReference().' · SAR '.number_format((float) $posted->total_amount, 2)]);
        }

        return $posted->fresh(['lines', 'invoice', 'zatcaRecord']);
    }

    /**
     * Remaining creditable taxable amount per original invoice line: the line's
     * taxable amount less every posted credit note line against it. Debit notes
     * do not raise the ceiling (conservative Phase 1 policy).
     *
     * @return array<int, float> keyed by customer_invoice_line_id
     */
    public function remainingCredit(CustomerInvoice $invoice, ?int $excludeNoteId = null): array
    {
        $invoice->loadMissing('lines');
        $credited = CustomerAdjustmentNoteLine::query()
            ->whereIn('customer_invoice_line_id', $invoice->lines->modelKeys())
            ->whereHas('note', fn ($q) => $q->withoutGlobalScopes()->where('note_type', CustomerAdjustmentNote::TYPE_CREDIT)->where('status', CustomerAdjustmentNote::STATUS_POSTED)
                ->when($excludeNoteId, fn ($qq) => $qq->where('id', '!=', $excludeNoteId)))
            ->groupBy('customer_invoice_line_id')
            ->selectRaw('customer_invoice_line_id, SUM(taxable_amount) as credited')
            ->pluck('credited', 'customer_invoice_line_id');
        $remaining = [];
        foreach ($invoice->lines as $line) {
            $remaining[$line->id] = round((float) $line->taxable_amount - (float) ($credited[$line->id] ?? 0), 2);
        }

        return $remaining;
    }

    /** @param array<int, array<string, mixed>> $rows rows with customer_invoice_line_id and taxable_amount */
    private function assertWithinRemaining(CustomerInvoice $invoice, array $rows, ?int $excludeNoteId): void
    {
        $remaining = $this->remainingCredit($invoice, $excludeNoteId);
        $requested = [];
        foreach ($rows as $row) {
            $requested[$row['customer_invoice_line_id']] = round(($requested[$row['customer_invoice_line_id']] ?? 0) + (float) $row['taxable_amount'], 2);
        }
        foreach ($requested as $lineId => $amount) {
            $left = $remaining[$lineId] ?? 0.0;
            if ($amount > $left + 0.004) {
                $line = $invoice->lines->firstWhere('id', $lineId);
                throw ValidationException::withMessages(['lines' => __('customer_notes.err_over_credit', [
                    'line' => $line?->description ?? '#'.$lineId, 'remaining' => number_format($left, 2), 'requested' => number_format($amount, 2),
                ])]);
            }
        }
    }
}
