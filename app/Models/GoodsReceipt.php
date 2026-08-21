<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    public const STATUSES = ['draft', 'posted', 'cancelled'];

    protected $fillable = [
        'grn_number', 'purchase_order_id', 'supplier_id', 'warehouse_id',
        'received_date', 'received_by', 'delivery_note_number', 'invoice_number',
        'taxable_amount', 'vat_rate', 'vat_amount', 'total_amount', 'status',
        'stock_updated', 'accounting_posted', 'journal_entry_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'stock_updated' => 'boolean',
            'accounting_posted' => 'boolean',
        ];
    }

    /** Posted stock documents are read-only. */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function lines()
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    public static function nextNumber(int $year): string
    {
        $prefix = 'GRN-'.$year.'-';

        return app(\App\Services\DocumentNumberService::class)
            ->next('grn-'.$year, $prefix, 'goods_receipts', 'grn_number');
    }
}
