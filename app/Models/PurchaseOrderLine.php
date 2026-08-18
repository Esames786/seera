<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLine extends Model
{
    protected $fillable = [
        'purchase_order_id', 'item_id', 'quantity', 'received_quantity',
        'unit_price', 'taxable_amount', 'vat_rate', 'vat_amount', 'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function outstandingQuantity(): float
    {
        return max((float) $this->quantity - (float) $this->received_quantity, 0);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
