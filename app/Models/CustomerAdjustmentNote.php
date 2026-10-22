<?php

namespace App\Models;

use App\Contracts\ZatcaDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A customer credit note or debit note: the only lawful correction of a posted
 * (and possibly ZATCA-cleared) customer invoice. It references the original
 * invoice, carries its own lines, posts its own journal and VAT rows, adjusts
 * the original invoice balance, and becomes its own ZATCA document (381 / 383)
 * on the same EGS chain. The original invoice is never changed.
 */
class CustomerAdjustmentNote extends Model implements ZatcaDocument
{
    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    public const TYPES = [self::TYPE_CREDIT, self::TYPE_DEBIT];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_POSTED];

    /**
     * The five reasons the KSA VAT Implementing Regulations (Article 40(1), Article 54(3))
     * allow for a credit or debit note, as listed in the ZATCA Data Dictionary (KSA-10).
     * The code is Seera's; the XML carries the text.
     */
    public const REASON_CODES = ['cancellation', 'supply_change', 'agreed_price_change', 'refund', 'party_details'];

    /** Invoice states a note may be issued against: posted, with or without receipts. */
    public const CORRECTABLE_INVOICE_STATUSES = ['unpaid', 'partially_paid', 'paid'];

    protected $fillable = [
        'note_type', 'note_number', 'customer_invoice_id', 'customer_id', 'project_id', 'cost_center_id', 'invoice_type',
        'note_date', 'issue_time', 'supply_date', 'reason_code', 'reason', 'status', 'taxable_amount', 'vat_rate', 'vat_amount',
        'total_amount', 'applied_amount', 'unapplied_amount', 'journal_entry_id', 'seller_snapshot', 'buyer_snapshot',
        'zatca_status', 'zatca_locked_at', 'posted_at', 'posted_by', 'created_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'note_date' => 'date',
            'supply_date' => 'date',
            'seller_snapshot' => 'array',
            'buyer_snapshot' => 'array',
            'zatca_locked_at' => 'datetime',
            'posted_at' => 'datetime',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'unapplied_amount' => 'decimal:2',
        ];
    }

    public function isCredit(): bool
    {
        return $this->note_type === self::TYPE_CREDIT;
    }

    public function isDebit(): bool
    {
        return $this->note_type === self::TYPE_DEBIT;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /** A draft can be edited; a posted note never (its correction is another note). */
    public function isEditable(): bool
    {
        return $this->isDraft() && ! $this->isZatcaLocked();
    }

    public function isZatcaLocked(): bool
    {
        return $this->zatca_locked_at !== null;
    }

    public function isSimplified(): bool
    {
        return $this->invoice_type === CustomerInvoice::TYPE_SIMPLIFIED;
    }

    /** Signed effect on the receivable: a credit note reduces it, a debit note increases it. */
    public function signedTotal(): float
    {
        return $this->isCredit() ? -(float) $this->total_amount : (float) $this->total_amount;
    }

    public function typeLabel(): string
    {
        return __('customer_notes.type_'.$this->note_type);
    }

    public function reasonLabel(): string
    {
        return __('customer_notes.reason_'.$this->reason_code);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
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

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CustomerAdjustmentNoteLine::class);
    }

    public function zatcaRecord(): HasOne
    {
        return $this->hasOne(ZatcaInvoiceRecord::class, 'customer_adjustment_note_id');
    }

    /** Credit applied from this note to other invoices (active and reversed rows). */
    public function creditAllocations()
    {
        return $this->hasMany(CustomerCreditAllocation::class, 'customer_adjustment_note_id');
    }

    /** Refunds of this note's credit (active and reversed rows). */
    public function creditRefunds()
    {
        return $this->hasMany(CustomerCreditRefund::class, 'customer_adjustment_note_id');
    }

    /** Posted credit note with credit beyond the original invoice balance: the only source of customer credit. */
    public function hasUnappliedCredit(): bool
    {
        return $this->isCredit() && $this->isPosted() && (float) $this->unapplied_amount > 0;
    }

    public static function nextNumber(string $type, int $year): string
    {
        $prefix = ($type === self::TYPE_CREDIT ? 'CN-' : 'DN-').$year.'-';

        return app(\App\Services\DocumentNumberService::class)
            ->next(($type === self::TYPE_CREDIT ? 'credit-note-' : 'debit-note-').$year, $prefix, 'customer_adjustment_notes', 'note_number');
    }

    // ------------------------------------------------------------ ZatcaDocument

    public function zatcaNumber(): string
    {
        return (string) $this->note_number;
    }

    public function zatcaIssueDate(): Carbon
    {
        return $this->note_date;
    }

    public function zatcaTypeCode(): string
    {
        return $this->isCredit() ? self::TYPE_CODE_CREDIT_NOTE : self::TYPE_CODE_DEBIT_NOTE;
    }

    public function zatcaBillingReference(): ?string
    {
        return $this->invoice?->invoice_number ?? CustomerInvoice::withoutGlobalScopes()->whereKey($this->customer_invoice_id)->value('invoice_number');
    }

    public function zatcaNoteReason(): ?string
    {
        $reason = trim(preg_replace('/\s+/', ' ', (string) $this->reason));

        return $reason === '' ? null : mb_substr($reason, 0, 1000);
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
        return ['customer_adjustment_note_id' => $this->id];
    }

    public function zatcaRecordAttributes(): array
    {
        return ['customer_invoice_id' => $this->customer_invoice_id, 'document_type' => $this->isCredit() ? 'credit_note' : 'debit_note'];
    }

    public function isPostedForZatca(): bool
    {
        return $this->isPosted();
    }

    public function zatcaActivityModules(): array
    {
        return ['Accounting', 'ZATCA'];
    }
}
