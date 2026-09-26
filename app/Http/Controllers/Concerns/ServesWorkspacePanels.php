<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Workspace\PanelDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller side of a connected workspace: every panel request is checked
 * against the panel's own permission module (the workspace never widens what
 * the user may do), and panels are returned as rendered HTML fragments.
 */
trait ServesWorkspacePanels
{
    /**
     * @param  class-string<\App\Support\Workspace\WorkspacePanels>  $panels
     */
    protected function authorizePanel(Request $request, string $panels, string $panel, string $action): PanelDefinition
    {
        $definition = $panels::definition($panel);
        abort_unless($definition, 404);
        abort_unless($request->user()->hasPermission($definition->module, $action), 403);

        return $definition;
    }

    protected function panelHtml(string $view, array $data): JsonResponse
    {
        return response()->json(['html' => view($view, $data)->render()]);
    }
}
