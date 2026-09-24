<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\User;
use App\Services\UserAccessScopeService;
use Illuminate\Database\Eloquent\Builder;

/** Read-only links. Never infer or repair a relationship from a name or code. */
class LinkedIdentityNavigation
{
    public function users(User $actor): Builder
    {
        $query = User::query();
        $scope = $actor->effectiveAccessScope();
        match ($scope) {
            'company' => null,
            'project' => $query->whereIn('project_id', app(UserAccessScopeService::class)->projectIdsFor($actor)),
            'site' => $actor->site_id ? $query->where('site_id', $actor->site_id) : $query->whereRaw('1 = 0'),
            'warehouse' => $actor->warehouse_id ? $query->where('warehouse_id', $actor->warehouse_id) : $query->whereRaw('1 = 0'),
            default => $query->whereRaw('1 = 0'),
        };

        return $query;
    }

    public function canUser(User $actor, User $target, string $action): bool
    {
        return $actor->hasPermission('Users', $action) && $this->users($actor)->whereKey($target->id)->exists();
    }

    public function userCard(Employee $employee, User $actor): array
    {
        if (! $employee->user_id) {
            return ['state' => 'unlinked'];
        }
        $target = $this->users($actor)->find($employee->user_id);
        if (! $target || (! $actor->hasPermission('Users', 'view') && ! $actor->hasPermission('Users', 'edit'))) {
            return ['state' => 'unavailable'];
        }
        if (Employee::withoutGlobalScopes()->where('user_id', $target->id)->count() !== 1) {
            return ['state' => 'inconsistent'];
        }

        return $this->card($target->name, $target->status,
            $actor->hasPermission('Users', 'view') ? route('admin.users.show', $target) : null,
            $actor->hasPermission('Users', 'edit') ? route('admin.users.edit', $target) : null);
    }

    public function employeeCard(User $user, User $actor): array
    {
        if (! $actor->hasPermission('HR', 'view') && ! $actor->hasPermission('HR', 'edit')) {
            return ['state' => 'unavailable'];
        }
        $query = Employee::withoutGlobalScopes()->where('user_id', $user->id);
        $count = (clone $query)->count();
        if ($count === 0) {
            return ['state' => 'unlinked'];
        }
        // Apply the actor's HR scope explicitly before disclosing any identity.
        app(UserAccessScopeService::class)->apply($query, new Employee, $actor);
        $employee = $query->first();
        if (! $employee) {
            return ['state' => 'unavailable'];
        }
        if ($count !== 1) {
            return ['state' => 'inconsistent'];
        }

        return $this->card($employee->name, $employee->status,
            $actor->hasPermission('HR', 'view') ? route('admin.hr.employees.show', $employee) : null,
            $actor->hasPermission('HR', 'edit') ? route('admin.hr.employees.edit', $employee) : null);
    }

    private function card(string $name, string $status, ?string $viewUrl, ?string $editUrl): array
    {
        return ['state' => 'linked', 'name' => $name, 'status' => $status, 'view_url' => $viewUrl, 'edit_url' => $editUrl];
    }
}
