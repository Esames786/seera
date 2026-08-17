<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReceipt extends Model
{
    protected $fillable = [
        'customer_id', 'customer_invoice_id', 'receipt_date', 'receipt_account_id',
        'amount', 'reference_number', 'journal_entry_id', 'notes',
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
