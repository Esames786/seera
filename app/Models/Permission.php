<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    /**
     * Every action the permission matrix can grant. Keep this in sync with the
     * seeder — the matrix only submits checkboxes for the actions listed here,
     * so an omitted action would be silently revoked on save.
     */
    public const ACTIONS = [
        'view', 'create', 'edit', 'delete', 'approve', 'reject',
        'export', 'mobile', 'post', 'process', 'retry',
        'receive', 'issue', 'transfer', 'adjust',
    ];

    protected $fillable = ['module', 'module_group', 'action'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
