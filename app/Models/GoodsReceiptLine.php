<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptLine extends Model
{
    protected $fillable = [
        'goods_receipt_id', 'item_id', 'ordered_quantity', 'received_quantity',
        'accepted_quantity', 'rejected_quantity', 'unit_cost', 'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'accepted_quantity' => 'decimal:3',
            'rejected_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
        ];
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
