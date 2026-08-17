<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    public const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    /** Account types whose balance grows on the debit side. */
    public const DEBIT_TYPES = ['asset', 'expense'];

    protected $fillable = [
        'account_code', 'account_name', 'account_type', 'parent_id',
        'opening_balance', 'normal_balance', 'vat_applicable',
        'cost_center_required', 'status',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'vat_applicable' => 'boolean',
            'cost_center_required' => 'boolean',
        ];
    }

    public function label(): string
    {
        return $this->account_code.' - '.$this->account_name;
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('account_code');
    }

    public function journalLines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Net movement from posted journals only, signed toward the account's normal balance.
     */
    public function postedBalance(): float
    {
        $totals = JournalEntryLine::query()
            ->where('chart_of_account_id', $this->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->first();

        $movement = (float) $totals->debit - (float) $totals->credit;

        return $this->normal_balance === 'credit'
            ? (float) $this->opening_balance - $movement
            : (float) $this->opening_balance + $movement;
    }
}
