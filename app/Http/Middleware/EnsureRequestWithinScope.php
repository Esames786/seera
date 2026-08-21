<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\Site;
use App\Models\Warehouse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequestWithinScope
{
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
        $allowedProjectId = $scope === 'warehouse' ? $assignedWarehouse?->project_id : $user->project_id;
        $allowedSiteId = $scope === 'warehouse' ? $assignedWarehouse?->site_id : $user->site_id;

        abort_if(
            $request->routeIs('admin.master.projects.store'),
            403,
            'Only company-level users can create projects.'
        );

        if ($scope === 'project' && ! $request->filled('project_id')) {
            $request->merge(['project_id' => $allowedProjectId]);
        } elseif ($scope === 'site') {
            $request->merge([
                'project_id' => $request->filled('project_id') ? $request->input('project_id') : $allowedProjectId,
                'site_id' => $request->filled('site_id') ? $request->input('site_id') : $allowedSiteId,
            ]);
        } elseif ($scope === 'warehouse' && ! $request->filled('warehouse_id')) {
            $request->merge(['warehouse_id' => $user->warehouse_id]);
        }

        $this->assertId($request->input('project_id'), $allowedProjectId, 'project');

        if ($request->filled('site_id')) {
            $site = Site::withoutGlobalScopes()->find($request->integer('site_id'));
            $allowed = $scope === 'project'
                ? $site && (int) $site->project_id === (int) $allowedProjectId
                : $site && (int) $site->id === (int) $allowedSiteId;
            abort_unless($allowed, 403, 'The selected site is outside your access scope.');
        }

        foreach (['warehouse_id', 'from_warehouse_id'] as $field) {
            if (! $request->filled($field)) {
                continue;
            }
            $warehouse = Warehouse::withoutGlobalScopes()->find($request->integer($field));
            $allowed = match ($scope) {
                'project' => $warehouse && (int) $warehouse->project_id === (int) $allowedProjectId,
                'site' => $warehouse && (int) $warehouse->site_id === (int) $allowedSiteId,
                'warehouse' => $warehouse && (int) $warehouse->id === (int) $user->warehouse_id,
                default => false,
            };
            abort_unless($allowed, 403, 'The selected warehouse is outside your access scope.');
        }

        if ($request->filled('to_warehouse_id') && $scope !== 'warehouse') {
            $warehouse = Warehouse::withoutGlobalScopes()->find($request->integer('to_warehouse_id'));
            $allowed = $scope === 'project'
                ? $warehouse && (int) $warehouse->project_id === (int) $allowedProjectId
                : $warehouse && (int) $warehouse->site_id === (int) $allowedSiteId;
            abort_unless($allowed, 403, 'The destination warehouse is outside your access scope.');
        }

        if ($request->filled('employee_id')) {
            $employee = Employee::withoutGlobalScopes()->find($request->integer('employee_id'));
            $allowed = match ($scope) {
                'project' => $employee && (int) $employee->project_id === (int) $allowedProjectId,
                'site' => $employee && (int) $employee->site_id === (int) $allowedSiteId,
                default => false,
            };
            abort_unless($allowed, 403, 'The selected employee is outside your access scope.');
        }

        return $next($request);
    }

    private function assertId(mixed $submitted, mixed $allowed, string $label): void
    {
        if (filled($submitted)) {
            abort_unless($allowed && (int) $submitted === (int) $allowed, 403, 'The selected '.$label.' is outside your access scope.');
        }
    }
}
