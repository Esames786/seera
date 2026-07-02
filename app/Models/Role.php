<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name', 'code', 'department_id', 'parent_id', 'level', 'access_scope',
        'default_dashboard', 'mobile_app_access', 'can_approve_child_requests',
        'is_system', 'description', 'status',
    ];

    protected function casts(): array
    {
        return [
            'mobile_app_access' => 'boolean',
            'can_approve_child_requests' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function parent()
    {
        return $this->belongsTo(Role::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Role::class, 'parent_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['is_primary', 'is_temporary', 'access_start_date', 'access_end_date'])
            ->withTimestamps();
    }
}
