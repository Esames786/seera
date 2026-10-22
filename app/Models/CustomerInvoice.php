<?php

namespace App\Models;

use App\Contracts\ZatcaDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class CustomerInvoice extends Model implements ZatcaDocument
{
    public const PAYMENT_STATUSES = ['draft', 'unpaid', 'partially_paid', 'paid', 'cancelled'];

    /** Mirror of the compliance record state shown on the register (ZatcaInvoiceRecord::STATUSES). */
    public const ZATCA_STATUSES = ['not_generated', 'generated', 'validated', 'signed', 'submission_pending', 'cleared', 'reported', 'rejected', 'failed', 'outcome_unknown', 'cancelled'];

    public const TYPE_STANDARD = 'standard';

    public const TYPE_SIMPLIFIED = 'simplified';

    public const TYPES = [self::TYPE_STANDARD, self::TYPE_SIMPLIFIED];

    protected $fillable = [
        'customer_id', 'invoice_number', 'invoice_type', 'invoice_date', 'issue_time', 'supply_date', 'due_date', 'project_id',
        'cost_center_id', 'taxable_amount', 'vat_rate', 'vat_amount', 'total_amount',
        'received_amount', 'balance_amount', 'payment_status', 'zatca_status',
        'journal_entry_id', 'notes', 'seller_snapshot', 'buyer_snapshot', 'zatca_locked_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'supply_date' => 'date',
            'due_date' => 'date',
            'seller_snapshot' => 'array',
            'buyer_snapshot' => 'array',
            'zatca_locked_at' => 'datetime',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
    }

    public function isEditable(): bool
    {
        return in_array($this->payment_status, ['draft', 'cancelled'], true) && ! $this->isZatcaLocked();
    }

    /** True once a stamped or submitted e-invoice exists: the document is legally immutable in Seera. */
    public function isZatcaLocked(): bool
    {
        return $this->zatca_locked_at !== null;
    }

    public function isSimplified(): bool
    {
        return $this->invoice_type === self::TYPE_SIMPLIFIED;
    }

    /** Posted, so a credit or debit note may correct it (draft and cancelled invoices are edited or reopened instead). */
    public function isCorrectable(): bool
    {
        return in_array($this->payment_status, CustomerAdjustmentNote::CORRECTABLE_INVOICE_STATUSES, true);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CustomerInvoiceLine::class);
    }

    public function receipts()
    {
        return $this->hasMany(CustomerReceipt::class);
    }

    /** Credit and debit notes issued against this invoice (any status). */
    public function adjustmentNotes()
    {
        return $this->hasMany(CustomerAdjustmentNote::class, 'customer_invoice_id');
    }

    /** Customer credit applied to this invoice from credit notes of other invoices (active and reversed rows). */
    public function creditAllocations()
    {
        return $this->hasMany(CustomerCreditAllocation::class, 'customer_invoice_id');
    }

    /** Active credit applied to this invoice from other credit notes (not cash: shown apart from receipts). */
    public function creditAllocatedAmount(): float
    {
        return round((float) $this->creditAllocations()->withoutGlobalScopes()->whereNull('reversed_at')->sum('amount'), 2);
    }

    /** The invoice's own compliance record (a note's record points at the invoice too, but carries the note key). */
    public function zatcaRecord(): HasOne
    {
        return $this->hasOne(ZatcaInvoiceRecord::class)->whereNull('customer_adjustment_note_id');
    }

    /**
     * Posted correction totals: the credit applied to this invoice's balance (never
     * more than was open), the credit that could not be applied (customer credit
     * awaiting Finance), and the debit added to the receivable.
     *
     * @return array{credited: float, credit_applied: float, credit_unapplied: float, debited: float}
     */
    public function postedAdjustments(): array
    {
        $posted = $this->adjustmentNotes()->withoutGlobalScopes()->where('status', CustomerAdjustmentNote::STATUS_POSTED)->get(['note_type', 'total_amount', 'applied_amount', 'unapplied_amount']);
        $credits = $posted->where('note_type', CustomerAdjustmentNote::TYPE_CREDIT);
        $debits = $posted->where('note_type', CustomerAdjustmentNote::TYPE_DEBIT);

        return [
            'credited' => round((float) $credits->sum('total_amount'), 2),
            'credit_applied' => round((float) $credits->sum('applied_amount'), 2),
            'credit_unapplied' => round((float) $credits->sum('unapplied_amount'), 2),
            'debited' => round((float) $debits->sum('total_amount'), 2),
        ];
    }

    /**
     * The outstanding amount is the posted total plus posted debit notes, less the
     * credit applied by this invoice's own credit notes, less customer credit
     * allocated from other credit notes, less cash / bank receipts. Paid means
     * that amount is settled; received_amount stays cash only, so credit applied
     * is always distinguishable from money received. The stored columns stay the
     * single source for every AR figure.
     */
    public function refreshPaymentStatus(): void
    {
        if (in_array($this->payment_status, ['draft', 'cancelled'], true)) {
            return;
        }

        $received = (float) $this->receipts()->sum('amount');
        $adjustments = $this->postedAdjustments();
        $allocated = $this->creditAllocatedAmount();
        $owed = round((float) $this->total_amount + $adjustments['debited'] - $adjustments['credit_applied'] - $allocated, 2);
        $balance = round($owed - $received, 2);

        $this->update([
            'received_amount' => round($received, 2),
            'balance_amount' => max($balance, 0),
            'payment_status' => match (true) {
                $balance <= 0.009 && ($received > 0 || $adjustments['credit_applied'] > 0 || $allocated > 0) => 'paid',
                $received <= 0 && $allocated <= 0 => 'unpaid',
                default => 'partially_paid',
            },
        ]);
    }

    public static function nextNumber(int $year): string
    {
        $prefix = 'INV-'.$year.'-';
        return app(\App\Services\DocumentNumberService::class)
            ->next('invoice-'.$year, $prefix, 'customer_invoices', 'invoice_number');
    }

    // ------------------------------------------------------------ ZatcaDocument

    public function zatcaNumber(): string
    {
        return (string) $this->invoice_number;
    }

    public function zatcaIssueDate(): Carbon
    {
        return $this->invoice_date;
    }

    public function zatcaTypeCode(): string
    {
        return self::TYPE_CODE_INVOICE;
    }

    public function zatcaBillingReference(): ?string
    {
        return null;
    }

    public function zatcaNoteReason(): ?string
    {
        return null;
    }

    public function zatcaFreeNote(): ?string
    {
        return $this->notes ? mb_substr(trim(preg_replace('/\s+/', ' ', $this->notes)), 0, 1000) : null;
    }

    public function zatcaAmounts(): array
    {
        return ['taxable_amount' => $this->taxable_amount, 'vat_rate' => $this->vat_rate, 'vat_amount' => $this->vat_amount, 'total_amount' => $this->total_amount];
    }

    public function zatcaRecordKey(): array
    {
        return ['customer_invoice_id' => $this->id, 'customer_adjustment_note_id' => null];
    }

    public function zatcaRecordAttributes(): array
    {
        return ['document_type' => 'invoice'];
    }

    public function isPostedForZatca(): bool
    {
        return in_array($this->payment_status, ['unpaid', 'partially_paid', 'paid'], true);
    }

    public function zatcaActivityModules(): array
    {
        return ['Accounting', 'ZATCA'];
    }
}
