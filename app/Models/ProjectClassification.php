<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A user-managed way of grouping projects (client change request CR-15).
 * The client adds their own names from the project form; nothing is preset.
 */
class ProjectClassification extends Model
{
    protected $fillable = ['name', 'description', 'status'];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
