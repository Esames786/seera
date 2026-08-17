<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EndOfServiceRecord extends Model
{
    public const STATUSES = ['draft', 'approved', 'paid'];

    protected $fillable = [
        'employee_id', 'termination_date', 'service_years', 'last_basic_salary',
        'eosb_amount', 'leave_salary', 'other_dues', 'deductions', 'final_amount',
        'reason', 'status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'termination_date' => 'date',
            'approved_at' => 'datetime',
            'service_years' => 'decimal:2',
            'last_basic_salary' => 'decimal:2',
            'eosb_amount' => 'decimal:2',
            'leave_salary' => 'decimal:2',
            'other_dues' => 'decimal:2',
            'deductions' => 'decimal:2',
            'final_amount' => 'decimal:2',
        ];
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
