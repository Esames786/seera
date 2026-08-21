<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryStructure extends Model
{
    protected $fillable = [
        'employee_id', 'basic_salary', 'housing_allowance', 'transport_allowance',
        'food_allowance', 'fuel_allowance', 'other_allowance', 'fixed_deduction',
        'effective_from', 'effective_to', 'status',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'basic_salary' => 'decimal:2',
            'housing_allowance' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'food_allowance' => 'decimal:2',
            'fuel_allowance' => 'decimal:2',
            'other_allowance' => 'decimal:2',
            'fixed_deduction' => 'decimal:2',
        ];
    }

    /**
     * Fixed allowances plus any additional allowance items.
     */
    public function totalAllowances(): float
    {
        return (float) $this->housing_allowance
            + (float) $this->transport_allowance
            + (float) $this->food_allowance
            + (float) $this->fuel_allowance
            + (float) $this->other_allowance
            + (float) $this->items->where('item_type', 'allowance')->sum('amount');
    }

    public function totalDeductions(): float
    {
        return (float) $this->fixed_deduction
            + (float) $this->items->where('item_type', 'deduction')->sum('amount');
    }

    public function grossSalary(): float
    {
        return (float) $this->basic_salary + $this->totalAllowances();
    }

    public function netSalary(): float
    {
        return $this->grossSalary() - $this->totalDeductions();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function items()
    {
        return $this->hasMany(SalaryStructureItem::class);
    }
}
