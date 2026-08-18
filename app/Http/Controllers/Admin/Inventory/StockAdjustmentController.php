<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\Accounting\PostingService;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly StockService $stock,
        private readonly PostingService $posting
    ) {
    }

    public function index(Request $request): View
    {
        $adjustments = StockAdjustment::with(['warehouse', 'item', 'approver'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('adjustment_number', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($i) => $i->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%")));
            })
            ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('adjustment_date')->orderByDesc('id')
            ->paginate(10)->withQueryString();

        return view('admin.inventory.stock-adjustments.index', [
            'adjustments' => $adjustments,
            'draftCount' => StockAdjustment::where('status', 'draft')->count(),
            'approvedCount' => StockAdjustment::where('status', 'approved')->count(),
            'postedCount' => StockAdjustment::where('status', 'posted')->count(),
            'lossValue' => round((float) StockAdjustment::where('status', 'posted')->where('difference_quantity', '<', 0)->sum('adjustment_value'), 2),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.inventory.stock-adjustments.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $adjustment = StockAdjustment::create($data + [
            'adjustment_number' => StockAdjustment::nextNumber((int) date('Y', strtotime($data['adjustment_date']))),
        ]);

        ActivityLog::record($request, 'Inventory', 'Created stock adjustment', $adjustment->adjustment_number);

        return redirect()->route('admin.inventory.stock-adjustments.show', $adjustment)
            ->with('status', 'Stock adjustment "'.$adjustment->adjustment_number.'" created. Approve it, then post it to change stock.');
    }

    public function show(StockAdjustment $stock_adjustment): View
    {
        $stock_adjustment->load(['warehouse', 'item.unit', 'approver', 'journalEntry.lines.account']);

        return view('admin.inventory.stock-adjustments.show', [
            'adjustment' => $stock_adjustment,
            'currentStock' => $this->stock->stockRow($stock_adjustment->item_id, $stock_adjustment->warehouse_id),
        ]);
    }

    public function edit(StockAdjustment $stock_adjustment): View
    {
        if (! $stock_adjustment->isEditable()) {
            abort(403, 'A posted stock adjustment is read-only.');
        }

        return view('admin.inventory.stock-adjustments.edit', ['adjustment' => $stock_adjustment] + $this->formOptions());
    }

    public function update(Request $request, StockAdjustment $stock_adjustment): RedirectResponse
    {
        if (! $stock_adjustment->isEditable()) {
            return back()->withErrors(['adjustment' => 'A posted stock adjustment is read-only.']);
        }

        $stock_adjustment->update($this->validated($request));

        ActivityLog::record($request, 'Inventory', 'Updated stock adjustment', $stock_adjustment->adjustment_number);

        return redirect()->route('admin.inventory.stock-adjustments.show', $stock_adjustment)
            ->with('status', 'Stock adjustment updated successfully.');
    }

    public function destroy(Request $request, StockAdjustment $stock_adjustment): RedirectResponse
    {
        if (! $stock_adjustment->isEditable()) {
            return back()->withErrors(['adjustment' => 'A posted stock adjustment cannot be deleted.']);
        }

        $number = $stock_adjustment->adjustment_number;
        $stock_adjustment->delete();

        ActivityLog::record($request, 'Inventory', 'Deleted stock adjustment', $number);

        return redirect()->route('admin.inventory.stock-adjustments.index')
            ->with('status', 'Stock adjustment "'.$number.'" deleted successfully.');
    }

    public function approve(Request $request, StockAdjustment $stock_adjustment): RedirectResponse
    {
        if ($stock_adjustment->status !== 'draft') {
            return back()->withErrors(['adjustment' => 'Only a draft adjustment can be approved.']);
        }

        $stock_adjustment->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        ActivityLog::record($request, 'Inventory', 'Approved stock adjustment', $stock_adjustment->adjustment_number);

        return back()->with('status', 'Stock adjustment approved. Post it to change warehouse stock.');
    }

    /**
     * Stock only changes once an approved adjustment is posted.
     */
    public function post(Request $request, StockAdjustment $stock_adjustment): RedirectResponse
    {
        if ($stock_adjustment->status !== 'approved') {
            return back()->withErrors(['adjustment' => 'Only an approved adjustment can be posted.']);
        }

        $stock_adjustment->load('item');

        try {
            DB::transaction(function () use ($stock_adjustment, $request) {
                $entry = $this->stock->adjust(
                    $stock_adjustment->item,
                    $stock_adjustment->warehouse_id,
                    (float) $stock_adjustment->adjusted_quantity,
                    [
                        'movement_type' => 'adjustment',
                        'reference_type' => StockAdjustment::class,
                        'reference_id' => $stock_adjustment->id,
                        'reference_number' => $stock_adjustment->adjustment_number,
                        'movement_date' => $stock_adjustment->adjustment_date->toDateString(),
                        'created_by' => $request->user()->id,
                    ]
                );

                $difference = (float) $stock_adjustment->adjusted_quantity - (float) $stock_adjustment->current_quantity;

                $stock_adjustment->update([
                    'difference_quantity' => round($difference, 3),
                    'unit_cost' => $entry->unit_cost,
                    'adjustment_value' => round(abs($difference) * (float) $entry->unit_cost, 2),
                    'adjustment_type' => $difference < 0 ? 'decrease' : 'increase',
                    'status' => 'posted',
                ]);

                $journal = $this->posting->postStockAdjustment($stock_adjustment->fresh('item'), $request->user()->id);

                $stock_adjustment->update([
                    'accounting_posted' => (bool) $journal,
                    'journal_entry_id' => $journal?->id,
                ]);
            });
        } catch (InsufficientStockException $exception) {
            return back()->withErrors(['adjustment' => $exception->getMessage()]);
        }

        ActivityLog::record($request, 'Inventory', 'Posted stock adjustment', $stock_adjustment->adjustment_number);

        return redirect()->route('admin.inventory.stock-adjustments.show', $stock_adjustment)
            ->with('status', 'Stock adjustment posted. Warehouse stock and the stock ledger have been updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'item_id' => ['required', 'exists:items,id'],
            'adjustment_date' => ['required', 'date'],
            'adjusted_quantity' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string'],
        ]);

        $stock = $this->stock->stockRow((int) $data['item_id'], (int) $data['warehouse_id']);
        $difference = round((float) $data['adjusted_quantity'] - (float) $stock->quantity, 3);

        $data += [
            'current_quantity' => (float) $stock->quantity,
            'difference_quantity' => $difference,
            'unit_cost' => (float) $stock->average_cost,
            'adjustment_value' => round(abs($difference) * (float) $stock->average_cost, 2),
            'adjustment_type' => $difference < 0 ? 'decrease' : 'increase',
            'status' => 'draft',
        ];

        return $data;
    }

    private function formOptions(): array
    {
        return [
            'warehouses' => Warehouse::orderBy('name')->get(),
            'items' => Item::where('status', 'active')->orderBy('item_code')->get(),
            'statuses' => StockAdjustment::STATUSES,
        ];
    }
}
