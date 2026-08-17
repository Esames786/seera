<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    public const TYPES = ['branch', 'department', 'project', 'site', 'warehouse'];

    protected $fillable = ['code', 'name', 'type', 'linked_id', 'manager_id', 'status'];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function journalLines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * The Phase 2 master record this cost center mirrors, if it still exists.
     */
    public function linkedRecord(): ?Model
    {
        if (! $this->linked_id) {
            return null;
        }

        return match ($this->type) {
            'branch' => Branch::find($this->linked_id),
            'department' => Department::find($this->linked_id),
            'project' => Project::find($this->linked_id),
            'site' => Site::find($this->linked_id),
            'warehouse' => Warehouse::find($this->linked_id),
            default => null,
        };
    }
}
