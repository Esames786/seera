<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $fillable = [
        'name', 'code', 'project_id', 'supervisor_id', 'latitude', 'longitude',
        'geofence_radius', 'geofence_enabled', 'attendance_inside_only',
        'offline_attendance_allowed', 'address', 'status',
    ];

    protected function casts(): array
    {
        return [
            'geofence_enabled' => 'boolean',
            'attendance_inside_only' => 'boolean',
            'offline_attendance_allowed' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }
}
