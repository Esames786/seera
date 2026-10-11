<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PayrollRun extends Model
{
    public const STATUSES = ['draft', 'processed', 'approved', 'paid'];

    /** Accounting state, kept apart from the HR status above. */
    public const ACCOUNTING_NOT_POSTED = 'not_posted';

    public const ACCOUNTING_REVIEW = 'review';

    public const ACCOUNTING_POSTED = 'posted';

    public const ACCOUNTING_FAILED = 'failed';

    public const ACCOUNTING_REVERSED = 'reversed';

    public const ACCOUNTING_STATUSES = [self::ACCOUNTING_NOT_POSTED, self::ACCOUNTING_REVIEW, self::ACCOUNTING_POSTED, self::ACCOUNTING_FAILED, self::ACCOUNTING_REVERSED];

    /** Salary payment (settlement) state, kept apart from both of the above. Phase 2 settles a run in full; 'partial' is reserved. */
    public const PAYMENT_NOT_PAID = 'not_paid';

    public const PAYMENT_PARTIAL = 'partial';

    public const PAYMENT_PAID = 'paid';

    protected $fillable = [
        'code', 'payroll_month', 'payroll_year', 'period_start', 'period_end',
        'branch_id', 'project_id', 'total_employees', 'gross_amount',
        'total_deductions', 'net_amount', 'status', 'processed_at',
        'approved_by', 'approved_at', 'notes',
        'accounting_status', 'journal_entry_id', 'posted_at', 'posting_error',
        'reversal_journal_id', 'reversed_at', 'reversal_reason',
        'payment_status', 'paid_amount', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'processed_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
            'settled_at' => 'datetime',
            'gross_amount' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function periodLabel(): string
    {
        return Carbon::create($this->payroll_year, $this->payroll_month, 1)->format('F Y');
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'paid'], true);
    }

    /** Approved and financially posted or reversed: the run's figures are history and must not change. */
    public function isFinanciallyLocked(): bool
    {
        return $this->journal_entry_id !== null || $this->reversal_journal_id !== null || $this->isPaid();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    /** What is still owed to employees for this run: net payroll minus what was settled. */
    public function remainingPayable(): float
    {
        return round(max(0, (float) $this->net_amount - (float) $this->paid_amount), 2);
    }

    public function accountingLabel(): string
    {
        return match ($this->accounting_status) {
            self::ACCOUNTING_POSTED => 'Posted to accounting',
            self::ACCOUNTING_REVIEW => 'Approved — accounting review required',
            self::ACCOUNTING_FAILED => 'Approved — posting failed',
            self::ACCOUNTING_REVERSED => 'Accounting reversed',
            default => $this->isApproved() ? 'Approved — not posted' : 'Not posted',
        };
    }

    public function paymentLabel(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_PAID => 'Paid',
            self::PAYMENT_PARTIAL => 'Partially paid',
            default => 'Not paid',
        };
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function reversalJournal()
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_id');
    }

    public function settlements()
    {
        return $this->hasMany(PayrollSettlement::class)->orderBy('id');
    }

    /** The settlement that currently pays the run (none once reversed). */
    public function activeSettlement()
    {
        return $this->hasOne(PayrollSettlement::class)->whereNull('reversal_journal_id')->latestOfMany();
    }

    public function bankExports()
    {
        return $this->hasMany(PayrollBankExport::class)->orderByDesc('sequence');
    }
}
