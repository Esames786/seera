<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer credit refunded from a bank / cash account against a posted credit
 * note: its own journal (Dr Accounts Receivable, Cr bank / cash), no revenue,
 * no VAT, no ZATCA document. A reversal posts the opposite journal and keeps
 * the row.
 */
class CustomerCreditRefund extends Model
{
    public const METHODS = ['Cash', 'Bank Transfer', 'Cheque'];

    protected $fillable = [
        'customer_adjustment_note_id', 'customer_id', 'payment_date', 'payment_account_id', 'payment_method', 'amount', 'reference', 'notes',
        'idempotency_key', 'journal_entry_id', 'reversal_journal_id', 'created_by', 'reversed_at', 'reversed_by', 'reversal_reason',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payment_date' => 'date', 'reversed_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('reversed_at');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    public function note()
    {
        return $this->belongsTo(CustomerAdjustmentNote::class, 'customer_adjustment_note_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function reversalJournal()
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
