<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLedgerEntry extends Model
{
    public const MOVEMENT_TYPES = ['grn', 'issue', 'transfer_out', 'transfer_in', 'adjustment'];

    protected $fillable = [
        'item_id', 'warehouse_id', 'movement_type', 'reference_type', 'reference_id',
        'reference_number', 'movement_date', 'in_quantity', 'out_quantity',
        'balance_quantity', 'unit_cost', 'value', 'project_id', 'site_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'in_quantity' => 'decimal:3',
            'out_quantity' => 'decimal:3',
            'balance_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'value' => 'decimal:2',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
