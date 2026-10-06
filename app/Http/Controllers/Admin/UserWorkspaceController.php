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
use App\Services\UserRoleAssignments;
use App\Support\LinkedIdentityNavigation;
use App\Support\SaveAction;
use App\Support\Workspace\UserWorkspacePanels as Panels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserWorkspaceController extends Controller
{
    public function panel(Request $request, User $user, string $panel)
    {
        $this->authorize($request, $user, $panel, false);
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'user_id' => ['prohibited']]);
        $manage = $request->boolean('manage') && app(LinkedIdentityNavigation::class)->canUser($request->user(), $user, 'edit');
        $data = compact('user', 'panel', 'manage') + ['rows' => null];
        if (in_array($panel, ['roles', 'temporary'])) {
            $query = $user->roles()->with(['parent', 'additionalParents', 'permissions']);
            if ($panel === 'temporary') {
                $query->wherePivot('is_temporary', true);
            }
            $data['rows'] = $query->orderBy('roles.name')->paginate(10)->withQueryString();
            $data['roles'] = $manage && $request->user()->hasPermission('Roles', 'process') ? Role::orderBy('name')->get() : collect();
        } elseif ($panel === 'employment') {
            $user->load(['department', 'designation', 'branch']);
            $data += ['departments' => Department::orderBy('name')->get(), 'designations' => Designation::orderBy('name')->get(), 'branches' => Branch::orderBy('name')->get()];
        } elseif ($panel === 'employee') {
            $data['linkedEmployee'] = app(LinkedIdentityNavigation::class)->employeeCard($user, $request->user());
            $data['employee'] = Employee::where('user_id', $user->id)->first();
        } elseif ($panel === 'scope') {
            $user->load(['project', 'site', 'warehouse', 'branch']);
            $data += ['projects' => Project::orderBy('name')->get(), 'sites' => Site::orderBy('name')->get(), 'warehouses' => Warehouse::orderBy('name')->get()];
        } elseif ($panel === 'activity') {
            // These are actions PERFORMED BY this login, not inferred subject history.
            $data['rows'] = ActivityLog::visibleTo($request->user())->where('user_id', $user->id)->latest('id')->paginate(10)->withQueryString();
        }
        $html = view('admin.users._panel', $data)->render();

        return $request->wantsJson() ? response()->json(['html' => $html]) : view('admin.users.panel', $data);
    }

    private function authorize(Request $request, User $user, string $panel, bool $write): void
    {
        $definition = Panels::definition($panel);
        abort_unless($definition, 404);
        abort_unless(app(LinkedIdentityNavigation::class)->canUser($request->user(), $user, $write ? 'edit' : 'view') && $definition->canView($request->user()), 403);
        if ($write && in_array($panel, ['roles', 'temporary'])) {
            abort_unless($request->user()->hasPermission('Roles', 'process'), 403);
        }
        if ($write && $panel === 'employee') {
            abort_unless($request->user()->hasPermission('HR', 'edit'), 403);
        }
    }

    public function save(Request $request, User $user, string $panel)
    {
        $this->authorize($request, $user, $panel, true);
        $request->validate(['user_id' => ['prohibited']]);
        abort_if($panel === 'activity', 405);
        DB::transaction(function () use ($request, $user, $panel) {
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (in_array($panel, ['roles', 'temporary'])) {
                $data = $request->validate(['role_id' => ['required', 'integer', 'exists:roles,id'],
                    'operation' => ['required', Rule::in($panel === 'roles' ? ['primary', 'permanent', 'remove'] : ['temporary', 'end'])],
                    'access_start_date' => ['required_if:operation,temporary', 'nullable', 'date'],
                    'access_end_date' => ['required_if:operation,temporary', 'nullable', 'date', 'after_or_equal:access_start_date']]);
                $role = Role::findOrFail($data['role_id']);
                $before = $target->roles()->whereKey($role->id)->first()?->pivot->getAttributes();
                app(UserRoleAssignments::class)->change($target, $role, $data);
                $log = ActivityLog::record($request, 'Users', 'Changed role assignment', '[User #'.$target->id.'] role #'.$role->id.' '.$data['operation']);
                $log->update(['old_value' => json_encode($before), 'new_value' => json_encode($target->roles()->whereKey($role->id)->first()?->pivot->getAttributes())]);
            } elseif ($panel === 'employee') {
                $data = $request->validate(['source_employee_id' => ['required', 'integer'], 'operation' => ['required', 'in:link,unlink']]);
                $employee = Employee::whereKey($data['source_employee_id'])->lockForUpdate()->firstOrFail();
                $links = Employee::withoutGlobalScopes()->where('user_id', $target->id)->pluck('id');
                if ($data['operation'] === 'unlink') {
                    abort_unless($employee->user_id === $target->id && $links->count() === 1, 422);
                    $employee->update(['user_id' => null]);
                    // The human-readable code is not itself a relationship; keep it for audit/reference.
                } else {
                    if ($employee->user_id || $links->isNotEmpty() || $employee->status !== 'active' || User::where('employee_id', $employee->employee_code)->whereKeyNot($target->id)->exists()) {
                        throw ValidationException::withMessages(['source_employee_id' => __('workspace.link_conflict')]);
                    }
                    $employee->update(['user_id' => $target->id]);
                    $target->update(['employee_id' => $employee->employee_code]);
                }
            } elseif ($panel === 'employment') {
                if ($request->exists('employee_classification')) {
                    abort_unless($request->user()->hasPermission('HR', 'edit') && $request->user()->hasPermission('HR', 'view'), 403);
                }
                $data = $request->validate(['department_id' => ['nullable', 'exists:departments,id'], 'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('department_id', $request->input('department_id'))], 'branch_id' => ['nullable', 'exists:branches,id'],
                    'employee_classification' => ['sometimes', 'nullable', Rule::in(Employee::CLASSIFICATIONS)],
                    'joining_date' => ['nullable', 'date', 'before_or_equal:today'], 'contract_type' => ['nullable', 'string', 'max:50'], 'iqama_number' => ['nullable', 'string', 'max:50'], 'iqama_expiry_date' => ['nullable', 'date']]);
                $target->update($data);
                if (! empty($data['employee_classification'])) {
                    Employee::where('user_id', $target->id)->update(['employee_classification' => $data['employee_classification']]);
                }
            } elseif ($panel === 'scope') {
                $data = $request->validate(['project_id' => ['nullable', 'integer'], 'site_id' => ['nullable', 'integer'], 'warehouse_id' => ['nullable', 'integer']]);
                self::validateScope($data);
                $target->update(array_merge(['project_id' => null, 'site_id' => null, 'warehouse_id' => null], $data));
            } elseif ($panel === 'mobile') {
                $target->update($request->validate(['mobile_access' => ['required', 'boolean']]));
            } elseif ($panel === 'security') {
                $data = $request->validate(['status' => ['required', 'in:active,inactive,locked,pending'], 'password' => ['nullable', 'string', 'min:8', 'confirmed']]);
                if (empty($data['password'])) {
                    unset($data['password']);
                } else {
                    $data['must_change_password'] = true;
                }
                $target->update($data);
            }
            if (! in_array($panel, ['roles', 'temporary'])) {
                ActivityLog::record($request, 'Users', 'Updated '.$panel, '[User #'.$target->id.']');
            }
        }, 3);
        $url = route('admin.users.workspace.panel', [$user, $panel, 'manage' => 1, 'return_to' => SaveAction::returnTo($request)]);
        if ($request->wantsJson()) {
            $user = $user->fresh('roles');
            $linkedEmployee = $request->user()->hasPermission('HR', 'view') ? app(LinkedIdentityNavigation::class)->employeeCard($user, $request->user()) : ['state' => 'unavailable'];

            return response()->json(['panel_url' => $url, 'identity_html' => view('admin.users._identity', compact('user', 'linkedEmployee') + ['manage' => true])->render()]);
        }

        return SaveAction::redirect($request, ['stay' => route('admin.users.edit', [$user, 'return_to' => SaveAction::returnTo($request)]).'#'.$panel, 'close' => route('admin.users.index')])->with('status', __('workspace.saved'));
    }

    public static function validateScope(array $data): void
    {
        if (! empty($data['project_id'])) {
            Project::findOrFail($data['project_id']);
        }
        if (! empty($data['site_id'])) {
            Site::whereKey($data['site_id'])->where('project_id', $data['project_id'] ?? null)->firstOrFail();
        }
        if (! empty($data['warehouse_id'])) {
            Warehouse::whereKey($data['warehouse_id'])->where('project_id', $data['project_id'] ?? null)
                ->when(! empty($data['site_id']), fn ($q) => $q->where('site_id', $data['site_id']))->firstOrFail();
        }
    }
}
