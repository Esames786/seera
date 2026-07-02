<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleHierarchyController extends Controller
{
    public function index(Request $request): View
    {
        $roles = Role::with('department')->orderBy('level')->orderBy('name')->get();

        $rootRoles = $roles->whereNull('parent_id');
        $childrenByParent = $roles->whereNotNull('parent_id')->groupBy('parent_id');

        $selectedRole = $request->filled('role')
            ? $roles->firstWhere('id', $request->integer('role'))
            : $roles->firstWhere('code', 'SITE_SUPERVISOR') ?? $roles->first();

        return view('admin.roles.hierarchy', [
            'rootRoles' => $rootRoles,
            'childrenByParent' => $childrenByParent,
            'roles' => $roles,
            'selectedRole' => $selectedRole,
        ]);
    }
}
