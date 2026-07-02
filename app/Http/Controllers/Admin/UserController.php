<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::with(['department', 'roles', 'project', 'site', 'branch'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%"));
            })
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('roles.id', $request->integer('role'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('employee_id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'departments' => Department::orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
            'totalUsers' => User::count(),
            'activeUsers' => User::where('status', 'active')->count(),
            'mobileUsers' => User::where('mobile_access', true)->count(),
            'lockedUsers' => User::whereIn('status', ['inactive', 'locked'])->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $roleId = $data['role_id'];
        unset($data['role_id']);

        $user = User::create($data + ['password' => $request->input('password', 'password')]);
        $user->roles()->attach($roleId, ['is_primary' => true]);

        ActivityLog::record($request, 'Users', 'Created user', $user->name.' ('.$user->email.')');

        return redirect()->route('admin.users.index')->with('status', 'User "'.$user->name.'" created successfully.');
    }

    public function show(User $user): View
    {
        $user->load(['department', 'designation', 'branch', 'project', 'site', 'warehouse', 'roles.parent', 'roles.permissions']);

        return view('admin.users.show', [
            'user' => $user,
            'recentLogs' => $user->activityLogs()->latest('created_at')->limit(6)->get(),
        ]);
    }

    public function edit(User $user): View
    {
        $user->load('roles');

        return view('admin.users.edit', ['user' => $user] + $this->formOptions());
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $roleId = $data['role_id'];
        unset($data['role_id']);

        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        $user->update($data);
        $user->roles()->sync([$roleId => ['is_primary' => true]]);

        ActivityLog::record($request, 'Users', 'Updated user', $user->name.' ('.$user->email.')');

        return redirect()->route('admin.users.index')->with('status', 'User "'.$user->name.'" updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Deactivate instead of hard delete so history stays intact for audits.
        $user->update(['status' => 'inactive']);

        ActivityLog::record($request, 'Users', 'Deactivated user', $user->name.' ('.$user->email.')');

        return redirect()->route('admin.users.index')->with('status', 'User "'.$user->name.'" has been deactivated.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'.($user ? ','.$user->id : '')],
            'employee_id' => ['nullable', 'string', 'max:50', 'unique:users,employee_id'.($user ? ','.$user->id : '')],
            'username' => ['nullable', 'string', 'max:100', 'unique:users,username'.($user ? ','.$user->id : '')],
            'phone' => ['nullable', 'string', 'max:30'],
            'language' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'joining_date' => ['nullable', 'date'],
            'contract_type' => ['nullable', 'string', 'max:50'],
            'iqama_number' => ['nullable', 'string', 'max:50'],
            'iqama_expiry_date' => ['nullable', 'date'],
            'mobile_access' => ['nullable', 'boolean'],
            'two_factor_enabled' => ['nullable', 'boolean'],
            'temporary_access' => ['nullable', 'boolean'],
            'access_start_date' => ['nullable', 'date'],
            'access_end_date' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive,locked,pending'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);
    }

    private function formOptions(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'roles' => Role::orderBy('level')->orderBy('name')->get(),
        ];
    }
}
