<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    /**
     * Standard role types. Combined with the department they give a consistent
     * name and code (Purchase + Assistant => "Purchase Assistant" /
     * PURCHASE_ASSISTANT) so the same job is never spelt three ways.
     */
    public const TYPES = [
        'Manager', 'Assistant', 'Supervisor', 'In-Charge', 'Officer',
        'Coordinator', 'Engineer', 'Accountant', 'Operator', 'Mechanic',
        'Store Keeper', 'Worker',
    ];

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

    /**
     * Ids of every role beneath this one in the reporting hierarchy.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = static::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
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
