<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One supplier bill line invoicing part (or all) of one posted goods receipt
 * line. The match is written with the draft bill; approval commits it, which
 * consumes the GRN line's invoiced quantity and clears GRNI in the journal.
 */
class SupplierBillGrnMatch extends Model
{
    protected $fillable = [
        'supplier_bill_id', 'supplier_bill_line_id', 'goods_receipt_id', 'goods_receipt_line_id',
        'matched_quantity', 'matched_taxable_amount', 'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'matched_quantity' => 'decimal:3',
            'matched_taxable_amount' => 'decimal:2',
            'committed_at' => 'datetime',
        ];
    }

    public function bill()
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    public function billLine()
    {
        return $this->belongsTo(SupplierBillLine::class, 'supplier_bill_line_id');
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function goodsReceiptLine()
    {
        return $this->belongsTo(GoodsReceiptLine::class);
    }
}
