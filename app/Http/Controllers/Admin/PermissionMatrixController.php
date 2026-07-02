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
            'actions' => ['view', 'create', 'edit', 'delete', 'approve', 'export', 'mobile'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['role_id' => ['required', 'exists:roles,id']]);

        $role = Role::findOrFail($request->integer('role_id'));
        $role->permissions()->sync($request->input('permissions', []));

        ActivityLog::record($request, 'Roles', 'Updated role permissions', $role->name);

        return redirect()
            ->route('admin.roles.permission-matrix', ['role' => $role->id])
            ->with('status', 'Permissions for "'.$role->name.'" saved successfully.');
    }
}
