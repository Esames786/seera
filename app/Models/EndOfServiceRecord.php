<?php

namespace App\Models;

use App\Services\Hr\GratuityCalculator;
use Illuminate\Database\Eloquent\Model;

class EndOfServiceRecord extends Model
{
    public const STATUSES = ['draft', 'approved', 'paid'];

    protected $fillable = [
        'employee_id', 'termination_date', 'termination_reason', 'service_years',
        'last_basic_salary', 'gratuity_before_adjustment', 'entitlement_percentage',
        'eosb_amount', 'manual_override', 'leave_salary', 'other_dues', 'deductions',
        'final_amount', 'reason', 'status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'termination_date' => 'date',
            'approved_at' => 'datetime',
            'service_years' => 'decimal:2',
            'last_basic_salary' => 'decimal:2',
            'gratuity_before_adjustment' => 'decimal:2',
            'entitlement_percentage' => 'decimal:2',
            'eosb_amount' => 'decimal:2',
            'leave_salary' => 'decimal:2',
            'other_dues' => 'decimal:2',
            'deductions' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'manual_override' => 'boolean',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function reasonLabel(): string
    {
        return GratuityCalculator::reasonLabels()[$this->termination_reason] ?? ucfirst((string) $this->termination_reason);
    }

    /**
     * Final settlement = gratuity + leave salary + other dues - deductions.
     */
    public function settlementTotal(): float
    {
        return round(
            (float) $this->eosb_amount + (float) $this->leave_salary
            + (float) $this->other_dues - (float) $this->deductions,
            2
        );
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
