<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockIssueLine extends Model
{
    protected $fillable = ['stock_issue_id', 'item_id', 'quantity', 'unit_cost', 'total_cost'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
        ];
    }

    public function stockIssue()
    {
        return $this->belongsTo(StockIssue::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
