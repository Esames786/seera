<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'name', 'code', 'city', 'manager_id', 'phone', 'email', 'address', 'status',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }
}
