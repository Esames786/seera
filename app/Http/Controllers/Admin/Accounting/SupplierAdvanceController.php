<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierAdvance;
use App\Models\SupplierAdvanceMovement;
use App\Services\Accounting\SupplierRecoverables;
use Illuminate\Http\Request;

class SupplierAdvanceController extends Controller
{
    public function __construct(private readonly SupplierRecoverables $recoverables) {}

    public function store(Request $request, Supplier $supplier)
    {
        $this->recoverables->record($supplier, $request->all(), $request->user());

        return $this->back($request, $supplier);
    }

    public function settle(Request $request, Supplier $supplier, SupplierAdvance $advance, string $kind)
    {
        abort_unless($advance->supplier_id === $supplier->id, 404);
        $this->recoverables->settle($advance, $kind, $request->all(), $request->user());

        return $this->back($request, $supplier);
    }

    public function reverse(Request $request, Supplier $supplier, SupplierAdvance $advance)
    {
        abort_unless($advance->supplier_id === $supplier->id, 404);
        $this->recoverables->reverse($advance, null, (string) $request->input('reason'), $request->user());

        return $this->back($request, $supplier);
    }

    public function reverseMovement(Request $request, Supplier $supplier, SupplierAdvance $advance, SupplierAdvanceMovement $movement)
    {
        abort_unless($advance->supplier_id === $supplier->id && $movement->supplier_advance_id === $advance->id, 404);
        $this->recoverables->reverse($advance, $movement, (string) $request->input('reason'), $request->user());

        return $this->back($request, $supplier);
    }

    private function back(Request $request, Supplier $supplier)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => __('supplier_advances.saved'), 'panel_url' => route('admin.master.suppliers.workspace.panel', [$supplier, 'advances'])]);
        }

        return redirect()->to(route('admin.master.suppliers.edit', $supplier).'#advances')->with('status', __('supplier_advances.saved'));
    }
}
