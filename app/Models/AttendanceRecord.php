<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    public const STATUSES = ['present', 'late', 'absent', 'leave', 'half day'];

    public const SOURCES = ['manual', 'mobile', 'offline'];

    public const GEOFENCE_STATUSES = ['inside', 'outside', 'unknown'];

    protected $fillable = [
        'employee_id', 'project_id', 'site_id', 'shift_id', 'attendance_date',
        'check_in', 'check_out', 'late_minutes', 'overtime_minutes',
        'status', 'source', 'geofence_status', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function overtimeRecords()
    {
        return $this->hasMany(OvertimeRecord::class);
    }
}
