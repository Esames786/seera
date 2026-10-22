<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer credit applied from a posted credit note to another open invoice
 * of the same customer. An AR sub-ledger event only: the credit note already
 * credited Accounts Receivable and the target invoice already debited it, so
 * no journal is written. A reversal keeps the row and stamps it.
 */
class CustomerCreditAllocation extends Model
{
    protected $fillable = [
        'customer_adjustment_note_id', 'customer_invoice_id', 'customer_id', 'amount', 'allocation_date', 'reference', 'notes',
        'idempotency_key', 'created_by', 'reversed_at', 'reversed_by', 'reversal_reason',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'allocation_date' => 'date', 'reversed_at' => 'datetime'];
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

    public function invoice()
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
