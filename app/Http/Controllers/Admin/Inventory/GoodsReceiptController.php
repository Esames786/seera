<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Accounting\PostingService;
use App\Services\Inventory\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly StockService $stock,
        private readonly PostingService $posting
    ) {
    }

    public function index(Request $request): View
    {
        $receipts = GoodsReceipt::with(['supplier', 'warehouse', 'purchaseOrder'])
            ->withCount('lines')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('grn_number', 'like', "%{$search}%")
                    ->orWhere('delivery_note_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%")));
            })
            ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('received_date')->orderByDesc('id')
            ->paginate(10)->withQueryString();

        return view('admin.inventory.goods-receipts.index', [
            'receipts' => $receipts,
            'draftCount' => GoodsReceipt::where('status', 'draft')->count(),
            'postedCount' => GoodsReceipt::where('status', 'posted')->count(),
            'receivedValue' => round((float) GoodsReceipt::where('status', 'posted')->sum('total_amount'), 2),
            'awaitingAccounting' => GoodsReceipt::where('status', 'posted')->where('accounting_posted', false)->count(),
        ] + $this->formOptions());
    }

    public function create(Request $request): View
    {
        $order = $request->filled('purchase_order')
            ? PurchaseOrder::with('lines.item')->find($request->integer('purchase_order'))
            : null;

        return view('admin.inventory.goods-receipts.create', ['order' => $order] + $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);

        $grn = DB::transaction(function () use ($data, $lines, $request) {
            $grn = GoodsReceipt::create($data + [
                'grn_number' => GoodsReceipt::nextNumber((int) date('Y', strtotime($data['received_date']))),
                'received_by' => $request->user()->id,
            ]);
            $grn->lines()->createMany($lines);

            return $grn;
        });

        ActivityLog::record($request, 'Inventory', 'Created goods receipt', $grn->grn_number);

        return redirect()->route('admin.inventory.goods-receipts.show', $grn)
            ->with('status', 'Goods receipt "'.$grn->grn_number.'" saved. Post it to update warehouse stock and accounting.');
    }

    public function show(GoodsReceipt $goods_receipt): View
    {
        $goods_receipt->load(['lines.item.unit', 'supplier', 'warehouse', 'purchaseOrder', 'receiver', 'journalEntry.lines.account']);

        return view('admin.inventory.goods-receipts.show', ['grn' => $goods_receipt]);
    }

    public function edit(GoodsReceipt $goods_receipt): View
    {
        if (! $goods_receipt->isEditable()) {
            abort(403, 'A posted goods receipt is read-only.');
        }

        $goods_receipt->load('lines');

        return view('admin.inventory.goods-receipts.edit', ['grn' => $goods_receipt, 'order' => null] + $this->formOptions());
    }

    public function update(Request $request, GoodsReceipt $goods_receipt): RedirectResponse
    {
        if (! $goods_receipt->isEditable()) {
            return back()->withErrors(['grn' => 'A posted goods receipt is read-only.']);
        }

        [$data, $lines] = $this->validated($request);

        DB::transaction(function () use ($goods_receipt, $data, $lines) {
            $goods_receipt->update($data);
            $goods_receipt->lines()->delete();
            $goods_receipt->lines()->createMany($lines);
        });

        ActivityLog::record($request, 'Inventory', 'Updated goods receipt', $goods_receipt->grn_number);

        return redirect()->route('admin.inventory.goods-receipts.show', $goods_receipt)
            ->with('status', 'Goods receipt updated successfully.');
    }

    public function destroy(Request $request, GoodsReceipt $goods_receipt): RedirectResponse
    {
        if (! $goods_receipt->isEditable()) {
            return back()->withErrors(['grn' => 'A posted goods receipt cannot be deleted.']);
        }

        $number = $goods_receipt->grn_number;
        $goods_receipt->delete();

        ActivityLog::record($request, 'Inventory', 'Deleted goods receipt', $number);

        return redirect()->route('admin.inventory.goods-receipts.index')
            ->with('status', 'Goods receipt "'.$number.'" deleted successfully.');
    }

    /**
     * Posting a GRN increases warehouse stock, writes the ledger, updates the
     * purchase order and posts inventory + input VAT against accounts payable.
     */
    public function postStock(Request $request, GoodsReceipt $goods_receipt): RedirectResponse
    {
        if ($goods_receipt->status !== 'draft') {
            return back()->withErrors(['grn' => 'This goods receipt is already posted.']);
        }

        $goods_receipt->load('lines.item');

        if ($goods_receipt->lines->isEmpty()) {
            return back()->withErrors(['grn' => 'Add at least one line before posting.']);
        }

        DB::transaction(function () use ($goods_receipt, $request) {
            $goods_receipt = GoodsReceipt::whereKey($goods_receipt->id)->lockForUpdate()->firstOrFail();
            if ($goods_receipt->status !== 'draft') {
                throw ValidationException::withMessages(['grn' => 'This goods receipt is already posted.']);
            }
            $goods_receipt->load('lines.item', 'warehouse');

            // Receiving against an order: lock the order lines and re-check the outstanding
            // quantity now, so two receipts posted at once cannot both over-deliver (F04).
            $order = $goods_receipt->purchase_order_id
                ? PurchaseOrder::whereKey($goods_receipt->purchase_order_id)->lockForUpdate()->first()
                : null;
            $orderLineFor = [];

            if ($order) {
                $this->assertOrderReceivable($order, (int) $goods_receipt->supplier_id, 'grn');
                $orderLines = $order->lines()->lockForUpdate()->get();
                $acceptedPerLine = [];

                foreach ($goods_receipt->lines as $line) {
                    $orderLine = $this->orderLineFor($order, $orderLines, $line->toArray(), 'grn');
                    $orderLineFor[$line->id] = $orderLine;
                    $acceptedPerLine[$orderLine->id] = round(($acceptedPerLine[$orderLine->id] ?? 0) + (float) $line->accepted_quantity, 3);

                    if ($acceptedPerLine[$orderLine->id] > $orderLine->outstandingQuantity() + 0.0005) {
                        throw ValidationException::withMessages(['grn' => sprintf(
                            'Purchase order %s has only %s of %s outstanding (another receipt was posted first); this receipt accepts %s. Nothing was posted.',
                            $order->po_number, $this->qty($orderLine->outstandingQuantity()), $line->item?->label() ?? 'item', $this->qty($acceptedPerLine[$orderLine->id])
                        )]);
                    }
                }
            }

            foreach ($goods_receipt->lines as $line) {
                $accepted = (float) $line->accepted_quantity;

                if ($accepted <= 0 || ! $line->item) {
                    continue;
                }

                $this->stock->receive($line->item, $goods_receipt->warehouse_id, $accepted, (float) $line->unit_cost, [
                    'movement_type' => 'grn',
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $goods_receipt->id,
                    'reference_number' => $goods_receipt->grn_number,
                    'movement_date' => $goods_receipt->received_date->toDateString(),
                    'project_id' => $goods_receipt->warehouse?->project_id,
                    'site_id' => $goods_receipt->warehouse?->site_id,
                    'created_by' => $request->user()->id,
                ]);

                if ($orderLine = $orderLineFor[$line->id] ?? null) {
                    $orderLine->increment('received_quantity', $accepted);
                    if ((int) $line->purchase_order_line_id !== (int) $orderLine->id) {
                        $line->update(['purchase_order_line_id' => $orderLine->id]);
                    }
                }
            }

            // Refused postings roll the stock movement back too; a draft (review-mode)
            // journal is reported truthfully as not yet posted (F01).
            $entry = $this->posting->postGoodsReceipt($goods_receipt, $request->user()->id);

            $goods_receipt->update([
                'status' => 'posted',
                'stock_updated' => true,
                'accounting_posted' => $entry?->status === 'posted',
                'journal_entry_id' => $entry?->id,
            ]);

            $order?->refreshReceiptStatus();
        });

        ActivityLog::record($request, 'Inventory', 'Posted goods receipt', $goods_receipt->grn_number);

        return redirect()->route('admin.inventory.goods-receipts.show', $goods_receipt)
            ->with('status', 'Goods receipt posted. Warehouse stock and the stock ledger have been updated.');
    }

    /**
     * @return array{0: array, 1: array} Header data and normalized line rows.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'received_date' => ['required', 'date'],
            'delivery_note_number' => ['nullable', 'string', 'max:100'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'exists:items,id'],
            'lines.*.purchase_order_line_id' => ['nullable', 'integer'],
            'lines.*.ordered_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.received_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.accepted_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ], ['lines.required' => 'Add at least one received item.']);

        $vatRate = (float) $data['vat_rate'];

        $lines = collect($data['lines'])
            ->filter(fn ($line) => filled($line['item_id'] ?? null) && (float) ($line['received_quantity'] ?? 0) > 0)
            ->map(function ($line) {
                $received = (float) $line['received_quantity'];
                $accepted = (float) ($line['accepted_quantity'] ?? $received);
                $accepted = min($accepted, $received);
                $cost = (float) ($line['unit_cost'] ?? 0);

                return [
                    'item_id' => $line['item_id'],
                    'purchase_order_line_id' => filled($line['purchase_order_line_id'] ?? null) ? (int) $line['purchase_order_line_id'] : null,
                    'ordered_quantity' => (float) ($line['ordered_quantity'] ?? 0),
                    'received_quantity' => $received,
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => round($received - $accepted, 3),
                    'unit_cost' => $cost,
                    'total_cost' => round($accepted * $cost, 2),
                ];
            })->values()->all();

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Add at least one line with an item and a received quantity.']);
        }

        if (! empty($data['purchase_order_id'])) {
            $lines = $this->linesAgainstOrder((int) $data['purchase_order_id'], (int) $data['supplier_id'], $lines);
        } else {
            $lines = array_map(fn ($line) => ['purchase_order_line_id' => null] + $line, $lines);
        }

        $taxable = round(array_sum(array_column($lines, 'total_cost')), 2);
        $vat = round($taxable * $vatRate / 100, 2);

        unset($data['lines']);

        $data += [
            'taxable_amount' => $taxable,
            'vat_amount' => $vat,
            'total_amount' => round($taxable + $vat, 2),
            'status' => 'draft',
        ];

        return [$data, $lines];
    }

    /**
     * A receipt against a purchase order may only deliver items on that order, from that
     * supplier, while the order is open, and never more than is still outstanding (F04).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function linesAgainstOrder(int $orderId, int $supplierId, array $lines): array
    {
        $order = PurchaseOrder::with('lines')->find($orderId);
        if (! $order) {
            throw ValidationException::withMessages(['purchase_order_id' => 'The selected purchase order is not available to your account.']);
        }

        $this->assertOrderReceivable($order, $supplierId, 'lines');
        $receivedPerLine = [];

        foreach ($lines as &$line) {
            $orderLine = $this->orderLineFor($order, $order->lines, $line, 'lines');
            $line['purchase_order_line_id'] = $orderLine->id;
            if ((float) $line['ordered_quantity'] <= 0) {
                $line['ordered_quantity'] = (float) $orderLine->quantity;
            }

            $receivedPerLine[$orderLine->id] = round(($receivedPerLine[$orderLine->id] ?? 0) + (float) $line['received_quantity'], 3);
            if ($receivedPerLine[$orderLine->id] > $orderLine->outstandingQuantity() + 0.0005) {
                throw ValidationException::withMessages(['lines' => sprintf(
                    'Purchase order %s has only %s of %s outstanding; this receipt records %s. Reduce the received quantity or raise the order.',
                    $order->po_number, $this->qty($orderLine->outstandingQuantity()), $orderLine->item?->label() ?? 'item', $this->qty($receivedPerLine[$orderLine->id])
                )]);
            }
        }
        unset($line);

        return $lines;
    }

    private function assertOrderReceivable(PurchaseOrder $order, int $supplierId, string $errorKey): void
    {
        if (! $order->canReceive()) {
            throw ValidationException::withMessages([$errorKey => 'Purchase order '.$order->po_number.' is '.str_replace('_', ' ', $order->status).' and cannot receive goods.']);
        }
        if ((int) $order->supplier_id !== $supplierId) {
            throw ValidationException::withMessages([$errorKey => 'Purchase order '.$order->po_number.' belongs to '.($order->supplier?->name ?? 'another supplier').', not to the supplier on this receipt.']);
        }
    }

    /** Resolve the order line a receipt line delivers: by line id, or by item when unambiguous. */
    private function orderLineFor(PurchaseOrder $order, \Illuminate\Support\Collection $orderLines, array $line, string $errorKey): PurchaseOrderLine
    {
        $item = Item::find($line['item_id'] ?? null);
        $label = $item?->label() ?? 'item';

        if (! empty($line['purchase_order_line_id'])) {
            $orderLine = $orderLines->firstWhere('id', (int) $line['purchase_order_line_id']);
            if (! $orderLine || (int) $orderLine->item_id !== (int) ($line['item_id'] ?? 0)) {
                throw ValidationException::withMessages([$errorKey => 'The line for '.$label.' does not belong to purchase order '.$order->po_number.'.']);
            }

            return $orderLine;
        }

        $candidates = $orderLines->where('item_id', (int) ($line['item_id'] ?? 0));
        if ($candidates->count() === 1) {
            return $candidates->first();
        }
        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages([$errorKey => $label.' is not on purchase order '.$order->po_number.'.']);
        }

        throw ValidationException::withMessages([$errorKey => $label.' appears on more than one line of purchase order '.$order->po_number.'; create the receipt from the order so each line is matched.']);
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'items' => Item::where('status', 'active')->orderBy('item_code')->get(),
            'openOrders' => PurchaseOrder::whereIn('status', ['approved', 'partially_received'])->orderByDesc('id')->get(),
            'statuses' => GoodsReceipt::STATUSES,
        ];
    }
}
