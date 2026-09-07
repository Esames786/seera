<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionGroups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissionMatrixController extends Controller
{
    public function index(Request $request): View
    {
        $roles = Role::with('department')->orderBy('level')->orderBy('name')->get();
        $selectedRole = $request->filled('role')
            ? $roles->firstWhere('id', $request->integer('role'))
            : $roles->first();

        // Default to the modules relevant to the role's department; "all"
        // shows the complete catalogue.
        $group = $request->filled('group')
            ? (string) $request->string('group')
            : ($selectedRole?->department?->code ?? 'all');
        $groupModules = $group === 'all' ? null : PermissionGroups::modulesForCode($group);
        if ($groupModules === null) {
            $group = 'all';
        }

        $permissions = Permission::orderBy('id')
            ->when($groupModules !== null, fn ($q) => $q->whereIn('module', $groupModules))
            ->when($request->filled('search'), fn ($q) => $q->where('module', 'like', '%'.$request->string('search').'%'))
            ->get()
            ->groupBy('module');

        return view('admin.roles.permission-matrix', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'permissionsByModule' => $permissions,
            'grantedIds' => $selectedRole ? $selectedRole->permissions->pluck('id')->all() : [],
            'actions' => Permission::ACTIONS,
            'groupOptions' => PermissionGroups::options(),
            'selectedGroup' => $group,
            'totalModules' => count(Permission::MODULES),
        ]);
    }

    /**
     * Only the permissions that were on screen are taken from the submission;
     * everything the filter hid keeps its current state.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'group' => ['nullable', 'string', 'max:20'],
            'visible_permission_ids' => ['nullable', 'array'],
            'visible_permission_ids.*' => ['integer', 'exists:permissions,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = Role::findOrFail($request->integer('role_id'));
        $visibleIds = collect($data['visible_permission_ids'] ?? [])->map(fn ($id) => (int) $id);
        $submittedIds = collect($data['permissions'] ?? [])->map(fn ($id) => (int) $id)->intersect($visibleIds);
        $preservedIds = $role->permissions()->pluck('permissions.id')->diff($visibleIds);

        $role->permissions()->sync($preservedIds->merge($submittedIds)->unique()->values()->all());

        ActivityLog::record($request, 'Roles', 'Updated role permissions', $role->name);

        return redirect()
            ->route('admin.roles.permission-matrix', array_filter([
                'role' => $role->id,
                'group' => $data['group'] ?? null,
                'search' => $data['search'] ?? null,
            ]))
            ->with('status', 'Permissions for "'.$role->name.'" saved successfully.');
    }
}
