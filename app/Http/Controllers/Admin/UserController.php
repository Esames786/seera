<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    /** Issued when an account is created without an explicit password. */
    public const DEFAULT_PASSWORD = '123456';

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

    public function employeeSearch(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('HR', 'view') && $request->user()->hasPermission('HR', 'edit'), 403);
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = '%'.str_replace(['%', '_'], '', trim($request->string('q'))).'%';
        $employees = Employee::whereNull('user_id')->where('status', 'active')
            ->whereNotIn('employee_code', User::whereNotNull('employee_id')->select('employee_id'))
            ->where(fn ($q) => $q->where('employee_code', 'like', $term)->orWhere('email', 'like', $term)
                ->orWhere(function ($name) use ($term) {
                    foreach (preg_split('/\s+/u', trim($term, '% '), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                        $name->where(fn ($part) => $part->where('first_name', 'like', '%'.$word.'%')->orWhere('last_name', 'like', '%'.$word.'%'));
                    }
                }))
            ->orderBy('employee_code')->limit(15)->get();

        return response()->json(['data' => $employees->map(fn (Employee $employee) => [
            'id' => $employee->id, 'employee_code' => $employee->employee_code, 'name' => $employee->name,
            'fields' => [
                'name' => $employee->name, 'employee_id' => $employee->employee_code,
                'email' => $employee->email, 'phone' => $employee->phone,
                'department_id' => $employee->department_id, 'designation_id' => $employee->designation_id,
                'branch_id' => $employee->branch_id, 'project_id' => $employee->project_id, 'site_id' => $employee->site_id,
                'employee_classification' => $employee->employee_classification,
                'joining_date' => $employee->joining_date?->toDateString(), 'contract_type' => $employee->contract_type,
                'iqama_number' => $employee->iqama_number, 'iqama_expiry_date' => $employee->iqama_expiry_date?->toDateString(),
            ],
        ])]);
    }

    /**
     * Also serves the "+ New" dialog on the project form (Project Manager) as
     * JSON. An account created without a password gets the shared default and
     * must choose its own on first sign-in.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate(['source_employee_id' => ['nullable', 'integer']]);
        $source = null;
        if ($request->filled('source_employee_id')) {
            abort_unless($request->user()->hasPermission('HR', 'view') && $request->user()->hasPermission('HR', 'edit'), 403);
            $source = Employee::findOrFail($request->integer('source_employee_id'));
            $request->merge(['employee_id' => $source->employee_code, 'employee_classification' => $source->employee_classification]);
            $request->validate(['password' => ['nullable', 'string', 'min:8']]);
        }
        $data = $this->validated($request);
        $roleId = $data['role_id'];
        unset($data['role_id']);

        $password = $request->filled('password') ? $request->input('password') : self::DEFAULT_PASSWORD;
        $data['must_change_password'] = ! $request->filled('password');

        $user = DB::transaction(function () use ($data, $roleId, $password, $source) {
            if ($source) {
                $source = Employee::whereKey($source->id)->lockForUpdate()->firstOrFail();
                if ($source->user_id || $source->status !== 'active' || $source->employee_code !== $data['employee_id']) {
                    throw ValidationException::withMessages(['source_employee_id' => 'This employee is no longer available. Search again before saving.']);
                }
            }
            $user = User::create($data + ['password' => $password]);
            $user->roles()->attach($roleId, ['is_primary' => true]);
            if ($source) {
                $source->update(['user_id' => $user->id]);
            }
            $this->syncEmployeeClassification($user);

            return $user;
        });

        ActivityLog::record($request, 'Users', 'Created user', $user->name.' ('.$user->email.')');

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $user->id,
                'label' => $user->name,
                'email' => $user->email,
                'must_change_password' => $user->must_change_password,
            ], 201);
        }

        return redirect()->route($request->input('_save_action') === 'stay' ? 'admin.users.edit' : 'admin.users.index', $request->input('_save_action') === 'stay' ? [$user] : [])->with('status', 'User "'.$user->name.'" created successfully.');
    }

    public function show(User $user): View
    {
        $user->load(['department', 'designation', 'branch', 'project', 'site', 'warehouse', 'roles.parent', 'roles.permissions', 'employee']);

        return view('admin.users.show', [
            'user' => $user,
            'recentLogs' => $user->activityLogs()->latest('created_at')->limit(6)->get(),
        ]);
    }

    public function edit(User $user): View
    {
        $user->load(['roles', 'employee']);

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

        DB::transaction(function () use ($user, $data, $roleId) {
            $user->update($data);
            $user->roles()->sync([$roleId => ['is_primary' => true]]);
            $this->syncEmployeeClassification($user);
        });

        ActivityLog::record($request, 'Users', 'Updated user', $user->name.' ('.$user->email.')');

        return redirect()->route($request->input('_save_action') === 'stay' ? 'admin.users.edit' : 'admin.users.index', $request->input('_save_action') === 'stay' ? [$user] : [])->with('status', 'User "'.$user->name.'" updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Deactivate instead of hard delete so history stays intact for audits.
        $user->update(['status' => 'inactive']);

        ActivityLog::record($request, 'Users', 'Deactivated user', $user->name.' ('.$user->email.')');

        return redirect()->route('admin.users.index')->with('status', 'User "'.$user->name.'" has been deactivated.');
    }

    /**
     * One answer per person: the classification chosen on the user screen is
     * copied onto the linked HR employee record, if there is one.
     */
    private function syncEmployeeClassification(User $user): void
    {
        if (! $user->employee_classification) {
            return;
        }

        Employee::where('user_id', $user->id)
            ->where('employee_classification', '!=', $user->employee_classification)
            ->update(['employee_classification' => $user->employee_classification]);
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
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where(fn ($query) => $query->where('department_id', $request->input('department_id')))],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', Rule::exists('sites', 'id')->where(fn ($query) => $query->where('project_id', $request->input('project_id')))],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'joining_date' => ['nullable', 'date', 'before_or_equal:today'],
            'contract_type' => ['nullable', 'string', 'max:50'],
            'employee_classification' => ['nullable', Rule::in(Employee::CLASSIFICATIONS)],
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
            'roleTypes' => Role::TYPES,
            'classifications' => Employee::CLASSIFICATIONS,
        ];
    }
}
