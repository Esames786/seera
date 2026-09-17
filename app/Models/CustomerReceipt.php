<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReceipt extends Model
{
    /** How the money actually arrived (client change request NR-28). */
    public const METHODS = ['Cash', 'Bank Transfer', 'Cheque'];

    protected $fillable = [
        'customer_id', 'customer_invoice_id', 'receipt_date', 'receipt_account_id',
        'payment_method', 'amount', 'reference_number', 'journal_entry_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function receiptAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'receipt_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
