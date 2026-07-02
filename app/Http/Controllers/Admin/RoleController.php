<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $roles = Role::with(['department', 'parent'])
            ->withCount('users')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('scope'), fn ($q) => $q->where('access_scope', 'like', '%'.$request->string('scope').'%'))
            ->orderBy('level')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.roles.index', [
            'roles' => $roles,
            'departments' => Department::orderBy('name')->get(),
            'totalRoles' => Role::count(),
            'activeRoles' => Role::where('status', 'active')->count(),
            'assignedUsers' => User::has('roles')->count(),
            'workflowCount' => \App\Models\ApprovalWorkflow::count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $role = Role::create($data);
        $role->permissions()->sync($request->input('permissions', []));

        ActivityLog::record($request, 'Roles', 'Created role', $role->name);

        return redirect()->route('admin.roles.index')->with('status', 'Role "'.$role->name.'" created successfully.');
    }

    public function show(Role $role): View
    {
        $role->load(['department', 'parent', 'permissions', 'users.site', 'users.project']);

        $workflows = \App\Models\ApprovalWorkflow::whereHas('steps', fn ($q) => $q->where('approver_role_id', $role->id))
            ->with('steps.approverRole')
            ->get();

        return view('admin.roles.show', [
            'role' => $role,
            'workflows' => $workflows,
        ]);
    }

    public function edit(Role $role): View
    {
        $role->load('permissions');

        return view('admin.roles.edit', ['role' => $role] + $this->formOptions());
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validated($request, $role);
        $role->update($data);
        $role->permissions()->sync($request->input('permissions', []));

        ActivityLog::record($request, 'Roles', 'Updated role', $role->name);

        return redirect()->route('admin.roles.index')->with('status', 'Role "'.$role->name.'" updated successfully.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->withErrors(['role' => 'System roles cannot be deleted.']);
        }

        if ($role->users()->exists() || $role->children()->exists()) {
            return back()->withErrors(['role' => 'This role still has assigned users or child roles. Reassign them before deleting.']);
        }

        $name = $role->name;
        $role->delete();

        ActivityLog::record($request, 'Roles', 'Deleted role', $name);

        return redirect()->route('admin.roles.index')->with('status', 'Role "'.$name.'" deleted successfully.');
    }

    public function assignUsers(Request $request): View
    {
        $roles = Role::orderBy('level')->orderBy('name')->get();
        $selectedRole = $request->filled('role')
            ? $roles->firstWhere('id', $request->integer('role'))
            : $roles->firstWhere('code', 'SITE_SUPERVISOR') ?? $roles->first();

        $assignedUsers = $selectedRole
            ? $selectedRole->users()->with(['designation', 'project', 'site'])->get()
            : collect();

        $availableUsers = User::with(['designation', 'project', 'site'])
            ->whereNotIn('id', $assignedUsers->pluck('id'))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->orderBy('name')
            ->get();

        return view('admin.roles.assign-users', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'assignedUsers' => $assignedUsers,
            'availableUsers' => $availableUsers,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function saveAssignedUsers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'temporary_access' => ['nullable', 'boolean'],
            'access_start_date' => ['nullable', 'date'],
            'access_end_date' => ['nullable', 'date', 'after_or_equal:access_start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $role = Role::findOrFail($data['role_id']);
        $userIds = collect($data['user_ids'] ?? [])->map(fn ($id) => (int) $id);

        // Keep is_primary flags for users that stay on the role.
        $existingPrimary = $role->users()
            ->wherePivot('is_primary', true)
            ->pluck('users.id');

        $isTemporary = $request->boolean('temporary_access');
        $pivot = [
            'is_temporary' => $isTemporary,
            'access_start_date' => $isTemporary ? ($data['access_start_date'] ?? null) : null,
            'access_end_date' => $isTemporary ? ($data['access_end_date'] ?? null) : null,
            'reason' => $data['reason'] ?? null,
        ];

        $role->users()->sync(
            $userIds->mapWithKeys(fn ($id) => [$id => $pivot + ['is_primary' => $existingPrimary->contains($id)]])->all()
        );

        ActivityLog::record(
            $request,
            'Roles',
            'Updated role assignments',
            $role->name.': '.$userIds->count().' user(s) assigned'.($isTemporary ? ' (temporary access)' : '')
        );

        return redirect()
            ->route('admin.roles.assign-users', ['role' => $role->id])
            ->with('status', 'Assignments for "'.$role->name.'" saved successfully ('.$userIds->count().' users).');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'unique:roles,code'.($role ? ','.$role->id : '')],
            'department_id' => ['nullable', 'exists:departments,id'],
            'parent_id' => ['nullable', 'exists:roles,id', $role ? 'not_in:'.$role->id : ''],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
            'access_scope' => ['required', 'string', 'max:100'],
            'default_dashboard' => ['nullable', 'string', 'max:100'],
            'mobile_app_access' => ['nullable', 'boolean'],
            'can_approve_child_requests' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function formOptions(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(),
            'parentRoles' => Role::orderBy('level')->orderBy('name')->get(),
            'permissionModules' => Permission::orderBy('id')->get()->groupBy('module'),
        ];
    }
}
