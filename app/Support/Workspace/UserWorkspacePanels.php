<?php

namespace App\Support\Workspace;

use App\Models\User;

class UserWorkspacePanels implements WorkspacePanels
{
    use PanelSet;

    public static function panels(): array
    {
        $panels = [];
        foreach (['employment' => ['Users', 'employment'], 'employee' => ['HR', 'employee'], 'roles' => ['Roles', 'roles'], 'temporary' => ['Roles', 'temporary'], 'scope' => ['Users', 'scope'], 'mobile' => ['Users', 'mobile'], 'security' => ['Users', 'security'], 'activity' => ['Activity Logs', 'activity']] as $key => [$module, $label]) {
            $panels[$key] = new PanelDefinition($key, 'workspace.'.$label, $module);
        }

        return $panels;
    }

    public static function state(User $user, $role): string
    {
        if ($role->status !== 'active') {
            return 'inactive';
        }
        if ($role->pivot->is_temporary && $role->pivot->access_end_date && $role->pivot->access_end_date < today()->toDateString()) {
            return 'expired';
        }
        if ($role->pivot->is_temporary && $role->pivot->access_start_date && $role->pivot->access_start_date > today()->toDateString()) {
            return 'scheduled';
        }

        return $user->hasEffectiveRole($role->id) ? 'active' : 'inactive';
    }
}
