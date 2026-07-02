<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ApprovalWorkflow;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'totalStaff' => User::count(),
            'activeRoles' => Role::where('status', 'active')->count(),
            'pendingApprovals' => ApprovalWorkflow::count() + User::where('status', 'pending')->count(),
            'inactiveUsers' => User::whereIn('status', ['inactive', 'locked'])->count(),
            'totalProjects' => Project::count(),
            'activeSites' => Site::where('status', 'active')->count(),
            'geoFencedSites' => Site::where('geofence_enabled', true)->count(),
            'recentLogs' => ActivityLog::with('user')->latest('created_at')->limit(6)->get(),
            'projects' => Project::with(['customer', 'manager'])->latest()->limit(5)->get(),
        ]);
    }
}
