<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    public const STATUSES = ['draft', 'approved', 'partially_received', 'received', 'cancelled'];

    protected $fillable = [
        'po_number', 'purchase_request_id', 'supplier_id', 'po_date',
        'expected_delivery_date', 'project_id', 'site_id', 'warehouse_id',
        'taxable_amount', 'vat_rate', 'vat_amount', 'total_amount',
        'status', 'approved_by', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'po_date' => 'date',
            'expected_delivery_date' => 'date',
            'approved_at' => 'datetime',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function canReceive(): bool
    {
        return in_array($this->status, ['approved', 'partially_received'], true);
    }

    /** Move the PO along the receiving track from its line quantities. */
    public function refreshReceiptStatus(): void
    {
        if (in_array($this->status, ['draft', 'cancelled'], true)) {
            return;
        }

        $ordered = (float) $this->lines()->sum('quantity');
        $received = (float) $this->lines()->sum('received_quantity');

        $this->update([
            'status' => match (true) {
                $received <= 0 => 'approved',
                $received + 0.001 >= $ordered => 'received',
                default => 'partially_received',
            },
        ]);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines()
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public static function nextNumber(int $year): string
    {
        $prefix = 'PO-'.$year.'-';

        return app(\App\Services\DocumentNumberService::class)
            ->next('purchase-order-'.$year, $prefix, 'purchase_orders', 'po_number');
    }
}
