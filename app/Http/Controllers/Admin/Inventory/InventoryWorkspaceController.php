<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Warehouse;
use App\Support\Workspace\InventoryWorkspace as Workspace;
use Illuminate\Http\Request;

class InventoryWorkspaceController extends Controller
{
    public function item(Request $request, Item $item, string $panel)
    {
        return $this->render($request, $item, $panel);
    }

    public function warehouse(Request $request, Warehouse $warehouse, string $panel)
    {
        return $this->render($request, $warehouse, $panel);
    }

    private function render(Request $request, Item|Warehouse $parent, string $panel)
    {
        $actor = $request->user();
        abort_unless(collect(Workspace::panels($parent, $actor))->contains(fn ($p) => $p->key === $panel), 403);
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'item_id' => ['prohibited'], 'warehouse_id' => ['prohibited'], 'project_id' => ['prohibited'], 'site_id' => ['prohibited'], 'record' => ['prohibited']]);
        $rows = in_array($panel, ['accounting', 'context']) ? null : Workspace::query($parent, $panel, $actor)->latest('id')->paginate(10)->withQueryString();
        if ($panel === 'accounting') {
            $parent->load(['inventoryAccount', 'expenseAccount']);
        }
        if ($panel === 'context') {
            $parent->load(['project', 'site']);
        }
        $data = compact('parent', 'panel', 'rows', 'actor');

        return $request->wantsJson() ? response()->json(['html' => view('admin.inventory.workspace._panel', $data)->render()]) : view('admin.inventory.workspace.panel', $data);
    }
}
