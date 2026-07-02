<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = [
        'name', 'code', 'branch_id', 'project_id', 'site_id', 'incharge_id',
        'valuation_method', 'address', 'status',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function incharge()
    {
        return $this->belongsTo(User::class, 'incharge_id');
    }
}
