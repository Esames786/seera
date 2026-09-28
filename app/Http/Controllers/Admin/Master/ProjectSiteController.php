<?php

namespace App\Http\Controllers\Admin\Master;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Support\SaveAction;
use Illuminate\Http\Request;

/** Existing Site form/validation, with an authoritative route parent. */
class ProjectSiteController extends SiteController
{
    private function authorizeContext(Request $request, Project $project, string $action, ?Site $site = null): void
    {
        abort_unless($request->user()->hasPermission('Projects', 'view') && $request->user()->hasPermission('Sites', $action), 403);
        if ($site) {
            abort_unless((int) $site->project_id === $project->id, 404);
        }
        if ($action === 'create') {
            abort_unless(in_array($request->user()->effectiveAccessScope(), ['company', 'project'], true), 403);
        }
    }

    public function createForProject(Request $request, Project $project)
    {
        $this->authorizeContext($request, $project, 'create');

        return $this->form($request, $project);
    }

    public function editForProject(Request $request, Project $project, Site $site)
    {
        $this->authorizeContext($request, $project, 'edit', $site);

        return $this->form($request, $project, $site);
    }

    private function form(Request $request, Project $project, ?Site $site = null)
    {
        $request->query->set('return_to', SaveAction::returnTo($request) ?? route('admin.master.projects.show', $project, false).'#sites');

        return view($site ? 'admin.master.sites.edit' : 'admin.master.sites.create', [
            'site' => $site, 'contextProject' => $project, 'projects' => collect([$project]),
            'supervisors' => User::orderBy('name')->get(),
            'formAction' => $site ? route('admin.master.sites.project.update', [$project, $site]) : route('admin.master.sites.project.store', $project),
        ]);
    }

    public function storeForProject(Request $request, Project $project)
    {
        $this->authorizeContext($request, $project, 'create');

        return $this->saveInProject($request, $project);
    }

    public function updateForProject(Request $request, Project $project, Site $site)
    {
        $this->authorizeContext($request, $project, 'edit', $site);

        return $this->saveInProject($request, $project, $site);
    }

    private function saveInProject(Request $request, Project $project, ?Site $site = null)
    {
        $request->validate(['project_id' => ['nullable', 'integer', 'in:'.$project->id]]);
        $request->merge(['project_id' => $project->id]);
        $values = $this->validated($request, $site);
        if ($site) {
            $site->update($values);
        } else {
            $site = Site::create($values);
        }
        ActivityLog::record($request, 'Projects', 'Saved location', '[Project #'.$project->id.'] '.$project->code.' / Site '.$site->code);
        $origin = SaveAction::returnTo($request) ?? route('admin.master.projects.show', $project, false).'#sites';

        return SaveAction::redirect($request, [
            'stay' => route('admin.master.sites.project.edit', [$project, $site, 'return_to' => $origin]),
            'new' => route('admin.master.sites.project.create', $project),
            'close' => $origin,
        ])->with('status', 'Location saved successfully.');
    }
}
