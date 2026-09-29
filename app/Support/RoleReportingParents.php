<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reporting links do not grant permissions, visibility, or approval authority. */
class RoleReportingParents
{
    /** Serialize graph changes before updating a role, inside the caller's transaction. */
    public static function lock(): void
    {
        Role::orderBy('id')->lockForUpdate()->get(['id']);
    }

    public static function sync(Role $role, array $additionalIds): void
    {
        $additionalIds = array_map('intval', $additionalIds);
        if ($role->parent_id && in_array((int) $role->parent_id, $additionalIds, true)) {
            throw ValidationException::withMessages(['additional_parent_ids' => __('ui.reporting_duplicate')]);
        }

        $parents = [];
        foreach (Role::all(['id', 'parent_id']) as $node) {
            $parents[$node->id] = $node->parent_id ? [(int) $node->parent_id] : [];
        }
        foreach (DB::table('role_reporting_parents')->get() as $edge) {
            $parents[$edge->role_id][] = (int) $edge->parent_role_id;
        }
        $parents[$role->id] = array_merge($role->parent_id ? [(int) $role->parent_id] : [], $additionalIds);
        $frontier = $parents[$role->id];
        $visited = [];
        while ($frontier !== []) {
            $id = array_pop($frontier);
            if ($id === (int) $role->id) {
                throw ValidationException::withMessages(['parent_id' => __('ui.reporting_cycle')]);
            }
            if (isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;
            $frontier = array_merge($frontier, $parents[$id] ?? []);
        }
        $role->additionalParents()->sync($additionalIds);
    }
}
