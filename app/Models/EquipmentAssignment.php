<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One period during which an asset was assigned to a project (and optionally a site / operator). Never deleted. */
class EquipmentAssignment extends Model
{
    protected $fillable = [
        'equipment_asset_id', 'project_id', 'site_id', 'operator_employee_id', 'assigned_from', 'assigned_until',
        'start_meter', 'end_meter', 'is_active', 'assigned_by', 'closed_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_from' => 'date',
            'assigned_until' => 'date',
            'start_meter' => 'decimal:1',
            'end_meter' => 'decimal:1',
            'is_active' => 'boolean',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(EquipmentAsset::class, 'equipment_asset_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function operator()
    {
        return $this->belongsTo(Employee::class, 'operator_employee_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
