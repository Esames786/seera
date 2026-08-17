<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    public const STATUSES = ['draft', 'approved', 'posted', 'cancelled'];

    public const SOURCE_MODULES = [
        'Manual', 'Payroll', 'Site Expense', 'Inventory',
        'Supplier Bill', 'Supplier Payment', 'Customer Invoice', 'Customer Receipt',
    ];

    protected $fillable = [
        'journal_number', 'journal_date', 'reference_number', 'source_module',
        'source_id', 'description', 'cost_center_id', 'total_debit', 'total_credit',
        'status', 'created_by', 'approved_by', 'approved_at', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
        ];
    }

    public function isBalanced(): bool
    {
        return abs((float) $this->total_debit - (float) $this->total_credit) < 0.01;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'approved'], true);
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Next sequential journal number, e.g. JV-2026-0007.
     */
    public static function nextNumber(int $year): string
    {
        $prefix = 'JV-'.$year.'-';
        $sequence = static::where('journal_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
