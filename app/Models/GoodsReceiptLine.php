<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptLine extends Model
{
    protected $fillable = [
        'goods_receipt_id', 'item_id', 'purchase_order_line_id', 'ordered_quantity', 'received_quantity',
        'accepted_quantity', 'rejected_quantity', 'invoiced_quantity', 'unit_cost', 'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'accepted_quantity' => 'decimal:3',
            'rejected_quantity' => 'decimal:3',
            'invoiced_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
        ];
    }

    /** Accepted quantity not yet covered by an approved supplier bill. */
    public function uninvoicedQuantity(): float
    {
        return round(max((float) $this->accepted_quantity - (float) $this->invoiced_quantity, 0), 3);
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function purchaseOrderLine()
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function billMatches()
    {
        return $this->hasMany(SupplierBillGrnMatch::class);
    }
}
