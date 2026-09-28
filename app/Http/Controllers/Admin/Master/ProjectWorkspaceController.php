<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Concerns\ServesWorkspacePanels;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\Workspace\ProjectWorkspacePanels;
use Illuminate\Http\Request;

class ProjectWorkspaceController extends Controller
{
    use ServesWorkspacePanels;

    public function panel(Request $request, Project $project, string $panel)
    {
        $this->authorizePanel($request, ProjectWorkspacePanels::class, $panel, 'view');
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'record' => ['prohibited'], 'project_id' => ['prohibited']]);
        $data = ProjectWorkspacePanels::data($project, $panel, $request->user(), $request->integer('page', 1));

        return $request->wantsJson()
            ? $this->panelHtml('admin.master.projects._panel', $data)
            : view('admin.master.projects.panel', $data);
    }
}
