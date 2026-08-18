<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function __construct(private readonly StockService $stock)
    {
    }

    public function index(Request $request): View
    {
        $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'requester'])
            ->withCount('lines')
            ->when($request->filled('search'), fn ($q) => $q->where('transfer_number', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('from_warehouse'), fn ($q) => $q->where('from_warehouse_id', $request->integer('from_warehouse')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('transfer_date')->orderByDesc('id')
            ->paginate(10)->withQueryString();

        return view('admin.inventory.stock-transfers.index', [
            'transfers' => $transfers,
            'draftCount' => StockTransfer::where('status', 'draft')->count(),
            'inTransitCount' => StockTransfer::where('status', 'dispatched')->count(),
            'receivedCount' => StockTransfer::where('status', 'received')->count(),
            'transferValue' => round((float) StockTransfer::where('status', 'received')->sum('total_cost'), 2),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.inventory.stock-transfers.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);

        $transfer = DB::transaction(function () use ($data, $lines, $request) {
            $transfer = StockTransfer::create($data + [
                'transfer_number' => StockTransfer::nextNumber((int) date('Y', strtotime($data['transfer_date']))),
                'requested_by' => $request->user()->id,
            ]);
            $transfer->lines()->createMany($lines);

            return $transfer;
        });

        ActivityLog::record($request, 'Inventory', 'Created stock transfer', $transfer->transfer_number);

        return redirect()->route('admin.inventory.stock-transfers.show', $transfer)
            ->with('status', 'Stock transfer "'.$transfer->transfer_number.'" saved. Dispatch it to move stock out of the source warehouse.');
    }

    public function show(StockTransfer $stock_transfer): View
    {
        $stock_transfer->load(['lines.item.unit', 'fromWarehouse', 'toWarehouse', 'requester', 'dispatcher', 'receiver']);

        return view('admin.inventory.stock-transfers.show', ['transfer' => $stock_transfer]);
    }

    public function edit(StockTransfer $stock_transfer): View
    {
        if (! $stock_transfer->isEditable()) {
            abort(403, 'A dispatched or received transfer is read-only.');
        }

        $stock_transfer->load('lines');

        return view('admin.inventory.stock-transfers.edit', ['transfer' => $stock_transfer] + $this->formOptions());
    }

    public function update(Request $request, StockTransfer $stock_transfer): RedirectResponse
    {
        if (! $stock_transfer->isEditable()) {
            return back()->withErrors(['transfer' => 'A dispatched or received transfer is read-only.']);
        }

        [$data, $lines] = $this->validated($request);

        DB::transaction(function () use ($stock_transfer, $data, $lines) {
            $stock_transfer->update($data);
            $stock_transfer->lines()->delete();
            $stock_transfer->lines()->createMany($lines);
        });

        ActivityLog::record($request, 'Inventory', 'Updated stock transfer', $stock_transfer->transfer_number);

        return redirect()->route('admin.inventory.stock-transfers.show', $stock_transfer)
            ->with('status', 'Stock transfer updated successfully.');
    }

    public function destroy(Request $request, StockTransfer $stock_transfer): RedirectResponse
    {
        if (! $stock_transfer->isEditable()) {
            return back()->withErrors(['transfer' => 'A dispatched or received transfer cannot be deleted.']);
        }

        $number = $stock_transfer->transfer_number;
        $stock_transfer->delete();

        ActivityLog::record($request, 'Inventory', 'Deleted stock transfer', $number);

        return redirect()->route('admin.inventory.stock-transfers.index')
            ->with('status', 'Stock transfer "'.$number.'" deleted successfully.');
    }

    /**
     * Dispatch moves stock out of the source warehouse and into transit.
     */
    public function dispatch(Request $request, StockTransfer $stock_transfer): RedirectResponse
    {
        if ($stock_transfer->status !== 'draft') {
            return back()->withErrors(['transfer' => 'Only a draft transfer can be dispatched.']);
        }

        $stock_transfer->load('lines.item');

        if ($stock_transfer->lines->isEmpty()) {
            return back()->withErrors(['transfer' => 'Add at least one line before dispatching.']);
        }

        try {
            DB::transaction(function () use ($stock_transfer, $request) {
                $total = 0.0;

                foreach ($stock_transfer->lines as $line) {
                    if (! $line->item) {
                        continue;
                    }

                    $entry = $this->stock->issue($line->item, $stock_transfer->from_warehouse_id, (float) $line->quantity, [
                        'movement_type' => 'transfer_out',
                        'reference_type' => StockTransfer::class,
                        'reference_id' => $stock_transfer->id,
                        'reference_number' => $stock_transfer->transfer_number,
                        'movement_date' => $stock_transfer->transfer_date->toDateString(),
                        'created_by' => $request->user()->id,
                    ]);

                    $line->update([
                        'unit_cost' => $entry->unit_cost,
                        'total_cost' => round((float) $line->quantity * (float) $entry->unit_cost, 2),
                    ]);

                    $total += (float) $line->total_cost;
                }

                $stock_transfer->update([
                    'status' => 'dispatched',
                    'dispatched_by' => $request->user()->id,
                    'dispatch_date' => now()->toDateString(),
                    'total_cost' => round($total, 2),
                ]);
            });
        } catch (InsufficientStockException $exception) {
            return back()->withErrors(['transfer' => $exception->getMessage()]);
        }

        ActivityLog::record($request, 'Inventory', 'Dispatched stock transfer', $stock_transfer->transfer_number);

        return redirect()->route('admin.inventory.stock-transfers.show', $stock_transfer)
            ->with('status', 'Transfer dispatched. Stock has left the source warehouse and is in transit.');
    }

    /**
     * Receive brings the in-transit stock into the destination warehouse.
     */
    public function receive(Request $request, StockTransfer $stock_transfer): RedirectResponse
    {
        if ($stock_transfer->status !== 'dispatched') {
            return back()->withErrors(['transfer' => 'Only a dispatched transfer can be received.']);
        }

        $stock_transfer->load('lines.item');

        DB::transaction(function () use ($stock_transfer, $request) {
            foreach ($stock_transfer->lines as $line) {
                if (! $line->item) {
                    continue;
                }

                $this->stock->receive($line->item, $stock_transfer->to_warehouse_id, (float) $line->quantity, (float) $line->unit_cost, [
                    'movement_type' => 'transfer_in',
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $stock_transfer->id,
                    'reference_number' => $stock_transfer->transfer_number,
                    'movement_date' => now()->toDateString(),
                    'created_by' => $request->user()->id,
                ]);
            }

            $stock_transfer->update([
                'status' => 'received',
                'received_by' => $request->user()->id,
                'receive_date' => now()->toDateString(),
            ]);
        });

        ActivityLog::record($request, 'Inventory', 'Received stock transfer', $stock_transfer->transfer_number);

        return redirect()->route('admin.inventory.stock-transfers.show', $stock_transfer)
            ->with('status', 'Transfer received. Stock is now in the destination warehouse.');
    }

    /**
     * @return array{0: array, 1: array} Header data and normalized line rows.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'transfer_date' => ['required', 'date'],
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'exists:items,id'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ], [
            'to_warehouse_id.different' => 'The destination warehouse must be different from the source warehouse.',
            'lines.required' => 'Add at least one transferred item.',
        ]);

        $lines = collect($data['lines'])
            ->filter(fn ($line) => filled($line['item_id'] ?? null) && (float) ($line['quantity'] ?? 0) > 0)
            ->map(fn ($line) => [
                'item_id' => $line['item_id'],
                'quantity' => (float) $line['quantity'],
                'unit_cost' => 0,
                'total_cost' => 0,
            ])->values()->all();

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Add at least one line with an item and a quantity.']);
        }

        unset($data['lines']);
        $data['status'] = 'draft';

        return [$data, $lines];
    }

    private function formOptions(): array
    {
        return [
            'warehouses' => Warehouse::orderBy('name')->get(),
            'items' => Item::where('status', 'active')->orderBy('item_code')->get(),
            'statuses' => StockTransfer::STATUSES,
        ];
    }
}
