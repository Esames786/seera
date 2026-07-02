<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $fillable = [
        'name', 'department_id', 'grade', 'default_role_id',
        'mobile_access_default', 'description', 'status',
    ];

    protected function casts(): array
    {
        return ['mobile_access_default' => 'boolean'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function defaultRole()
    {
        return $this->belongsTo(Role::class, 'default_role_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
