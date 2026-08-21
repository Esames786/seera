<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissionMatrixController extends Controller
{
    public function index(Request $request): View
    {
        $roles = Role::orderBy('level')->orderBy('name')->get();
        $selectedRole = $request->filled('role')
            ? $roles->firstWhere('id', $request->integer('role'))
            : $roles->first();

        $permissions = Permission::orderBy('id')
            ->when($request->filled('search'), fn ($q) => $q->where('module', 'like', '%'.$request->string('search').'%'))
            ->get()
            ->groupBy('module');

        return view('admin.roles.permission-matrix', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'permissionsByModule' => $permissions,
            'grantedIds' => $selectedRole ? $selectedRole->permissions->pluck('id')->all() : [],
            'actions' => Permission::ACTIONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'visible_permission_ids' => ['nullable', 'array'],
            'visible_permission_ids.*' => ['integer', 'exists:permissions,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = Role::findOrFail($request->integer('role_id'));
        $visibleIds = Permission::query()
            ->when(filled($data['search'] ?? null), fn ($query) => $query->where('module', 'like', '%'.$data['search'].'%'))
            ->pluck('id');
        $submittedIds = collect($data['permissions'] ?? [])->map(fn ($id) => (int) $id)->intersect($visibleIds);
        $preservedIds = $role->permissions()->pluck('permissions.id')->diff($visibleIds);

        $role->permissions()->sync($preservedIds->merge($submittedIds)->unique()->all());

        ActivityLog::record($request, 'Roles', 'Updated role permissions', $role->name);

        return redirect()
            ->route('admin.roles.permission-matrix', ['role' => $role->id])
            ->with('status', 'Permissions for "'.$role->name.'" saved successfully.');
    }
}
