<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    public const VALUATION_METHODS = ['average', 'fifo'];

    protected $fillable = [
        'item_code', 'name', 'description', 'item_category_id', 'unit_id',
        'valuation_method', 'reorder_level', 'minimum_stock', 'maximum_stock',
        'preferred_supplier_id', 'inventory_account_id', 'expense_account_id',
        'vat_applicable', 'average_cost', 'status',
    ];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'maximum_stock' => 'decimal:3',
            'average_cost' => 'decimal:4',
            'vat_applicable' => 'boolean',
        ];
    }

    public function label(): string
    {
        return $this->item_code.' - '.$this->name;
    }

    /** Quantity across every warehouse. */
    public function totalQuantity(): float
    {
        return (float) $this->stocks()->sum('quantity');
    }

    public function totalValue(): float
    {
        return (float) $this->stocks()->sum('total_value');
    }

    public function isLowStock(): bool
    {
        return (float) $this->reorder_level > 0 && $this->totalQuantity() <= (float) $this->reorder_level;
    }

    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function preferredSupplier()
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    public function inventoryAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_account_id');
    }

    public function expenseAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function stocks()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(StockLedgerEntry::class);
    }
}
