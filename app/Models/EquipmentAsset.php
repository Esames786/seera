<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One equipment or vehicle asset. Current project / site / operator are a
 * snapshot of the active assignment; the history lives in assignments. The
 * meter is the latest valid reading and never moves backwards silently.
 */
class EquipmentAsset extends Model
{
    public const TYPES = ['equipment' => 'Equipment', 'vehicle' => 'Vehicle'];

    public const OWNERSHIP = ['owned' => 'Owned', 'rented' => 'Rented', 'leased' => 'Leased'];

    public const STATUSES = ['available' => 'Available', 'assigned' => 'Assigned', 'under_maintenance' => 'Under Maintenance', 'out_of_service' => 'Out of Service', 'retired' => 'Retired'];

    public const METER_TYPES = ['odometer' => 'Odometer (km)', 'hours' => 'Hour meter (h)', 'none' => 'No meter'];

    protected $fillable = [
        'asset_code', 'name', 'asset_type', 'category', 'make', 'model', 'year', 'registration_number', 'chassis_number', 'serial_number',
        'ownership_type', 'acquisition_date', 'acquisition_cost', 'status', 'current_project_id', 'current_site_id', 'current_operator_id',
        'current_assignment_id', 'meter_type', 'current_meter', 'registration_expiry_date', 'insurance_expiry_date',
        'tracker_provider', 'tracker_device_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'registration_expiry_date' => 'date',
            'insurance_expiry_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'current_meter' => 'decimal:1',
        ];
    }

    public function isVehicle(): bool
    {
        return $this->asset_type === 'vehicle';
    }

    public function label(): string
    {
        return $this->asset_code.' - '.$this->name;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->asset_type] ?? ucfirst((string) $this->asset_type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function meterUnit(): string
    {
        return match ($this->meter_type) {
            'odometer' => 'km',
            'hours' => 'h',
            default => '',
        };
    }

    public function meterLabel(): string
    {
        return $this->meter_type === 'none' ? 'No meter' : number_format((float) $this->current_meter, 1).' '.$this->meterUnit();
    }

    /** Expiry state of a dated document field: expired / expiring (60 days) / valid / unknown. */
    public static function expiryState(?Carbon $date): string
    {
        if (! $date) {
            return 'unknown';
        }
        if ($date->isPast() && ! $date->isToday()) {
            return 'expired';
        }

        return $date->lte(today()->addDays(60)) ? 'expiring' : 'valid';
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'current_project_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'current_site_id');
    }

    public function operator()
    {
        return $this->belongsTo(Employee::class, 'current_operator_id');
    }

    public function assignments()
    {
        return $this->hasMany(EquipmentAssignment::class)->orderByDesc('assigned_from')->orderByDesc('id');
    }

    public function activeAssignment()
    {
        return $this->hasOne(EquipmentAssignment::class)->where('is_active', true)->latestOfMany();
    }

    public function fuelLogs()
    {
        return $this->hasMany(EquipmentFuelLog::class)->orderByDesc('fuel_date')->orderByDesc('id');
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(EquipmentMaintenanceRecord::class)->orderByDesc('service_date')->orderByDesc('id');
    }

    public function documents()
    {
        return $this->hasMany(EquipmentDocument::class)->orderByDesc('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
