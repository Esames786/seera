<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

/** Ordinary permission grants must never be a route to Super Admin identity. */
final class SuperAdminProtection
{
    public static function role(User $actor, Role $role): void
    {
        abort_if($role->code === 'SUPER_ADMIN' && ! $actor->isSuperAdmin(), 403);
    }

    public static function user(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() || ! $target->roles()->where('code', 'SUPER_ADMIN')->exists();
    }
}
