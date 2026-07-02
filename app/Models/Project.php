<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name', 'code', 'customer_id', 'branch_id', 'manager_id',
        'start_date', 'end_date', 'budget', 'location', 'description', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }
}
