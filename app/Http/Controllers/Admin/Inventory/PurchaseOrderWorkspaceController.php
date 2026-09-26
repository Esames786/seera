<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Concerns\ServesWorkspacePanels;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Support\Workspace\PurchaseOrderWorkspacePanels as Panels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paged sections of the Purchase Order document workspace (goods receipts,
 * billing, accounting, activity). Read-only: receiving, billing and payment
 * stay explicit actions on their own pages. The order in the URL is the
 * authority; every section is checked against its own permission module.
 */
class PurchaseOrderWorkspaceController extends Controller
{
    use ServesWorkspacePanels;

    public function panel(Request $request, PurchaseOrder $purchase_order, string $panel): JsonResponse
    {
        abort_unless(in_array($panel, Panels::PAGED, true), 404);
        $this->authorizePanel($request, Panels::class, $panel, 'view');
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return $this->panelHtml(
            'admin.inventory.purchase-orders._workspace-panel',
            Panels::data($purchase_order, $panel, $request->user(), 5, $request->integer('page') ?: null, false)
        );
    }
}
