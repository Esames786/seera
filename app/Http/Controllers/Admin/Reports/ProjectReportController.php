<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectReportController extends Controller
{
    /**
     * Read-only project portfolio overview. Projects use the existing global
     * user_access scope; no journal/finance numbers are reimplemented here.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,planning,on hold,completed,inactive'],
        ]);

        $query = Project::query()
            ->with(['customer:id,name', 'manager:id,name'])
            ->withCount('sites')
            ->when($filters['search'] ?? null, function ($q, $term) {
                $q->where(function ($nested) use ($term) {
                    $nested->where('code', 'like', '%'.$term.'%')
                        ->orWhere('name', 'like', '%'.$term.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        $count = (clone $query)->count();
        $active = (clone $query)->where('status', 'active')->count();
        $projects = $query->orderBy('code')->paginate(15)->withQueryString();

        return view('admin.reports.projects.index', [
            'projects' => $projects,
            'projectCount' => $count,
            'activeCount' => $active,
            'canViewFinancialReports' => $request->user()->hasPermission('Financial Reports', 'view'),
            'canViewSites' => $request->user()->hasPermission('Sites', 'view'),
        ]);
    }
}
