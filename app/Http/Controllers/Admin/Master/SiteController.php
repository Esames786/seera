<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Site;
use App\Support\LinkedIdentityNavigation;
use App\Support\SaveAction;
use App\Support\Workspace\SiteWorkspacePanels as Panels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(in_array($request->user()->effectiveAccessScope(), ['company', 'project'], true), 403);
        if ($request->wantsJson()) {
            // A site/warehouse-scoped operator cannot assign a newly created
            // site; inline creation must not offer a scope escape.
            abort_unless(in_array($request->user()->effectiveAccessScope(), ['company', 'project'], true), 403);
        }
        $site = Site::create($this->validated($request));

        ActivityLog::record($request, 'Sites', 'Created site', '[Site #'.$site->id.'] '.$site->code);

        if ($request->wantsJson()) {
            return response()->json(['id' => $site->id, 'label' => $site->name, 'parent' => $site->project_id], 201);
        }

        return $this->saved($request, $site)->with('status', 'Site "'.$site->name.'" created successfully.');
    }

    public function show(Site $site): View
    {
        return view('admin.master.sites.show', $this->workspaceData($site));
    }

    public function edit(Site $site): View
    {
        return view('admin.master.sites.edit', $this->workspaceData($site) + $this->formOptions());
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $site->update($this->validated($request, $site));

        ActivityLog::record($request, 'Sites', 'Updated site', '[Site #'.$site->id.'] '.$site->code);

        return $this->saved($request, $site)->with('status', 'Site "'.$site->name.'" updated successfully.');
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

    protected function validated(Request $request, ?Site $site = null): array
    {
        if ($site) {
            if ($request->exists('project_id') && (string) $request->input('project_id') !== (string) $site->project_id) {
                throw ValidationException::withMessages(['project_id' => __('workspace.site_project_fixed')]);
            }
            $request->merge(['project_id' => $site->project_id]);
        }
        if ($request->filled('project_id')) {
            Project::findOrFail($request->integer('project_id'));
        }
        // Preserve the existing reference even when its User is outside the
        // editor's User scope. New assignments still require visible Users.
        if ($request->filled('supervisor_id') && (int) $request->input('supervisor_id') !== (int) $site?->supervisor_id) {
            abort_unless(app(LinkedIdentityNavigation::class)->users($request->user())->whereKey($request->integer('supervisor_id'))->exists(), 403);
        }

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
            'supervisors' => app(LinkedIdentityNavigation::class)->users(auth()->user())->orderBy('name')->get(),
        ];
    }

    protected function workspaceData(Site $site): array
    {
        $site->load(['project', 'supervisor']);
        $panels = auth()->user()->hasPermission('Sites', 'view') ? Panels::visibleDefinitions(auth()->user()) : [];
        $summary = [];
        foreach (['staff', 'warehouses'] as $key) {
            if (isset($panels[$key])) {
                $summary[$key] = Panels::query($site, $key, auth()->user())->count();
            }
        }

        return compact('site', 'panels', 'summary');
    }

    private function saved(Request $request, Site $site): RedirectResponse
    {
        return SaveAction::redirect($request, ['stay' => route('admin.master.sites.edit', [$site, 'return_to' => SaveAction::returnTo($request)]), 'close' => route('admin.master.sites.index')]
            + ($request->user()->hasPermission('Sites', 'create') ? ['new' => route('admin.master.sites.create')] : []));
    }
}
