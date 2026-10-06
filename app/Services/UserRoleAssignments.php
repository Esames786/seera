<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Caller holds the User row mutex. Never replace the whole pivot collection. */
class UserRoleAssignments
{
    public function primary(User $user, int $roleId): void
    {
        $assigned = $user->roles()->whereKey($roleId)->first();
        if ($assigned?->pivot->is_temporary) {
            throw ValidationException::withMessages(['role_id' => __('workspace.temporary_primary_error')]);
        }
        $user->roles()->newPivotStatement()->where('user_id', $user->id)->update(['is_primary' => false]);
        $user->roles()->syncWithoutDetaching([$roleId => ['is_primary' => true]]);
    }

    public function change(User $user, Role $role, array $data): void
    {
        $assigned = $user->roles()->whereKey($role->id)->first();
        $action = $data['operation'];
        if ($action === 'primary') {
            $this->primary($user, $role->id);
        } elseif ($action === 'remove') {
            if (! $assigned || $assigned->pivot->is_temporary || $assigned->pivot->is_primary) {
                throw ValidationException::withMessages(['role_id' => __('workspace.remove_role_error')]);
            }
            $user->roles()->detach($role->id);
        } elseif ($action === 'permanent') {
            if ($assigned) {
                throw ValidationException::withMessages(['role_id' => __('workspace.assignment_exists')]);
            }
            $user->roles()->attach($role->id, ['is_primary' => false, 'is_temporary' => false]);
        } else {
            if ($assigned && ! $assigned->pivot->is_temporary) {
                throw ValidationException::withMessages(['role_id' => __('workspace.assignment_exists')]);
            }
            if ($action === 'end') {
                if (! $assigned) {
                    throw ValidationException::withMessages(['role_id' => __('workspace.assignment_missing')]);
                }
                // End dates are inclusive; yesterday revokes access immediately.
                // Retain the row and original start; audit stores before/after.
                $user->roles()->updateExistingPivot($role->id, ['access_end_date' => today()->subDay()->toDateString()]);
            } else {
                $user->roles()->syncWithoutDetaching([$role->id => [
                    'is_primary' => false, 'is_temporary' => true,
                    'access_start_date' => $data['access_start_date'], 'access_end_date' => $data['access_end_date'],
                ]]);
            }
        }
    }
}
