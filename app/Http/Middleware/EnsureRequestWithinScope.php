<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\Site;
use App\Models\Warehouse;
use App\Services\UserAccessScopeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequestWithinScope
{
    public function __construct(private readonly UserAccessScopeService $scopes)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! $request->user()) {
            return $next($request);
        }

        $user = $request->user();
        $scope = $user->effectiveAccessScope();
        if ($scope === 'company') {
            return $next($request);
        }

        $assignedWarehouse = $user->warehouse_id
            ? Warehouse::withoutGlobalScopes()->find($user->warehouse_id)
            : null;

        // A project-level user may act in every project they manage (NR-34), not
        // only the one pinned on their user record.
        $allowedProjectIds = match ($scope) {
            'warehouse' => array_filter([$assignedWarehouse?->project_id]),
            'project' => $this->scopes->projectIdsFor($user),
            default => array_filter([$user->project_id]),
        };
        $defaultProjectId = $scope === 'warehouse' ? $assignedWarehouse?->project_id : $this->scopes->defaultProjectIdFor($user);
        $allowedSiteId = $scope === 'warehouse' ? $assignedWarehouse?->site_id : $user->site_id;

        abort_if(
            $request->routeIs('admin.master.projects.store'),
            403,
            'Only company-level users can create projects.'
        );

        if ($scope === 'project' && ! $request->filled('project_id')) {
            $request->merge(['project_id' => $defaultProjectId]);
        } elseif ($scope === 'site') {
            $request->merge([
                'project_id' => $request->filled('project_id') ? $request->input('project_id') : $defaultProjectId,
                'site_id' => $request->filled('site_id') ? $request->input('site_id') : $allowedSiteId,
            ]);
        } elseif ($scope === 'warehouse' && ! $request->filled('warehouse_id')) {
            $request->merge(['warehouse_id' => $user->warehouse_id]);
        }

        if ($request->filled('project_id')) {
            abort_unless(in_array((int) $request->input('project_id'), array_map('intval', $allowedProjectIds), true), 403, 'The selected project is outside your access scope.');
        }

        if ($request->filled('site_id')) {
            $site = Site::withoutGlobalScopes()->find($request->integer('site_id'));
            $allowed = $scope === 'project'
                ? $site && in_array((int) $site->project_id, array_map('intval', $allowedProjectIds), true)
                : $site && (int) $site->id === (int) $allowedSiteId;
            abort_unless($allowed, 403, 'The selected site is outside your access scope.');
        }

        foreach (['warehouse_id', 'from_warehouse_id'] as $field) {
            if (! $request->filled($field)) {
                continue;
            }
            $warehouse = Warehouse::withoutGlobalScopes()->find($request->integer($field));
            $allowed = match ($scope) {
                'project' => $warehouse && in_array((int) $warehouse->project_id, array_map('intval', $allowedProjectIds), true),
                'site' => $warehouse && (int) $warehouse->site_id === (int) $allowedSiteId,
                'warehouse' => $warehouse && (int) $warehouse->id === (int) $user->warehouse_id,
                default => false,
            };
            abort_unless($allowed, 403, 'The selected warehouse is outside your access scope.');
        }

        if ($request->filled('to_warehouse_id') && $scope !== 'warehouse') {
            $warehouse = Warehouse::withoutGlobalScopes()->find($request->integer('to_warehouse_id'));
            $allowed = $scope === 'project'
                ? $warehouse && in_array((int) $warehouse->project_id, array_map('intval', $allowedProjectIds), true)
                : $warehouse && (int) $warehouse->site_id === (int) $allowedSiteId;
            abort_unless($allowed, 403, 'The destination warehouse is outside your access scope.');
        }

        if ($request->filled('employee_id')) {
            $employee = Employee::withoutGlobalScopes()->find($request->integer('employee_id'));
            $allowed = match ($scope) {
                'project' => $employee && in_array((int) $employee->project_id, array_map('intval', $allowedProjectIds), true),
                'site' => $employee && (int) $employee->site_id === (int) $allowedSiteId,
                default => false,
            };
            abort_unless($allowed, 403, 'The selected employee is outside your access scope.');
        }

        return $next($request);
    }
}
