<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PayrollRun extends Model
{
    public const STATUSES = ['draft', 'processed', 'approved', 'paid'];

    protected $fillable = [
        'code', 'payroll_month', 'payroll_year', 'period_start', 'period_end',
        'branch_id', 'project_id', 'total_employees', 'gross_amount',
        'total_deductions', 'net_amount', 'status', 'processed_at',
        'approved_by', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'processed_at' => 'datetime',
            'approved_at' => 'datetime',
            'gross_amount' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function periodLabel(): string
    {
        return Carbon::create($this->payroll_year, $this->payroll_month, 1)->format('F Y');
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
}
