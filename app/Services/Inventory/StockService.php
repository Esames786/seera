<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\StockLedgerEntry;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for every warehouse stock movement.
 *
 * Valuation is weighted average cost for this phase; the FIFO columns exist on
 * items but batch costing is deliberately not built yet. Every movement writes
 * a stock ledger entry so the ledger is always a faithful replay of stock.
 */
class StockService
{
    /**
     * Increase stock and re-average the unit cost.
     *
     * @param  array<string, mixed>  $context  movement_type, reference_*, dates, project/site, user
     */
    public function receive(Item $item, int $warehouseId, float $quantity, float $unitCost, array $context = []): StockLedgerEntry
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn () => $this->receive($item, $warehouseId, $quantity, $unitCost, $context));
        }

        if ($quantity <= 0) {
            throw new InsufficientStockException('Received quantity must be greater than zero.');
        }

        $stock = $this->lockedStockRow($item->id, $warehouseId);

        $oldQuantity = (float) $stock->quantity;
        $oldValue = (float) $stock->total_value;

        $newQuantity = $oldQuantity + $quantity;
        $newValue = $oldValue + ($quantity * $unitCost);
        $newAverage = $newQuantity > 0 ? $newValue / $newQuantity : $unitCost;

        $stock->update([
            'quantity' => round($newQuantity, 3),
            'total_value' => round($newValue, 2),
            'average_cost' => round($newAverage, 4),
        ]);

        $item->update(['average_cost' => round($this->companyAverageCost($item), 4)]);

        return $this->writeLedger($item, $warehouseId, $quantity, 0, $newQuantity, $unitCost, $context);
    }

    /**
     * Decrease stock at the current average cost. Blocks negative balances.
     */
    public function issue(Item $item, int $warehouseId, float $quantity, array $context = []): StockLedgerEntry
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn () => $this->issue($item, $warehouseId, $quantity, $context));
        }

        if ($quantity <= 0) {
            throw new InsufficientStockException('Issued quantity must be greater than zero.');
        }

        $stock = $this->lockedStockRow($item->id, $warehouseId);
        $available = (float) $stock->quantity;

        if ($quantity > $available + 0.0001) {
            throw new InsufficientStockException(
                'Not enough stock for '.$item->label().'. Available '.rtrim(rtrim(number_format($available, 3), '0'), '.').
                ', requested '.rtrim(rtrim(number_format($quantity, 3), '0'), '.').'.'
            );
        }

        $unitCost = (float) $stock->average_cost;
        $newQuantity = $available - $quantity;
        $newValue = max((float) $stock->total_value - ($quantity * $unitCost), 0);

        $stock->update([
            'quantity' => round($newQuantity, 3),
            'total_value' => round($newValue, 2),
        ]);

        $item->update(['average_cost' => round($this->companyAverageCost($item), 4)]);

        return $this->writeLedger($item, $warehouseId, 0, $quantity, $newQuantity, $unitCost, $context);
    }

    /**
     * Move stock between warehouses at the source warehouse's average cost.
     *
     * @return array{0: StockLedgerEntry, 1: StockLedgerEntry}
     */
    public function transfer(Item $item, int $fromWarehouseId, int $toWarehouseId, float $quantity, array $context = []): array
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn () => $this->transfer($item, $fromWarehouseId, $toWarehouseId, $quantity, $context));
        }

        $unitCost = (float) $this->stockRow($item->id, $fromWarehouseId)->average_cost;

        $out = $this->issue($item, $fromWarehouseId, $quantity, $context + ['movement_type' => 'transfer_out']);
        $in = $this->receive($item, $toWarehouseId, $quantity, $unitCost, $context + ['movement_type' => 'transfer_in']);

        return [$out, $in];
    }

    /**
     * Set a warehouse balance to a counted quantity, in either direction.
     */
    public function adjust(Item $item, int $warehouseId, float $targetQuantity, array $context = []): StockLedgerEntry
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn () => $this->adjust($item, $warehouseId, $targetQuantity, $context));
        }

        $stock = $this->lockedStockRow($item->id, $warehouseId);
        $difference = round($targetQuantity - (float) $stock->quantity, 3);

        if ($difference === 0.0) {
            throw new InsufficientStockException('The adjusted quantity is the same as the current quantity.');
        }

        if ($difference > 0) {
            $unitCost = (float) $stock->average_cost ?: (float) $item->average_cost;

            return $this->receive($item, $warehouseId, $difference, $unitCost, $context + ['movement_type' => 'adjustment']);
        }

        return $this->issue($item, $warehouseId, abs($difference), $context + ['movement_type' => 'adjustment']);
    }

    public function availableQuantity(int $itemId, int $warehouseId): float
    {
        $stock = WarehouseStock::where('item_id', $itemId)->where('warehouse_id', $warehouseId)->first();

        return $stock ? (float) $stock->quantity - (float) $stock->reserved_quantity : 0.0;
    }

    public function stockRow(int $itemId, int $warehouseId): WarehouseStock
    {
        return WarehouseStock::firstOrCreate(
            ['item_id' => $itemId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved_quantity' => 0, 'average_cost' => 0, 'total_value' => 0]
        );
    }

    private function lockedStockRow(int $itemId, int $warehouseId): WarehouseStock
    {
        DB::table('warehouse_stocks')->insertOrIgnore([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'reserved_quantity' => 0,
            'average_cost' => 0,
            'total_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return WarehouseStock::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Blended average across every warehouse holding the item.
     */
    private function companyAverageCost(Item $item): float
    {
        $rows = WarehouseStock::withoutGlobalScope('user_access')->where('item_id', $item->id)->get();
        $quantity = (float) $rows->sum('quantity');

        if ($quantity <= 0) {
            return (float) $item->average_cost;
        }

        return (float) $rows->sum('total_value') / $quantity;
    }

    private function writeLedger(Item $item, int $warehouseId, float $in, float $out, float $balance, float $unitCost, array $context): StockLedgerEntry
    {
        return StockLedgerEntry::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouseId,
            'movement_type' => $context['movement_type'] ?? 'adjustment',
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'reference_number' => $context['reference_number'] ?? null,
            'movement_date' => $context['movement_date'] ?? now()->toDateString(),
            'in_quantity' => round($in, 3),
            'out_quantity' => round($out, 3),
            'balance_quantity' => round($balance, 3),
            'unit_cost' => round($unitCost, 4),
            'value' => round(($in > 0 ? $in : $out) * $unitCost, 2),
            'project_id' => $context['project_id'] ?? null,
            'site_id' => $context['site_id'] ?? null,
            'created_by' => $context['created_by'] ?? null,
        ]);
    }
}
