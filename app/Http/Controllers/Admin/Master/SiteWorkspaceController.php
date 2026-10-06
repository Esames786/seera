<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Admin\SiteExpenseController;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Support\Workspace\SiteWorkspacePanels as Panels;
use Illuminate\Http\Request;

class SiteWorkspaceController extends Controller
{
    public function panel(Request $request, Site $site, string $panel)
    {
        $definition = Panels::definition($panel);
        abort_unless($definition, 404);
        abort_unless($request->user()->hasPermission('Sites', 'view') && $definition->canView($request->user()), 403);
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'site_id' => ['prohibited'], 'project_id' => ['prohibited'], 'record' => ['prohibited']]);
        $data = Panels::data($site, $panel, $request->user());

        return $request->wantsJson() ? response()->json(['html' => view('admin.master.sites._panel', $data)->render()]) : view('admin.master.sites.panel', $data);
    }

    private function expenseContext(Request $request, Site $site): void
    {
        abort_unless($request->user()->hasPermission('Sites', 'view') && $request->user()->hasPermission('Site Expenses', 'view') && $request->user()->hasPermission('Site Expenses', 'create'), 403);
        abort_unless($site->project, 404);
        $request->validate(['site_id' => ['nullable', 'integer', 'in:'.$site->id], 'project_id' => ['nullable', 'integer', 'in:'.$site->project_id]]);
        $request->merge(['site_id' => $site->id, 'project_id' => $site->project_id, '_return_to' => route('admin.master.sites.show', $site, false).'#expenses']);
    }

    public function createExpense(Request $request, Site $site, SiteExpenseController $controller)
    {
        $this->expenseContext($request, $site);
        $view = $controller->create($request);
        $view->getData()['expense']->fill(['site_id' => $site->id, 'project_id' => $site->project_id]);

        return $view->with('contextSite', $site)->with('formAction', route('admin.master.sites.expenses.store', $site));
    }

    public function storeExpense(Request $request, Site $site, SiteExpenseController $controller)
    {
        $this->expenseContext($request, $site);

        return $controller->store($request);
    }
}
