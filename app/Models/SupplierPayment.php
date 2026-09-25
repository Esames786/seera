<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    /** How the money actually moved (client change request NR-28). */
    public const METHODS = ['Cash', 'Bank Transfer', 'Cheque'];

    /** Why the payment was made (client change request NR-29). Descriptive; the ledger treatment is unchanged. */
    public const PURPOSES = [
        'Bill payment',
        'Advance against this bill',
        "Salary paid on supplier's behalf",
        'Repair / back-charge deduction',
        'Other adjustment',
    ];

    protected $fillable = [
        'supplier_id', 'supplier_bill_id', 'payment_date', 'payment_account_id',
        'payment_method', 'purpose', 'amount', 'reference_number', 'journal_entry_id', 'idempotency_key', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bill()
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
