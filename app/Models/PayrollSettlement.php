<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One salary payment recorded against a posted payroll run: the structured
 * link between the run, the bank / cash account and the settlement journal
 * (Dr payroll payable, Cr bank / cash). Never edited or deleted; a correction
 * is a reversal that keeps this row and links the opposite journal.
 */
class PayrollSettlement extends Model
{
    public const METHODS = ['Bank Transfer', 'Cash'];

    protected $fillable = [
        'payroll_run_id', 'payment_date', 'payment_account_id', 'payment_method', 'amount',
        'reference_number', 'journal_entry_id', 'reversal_journal_id', 'reversed_at', 'reversal_reason',
        'idempotency_key', 'created_by', 'reversed_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    public function isReversed(): bool
    {
        return $this->reversal_journal_id !== null;
    }

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser()
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
