<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRunItem extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'basic_salary', 'total_allowances',
        'overtime_amount', 'total_deductions', 'gross_amount', 'net_amount',
        'present_days', 'leave_days', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'total_allowances' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
