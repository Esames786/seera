<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeShiftAssignment extends Model
{
    protected $fillable = [
        'employee_id', 'shift_id', 'effective_from', 'effective_to', 'status',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
