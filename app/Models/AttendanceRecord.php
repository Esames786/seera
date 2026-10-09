<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    public const STATUSES = ['present', 'late', 'absent', 'leave', 'half day'];

    /** Sources HR may type on a manual record. `gps` is written by the mobile runtime only. */
    public const SOURCES = ['manual', 'mobile', 'offline'];

    public const SOURCE_GPS = 'gps';

    /** Labels HR may type on a manual record. The runtime adds its own computed values below. */
    public const GEOFENCE_STATUSES = ['inside', 'outside', 'unknown'];

    public const GEOFENCE_INSIDE = 'inside';

    public const GEOFENCE_OUTSIDE = 'outside';

    public const GEOFENCE_NOT_ENFORCED = 'not_enforced';

    public const GEOFENCE_LOCATION_UNAVAILABLE = 'location_unavailable';

    protected $fillable = [
        'employee_id', 'project_id', 'site_id', 'shift_id', 'attendance_date',
        'check_in', 'check_out', 'late_minutes', 'overtime_minutes',
        'status', 'source', 'geofence_status', 'remarks',
        'check_in_latitude', 'check_in_longitude', 'check_in_accuracy_meters', 'check_in_distance_meters', 'check_in_geofence_status', 'check_in_recorded_at',
        'check_out_latitude', 'check_out_longitude', 'check_out_accuracy_meters', 'check_out_distance_meters', 'check_out_geofence_status', 'check_out_recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_recorded_at' => 'datetime',
            'check_out_recorded_at' => 'datetime',
        ];
    }

    /** True for records written by the location-validated mobile runtime. */
    public function isGpsRecord(): bool
    {
        return $this->source === self::SOURCE_GPS;
    }

    public function isOpenForCheckOut(): bool
    {
        return $this->isGpsRecord() && $this->check_in !== null && $this->check_out === null;
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_GPS => 'GPS / Mobile',
            'manual' => 'Manual',
            'mobile' => 'Mobile (typed)',
            'offline' => 'Offline (typed)',
            default => ucfirst((string) $this->source),
        };
    }

    public static function geofenceLabel(?string $status): string
    {
        return match ($status) {
            self::GEOFENCE_INSIDE => 'Inside',
            self::GEOFENCE_OUTSIDE => 'Outside',
            self::GEOFENCE_NOT_ENFORCED => 'Not enforced',
            self::GEOFENCE_LOCATION_UNAVAILABLE => 'Site location unavailable',
            'unknown' => 'Unknown',
            null, '' => '-',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
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
