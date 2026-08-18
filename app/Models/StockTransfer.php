<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    public const STATUSES = ['draft', 'dispatched', 'received', 'cancelled'];

    protected $fillable = [
        'transfer_number', 'transfer_date', 'from_warehouse_id', 'to_warehouse_id',
        'requested_by', 'approved_by', 'dispatched_by', 'received_by',
        'dispatch_date', 'receive_date', 'total_cost', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'dispatch_date' => 'date',
            'receive_date' => 'date',
            'total_cost' => 'decimal:2',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function lines()
    {
        return $this->hasMany(StockTransferLine::class);
    }

    public static function nextNumber(int $year): string
    {
        $prefix = 'TRF-'.$year.'-';

        return $prefix.str_pad((string) (static::where('transfer_number', 'like', $prefix.'%')->count() + 1), 4, '0', STR_PAD_LEFT);
    }
}
