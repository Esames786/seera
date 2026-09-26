<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Concerns\ServesWorkspacePanels;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Supplier;
use App\Support\Workspace\SupplierWorkspacePanels as Panels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Related panels of the Supplier workspace. The supplier in the URL is the
 * authority for every child operation; ids sent by the browser are only
 * accepted when they belong to that supplier and are visible to the user.
 * Bills, payments, orders and receipts are never written from here: those
 * stay explicit actions on their own pages.
 */
class SupplierWorkspaceController extends Controller
{
    use ServesWorkspacePanels;

    public function panel(Request $request, Supplier $supplier, string $panel): JsonResponse
    {
        $this->authorizePanel($request, Panels::class, $panel, 'view');
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return $this->panelHtml('admin.master.suppliers._workspace-panel', Panels::data($supplier, $panel, $request->user(), false, 10, $request->integer('page') ?: null));
    }

    /** Projects panel: link one visible project to this supplier. */
    public function save(Request $request, Supplier $supplier, string $panel): JsonResponse
    {
        abort_unless($panel === 'projects', 404);
        $this->authorizePanel($request, Panels::class, 'projects', 'edit');
        abort_unless($request->user()->hasPermission('Projects', 'view'), 403);
        abort_if($request->filled('supplier_id') && $request->integer('supplier_id') !== $supplier->id, 403);

        $data = $request->validate(['project_id' => ['required', 'integer']]);

        DB::transaction(function () use ($request, $supplier, $data) {
            $supplier = Supplier::whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            // The Project query carries the user's access scope: an out-of-scope id is simply not found.
            $project = Project::whereKey($data['project_id'])->first();
            if (! $project) {
                throw ValidationException::withMessages(['project_id' => 'That project is not available to your account.']);
            }

            $supplier->projects()->syncWithoutDetaching([$project->id]);
            ActivityLog::record($request, 'Suppliers', 'Linked supplier to project', $supplier->name.' → '.$project->code.' '.$project->name);
        });

        return response()->json([
            'message' => __('Saved successfully. This section is up to date.'),
            'panel_url' => route('admin.master.suppliers.workspace.panel', [$supplier, 'projects']),
        ]);
    }

    /** Projects panel: unlink a project that is linked to this supplier. */
    public function action(Request $request, Supplier $supplier, string $panel, int $record, string $action): JsonResponse
    {
        abort_unless($panel === 'projects' && $action === 'remove', 404);
        $this->authorizePanel($request, Panels::class, 'projects', 'edit');

        DB::transaction(function () use ($request, $supplier, $record) {
            $supplier = Supplier::whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            // Must be linked to THIS supplier and visible to the user; anything else is not found.
            $project = $supplier->projects()->whereKey($record)->first();
            abort_unless($project, 404);

            $supplier->projects()->detach($project->id);
            ActivityLog::record($request, 'Suppliers', 'Unlinked supplier from project', $supplier->name.' ✕ '.$project->code.' '.$project->name);
        });

        return response()->json(['message' => __('Action completed.')]);
    }
}
