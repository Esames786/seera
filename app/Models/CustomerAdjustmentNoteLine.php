<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAdjustmentNoteLine extends Model
{
    protected $fillable = [
        'customer_adjustment_note_id', 'customer_invoice_line_id', 'description', 'quantity', 'unit_price',
        'taxable_amount', 'vat_rate', 'vat_amount', 'total_amount', 'revenue_account_id', 'cost_center_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function note()
    {
        return $this->belongsTo(CustomerAdjustmentNote::class, 'customer_adjustment_note_id');
    }

    public function invoiceLine()
    {
        return $this->belongsTo(CustomerInvoiceLine::class, 'customer_invoice_line_id');
    }

    public function revenueAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'revenue_account_id');
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }
}
