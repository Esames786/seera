<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'name', 'code', 'start_time', 'end_time', 'break_minutes',
        'grace_minutes', 'overtime_after_minutes', 'status',
    ];

    public function assignments()
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
