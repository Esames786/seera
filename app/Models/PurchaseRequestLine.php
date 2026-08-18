<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestLine extends Model
{
    protected $fillable = [
        'purchase_request_id', 'item_id', 'description', 'quantity', 'unit_id',
        'estimated_unit_cost', 'estimated_total', 'budget_line',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'estimated_unit_cost' => 'decimal:4',
            'estimated_total' => 'decimal:2',
        ];
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
