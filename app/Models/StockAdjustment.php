<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    public const STATUSES = ['draft', 'approved', 'posted', 'cancelled'];

    protected $fillable = [
        'adjustment_number', 'warehouse_id', 'item_id', 'adjustment_date',
        'current_quantity', 'adjusted_quantity', 'difference_quantity',
        'unit_cost', 'adjustment_value', 'adjustment_type', 'reason', 'status',
        'approved_by', 'approved_at', 'accounting_posted', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'approved_at' => 'datetime',
            'current_quantity' => 'decimal:3',
            'adjusted_quantity' => 'decimal:3',
            'difference_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'adjustment_value' => 'decimal:2',
            'accounting_posted' => 'boolean',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isLoss(): bool
    {
        return (float) $this->difference_quantity < 0;
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public static function nextNumber(int $year): string
    {
        $prefix = 'ADJ-'.$year.'-';

        return app(\App\Services\DocumentNumberService::class)
            ->next('stock-adjustment-'.$year, $prefix, 'stock_adjustments', 'adjustment_number');
    }
}
