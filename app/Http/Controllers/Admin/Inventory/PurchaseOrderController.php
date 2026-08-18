<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = PurchaseOrder::with(['supplier', 'project', 'warehouse'])
            ->withCount('lines')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('po_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%")));
            })
            ->when($request->filled('supplier'), fn ($q) => $q->where('supplier_id', $request->integer('supplier')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('po_date')->orderByDesc('id')
            ->paginate(10)->withQueryString();

        return view('admin.inventory.purchase-orders.index', [
            'orders' => $orders,
            'draftCount' => PurchaseOrder::where('status', 'draft')->count(),
            'openCount' => PurchaseOrder::whereIn('status', ['approved', 'partially_received'])->count(),
            'receivedCount' => PurchaseOrder::where('status', 'received')->count(),
            'openValue' => round((float) PurchaseOrder::whereIn('status', ['approved', 'partially_received'])->sum('total_amount'), 2),
        ] + $this->formOptions());
    }

    public function create(Request $request): View
    {
        $sourceRequest = $request->filled('purchase_request')
            ? PurchaseRequest::with('lines.item')->find($request->integer('purchase_request'))
            : null;

        return view('admin.inventory.purchase-orders.create', ['sourceRequest' => $sourceRequest] + $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);

        $order = DB::transaction(function () use ($data, $lines) {
            $order = PurchaseOrder::create($data + [
                'po_number' => PurchaseOrder::nextNumber((int) date('Y', strtotime($data['po_date']))),
            ]);
            $order->lines()->createMany($lines);

            if ($order->purchase_request_id) {
                PurchaseRequest::where('id', $order->purchase_request_id)
                    ->where('status', 'approved')
                    ->update(['status' => 'converted']);
            }

            return $order;
        });

        ActivityLog::record($request, 'Inventory', 'Created purchase order', $order->po_number);

        return redirect()->route('admin.inventory.purchase-orders.show', $order)
            ->with('status', 'Purchase order "'.$order->po_number.'" created successfully.');
    }

    public function show(PurchaseOrder $purchase_order): View
    {
        $purchase_order->load(['lines.item.unit', 'supplier', 'project', 'site', 'warehouse', 'approver', 'purchaseRequest', 'goodsReceipts']);

        return view('admin.inventory.purchase-orders.show', ['order' => $purchase_order]);
    }

    public function edit(PurchaseOrder $purchase_order): View
    {
        if (! $purchase_order->isEditable()) {
            abort(403, 'An approved purchase order can no longer be edited.');
        }

        $purchase_order->load('lines');

        return view('admin.inventory.purchase-orders.edit', ['order' => $purchase_order, 'sourceRequest' => null] + $this->formOptions());
    }

    public function update(Request $request, PurchaseOrder $purchase_order): RedirectResponse
    {
        if (! $purchase_order->isEditable()) {
            return back()->withErrors(['po' => 'An approved purchase order can no longer be edited.']);
        }

        [$data, $lines] = $this->validated($request);

        DB::transaction(function () use ($purchase_order, $data, $lines) {
            $purchase_order->update($data);
            $purchase_order->lines()->delete();
            $purchase_order->lines()->createMany($lines);
        });

        ActivityLog::record($request, 'Inventory', 'Updated purchase order', $purchase_order->po_number);

        return redirect()->route('admin.inventory.purchase-orders.show', $purchase_order)
            ->with('status', 'Purchase order updated successfully.');
    }

    public function destroy(Request $request, PurchaseOrder $purchase_order): RedirectResponse
    {
        if (! $purchase_order->isEditable()) {
            return back()->withErrors(['po' => 'An approved purchase order cannot be deleted.']);
        }

        $number = $purchase_order->po_number;
        $purchase_order->delete();

        ActivityLog::record($request, 'Inventory', 'Deleted purchase order', $number);

        return redirect()->route('admin.inventory.purchase-orders.index')
            ->with('status', 'Purchase order "'.$number.'" deleted successfully.');
    }

    public function approve(Request $request, PurchaseOrder $purchase_order): RedirectResponse
    {
        if ($purchase_order->status !== 'draft') {
            return back()->withErrors(['po' => 'Only a draft purchase order can be approved.']);
        }

        $purchase_order->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        ActivityLog::record($request, 'Inventory', 'Approved purchase order', $purchase_order->po_number);

        return back()->with('status', 'Purchase order "'.$purchase_order->po_number.'" approved and open for receiving.');
    }

    /**
     * @return array{0: array, 1: array} Header data and normalized line rows.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_request_id' => ['nullable', 'exists:purchase_requests,id'],
            'po_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:po_date'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'exists:items,id'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ], ['lines.required' => 'Add at least one order line.']);

        $vatRate = (float) $data['vat_rate'];

        $lines = collect($data['lines'])
            ->filter(fn ($line) => filled($line['item_id'] ?? null) && (float) ($line['quantity'] ?? 0) > 0)
            ->map(function ($line) use ($vatRate) {
                $quantity = (float) $line['quantity'];
                $price = (float) ($line['unit_price'] ?? 0);
                $taxable = round($quantity * $price, 2);
                $vat = round($taxable * $vatRate / 100, 2);

                return [
                    'item_id' => $line['item_id'],
                    'quantity' => $quantity,
                    'received_quantity' => 0,
                    'unit_price' => $price,
                    'taxable_amount' => $taxable,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vat,
                    'total_amount' => round($taxable + $vat, 2),
                ];
            })->values()->all();

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Add at least one line with an item and a quantity.']);
        }

        $taxable = round(array_sum(array_column($lines, 'taxable_amount')), 2);
        $vat = round(array_sum(array_column($lines, 'vat_amount')), 2);

        unset($data['lines']);

        $data += [
            'taxable_amount' => $taxable,
            'vat_amount' => $vat,
            'total_amount' => round($taxable + $vat, 2),
            'status' => 'draft',
        ];

        return [$data, $lines];
    }

    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::orderBy('name')->get(),
            'items' => Item::where('status', 'active')->orderBy('item_code')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'approvedRequests' => PurchaseRequest::whereIn('status', ['approved', 'converted'])->orderByDesc('id')->get(),
            'statuses' => PurchaseOrder::STATUSES,
        ];
    }
}
