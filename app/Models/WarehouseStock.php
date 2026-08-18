<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseStock extends Model
{
    protected $fillable = [
        'item_id', 'warehouse_id', 'quantity', 'reserved_quantity',
        'average_cost', 'total_value',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'average_cost' => 'decimal:4',
            'total_value' => 'decimal:2',
        ];
    }

    public function availableQuantity(): float
    {
        return (float) $this->quantity - (float) $this->reserved_quantity;
    }

    public function isLowStock(): bool
    {
        $reorder = (float) $this->item?->reorder_level;

        return $reorder > 0 && (float) $this->quantity <= $reorder;
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** Rows at or below their item reorder level. */
    public static function lowStockCount(): int
    {
        return static::join('items', 'items.id', '=', 'warehouse_stocks.item_id')
            ->whereColumn('warehouse_stocks.quantity', '<=', 'items.reorder_level')
            ->where('items.reorder_level', '>', 0)
            ->count();
    }
}
