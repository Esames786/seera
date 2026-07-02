<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(Request $request): View
    {
        $sites = Site::with(['project', 'supervisor'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->integer('project')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.master.sites.index', [
            'sites' => $sites,
            'projects' => Project::orderBy('name')->get(),
            'totalSites' => Site::count(),
            'activeSites' => Site::where('status', 'active')->count(),
            'geoFencedSites' => Site::where('geofence_enabled', true)->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.sites.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $site = Site::create($this->validated($request));

        ActivityLog::record($request, 'Sites', 'Created site', $site->name);

        return redirect()->route('admin.master.sites.index')->with('status', 'Site "'.$site->name.'" created successfully.');
    }

    public function show(Site $site): View
    {
        $site->load(['project.customer', 'supervisor', 'warehouses']);

        return view('admin.master.sites.show', ['site' => $site]);
    }

    public function edit(Site $site): View
    {
        return view('admin.master.sites.edit', ['site' => $site] + $this->formOptions());
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $site->update($this->validated($request, $site));

        ActivityLog::record($request, 'Sites', 'Updated site', $site->name);

        return redirect()->route('admin.master.sites.index')->with('status', 'Site "'.$site->name.'" updated successfully.');
    }

    public function destroy(Request $request, Site $site): RedirectResponse
    {
        if ($site->warehouses()->exists()) {
            return back()->withErrors(['site' => 'This site still has warehouses attached.']);
        }

        $name = $site->name;
        $site->delete();

        ActivityLog::record($request, 'Sites', 'Deleted site', $name);

        return redirect()->route('admin.master.sites.index')->with('status', 'Site "'.$name.'" deleted successfully.');
    }

    private function validated(Request $request, ?Site $site = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:sites,code'.($site ? ','.$site->id : '')],
            'project_id' => ['nullable', 'exists:projects,id'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius' => ['nullable', 'integer', 'min:10', 'max:100000'],
            'geofence_enabled' => ['nullable', 'boolean'],
            'attendance_inside_only' => ['nullable', 'boolean'],
            'offline_attendance_allowed' => ['nullable', 'boolean'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,draft,inactive'],
        ]);
    }

    private function formOptions(): array
    {
        return [
            'projects' => Project::orderBy('name')->get(),
            'supervisors' => User::orderBy('name')->get(),
        ];
    }
}
