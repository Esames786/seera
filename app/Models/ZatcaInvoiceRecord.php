<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The compliance record of one customer invoice or credit / debit note: UUID (KSA-1), type and
 * transaction code, the ICV / PIH / hash chain values assigned by the EGS
 * unit, the private XML artifacts, the Phase-2 QR, validation and ZATCA
 * outcomes. `status` is an explicit state machine; nothing here is a
 * clearance or reporting result unless ZATCA actually returned it.
 */
class ZatcaInvoiceRecord extends Model
{
    public const STATUS_NOT_GENERATED = 'not_generated';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_VALIDATION_FAILED = 'validation_failed';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_SUBMISSION_PENDING = 'submission_pending';

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_REPORTED = 'reported';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    public const STATUS_OUTCOME_UNKNOWN = 'outcome_unknown';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_NOT_GENERATED, self::STATUS_GENERATED, self::STATUS_VALIDATION_FAILED, self::STATUS_VALIDATED, self::STATUS_SIGNED,
        self::STATUS_SUBMISSION_PENDING, self::STATUS_CLEARED, self::STATUS_REPORTED, self::STATUS_REJECTED, self::STATUS_FAILED,
        self::STATUS_OUTCOME_UNKNOWN, self::STATUS_CANCELLED,
    ];

    /** States in which ZATCA has accepted the document: the invoice is legally immutable. */
    public const ACCEPTED_STATUSES = [self::STATUS_CLEARED, self::STATUS_REPORTED];

    /** States after which the invoice may no longer be reopened or edited (a submission exists or may exist). */
    public const LOCKED_STATUSES = [self::STATUS_SIGNED, self::STATUS_SUBMISSION_PENDING, self::STATUS_CLEARED, self::STATUS_REPORTED, self::STATUS_REJECTED, self::STATUS_OUTCOME_UNKNOWN];

    /** Legacy foundation values kept in `clearance_status` for history only. */
    public const CLEARANCE_STATUSES = ['draft', 'generated', 'pending', 'cleared', 'failed', 'cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cleared_at' => 'datetime', 'generated_at' => 'datetime', 'validated_at' => 'datetime', 'signed_at' => 'datetime',
            'submitted_at' => 'datetime', 'reported_at' => 'datetime', 'outcome_unknown_at' => 'datetime',
            'validation_errors' => 'array', 'validation_warnings' => 'array', 'response_warnings' => 'array', 'response_errors' => 'array',
            'accepted_with_warnings' => 'boolean',
        ];
    }

    /** The customer invoice: the document itself for an invoice record, the original invoice for a note record. */
    public function customerInvoice()
    {
        return $this->belongsTo(CustomerInvoice::class);
    }

    public function adjustmentNote()
    {
        return $this->belongsTo(CustomerAdjustmentNote::class, 'customer_adjustment_note_id');
    }

    /** The business document this record belongs to (invoice or credit / debit note). */
    public function document(): ?\App\Contracts\ZatcaDocument
    {
        return $this->customer_adjustment_note_id ? $this->adjustmentNote : $this->customerInvoice;
    }

    public function isNote(): bool
    {
        return $this->customer_adjustment_note_id !== null;
    }

    /** Document number shown on the register (note number for a note, invoice number otherwise). */
    public function documentNumber(): ?string
    {
        return $this->document()?->zatcaNumber();
    }

    /** Human label of the ZATCA document kind: standard / simplified tax invoice, credit note or debit note. */
    public function documentTypeLabel(): string
    {
        $base = __('zatca.type_'.($this->invoice_type ?: 'standard'));

        return match ($this->document_type) {
            'credit_note' => __('zatca.doc_credit_note', ['type' => $base]),
            'debit_note' => __('zatca.doc_debit_note', ['type' => $base]),
            default => $base,
        };
    }

    public function unit()
    {
        return $this->belongsTo(ZatcaEgsUnit::class, 'zatca_egs_unit_id');
    }

    public function submissions()
    {
        return $this->hasMany(ZatcaSubmission::class)->orderBy('attempt');
    }

    public function isAccepted(): bool
    {
        return in_array($this->status, self::ACCEPTED_STATUSES, true);
    }

    public function isLocked(): bool
    {
        return in_array($this->status, self::LOCKED_STATUSES, true);
    }

    public function hasXml(): bool
    {
        return $this->xml_path !== null;
    }

    public function statusLabel(): string
    {
        return __('zatca.status_'.$this->status);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_CLEARED, self::STATUS_REPORTED => 'green',
            self::STATUS_REJECTED, self::STATUS_FAILED, self::STATUS_VALIDATION_FAILED => 'red',
            self::STATUS_OUTCOME_UNKNOWN => 'purple',
            self::STATUS_CANCELLED, self::STATUS_NOT_GENERATED => 'gray',
            default => 'yellow',
        };
    }

    /** Simplified documents must be reported within the configured window of generation. */
    public function reportingDeadline(): ?\Illuminate\Support\Carbon
    {
        if ($this->invoice_type !== 'simplified' || ! $this->generated_at) {
            return null;
        }

        return $this->generated_at->copy()->addHours((int) config('zatca.reporting_window_hours', 24));
    }
}
