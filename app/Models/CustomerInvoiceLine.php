<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerInvoiceLine extends Model
{
    protected $fillable = [
        'customer_invoice_id', 'description', 'quantity', 'unit_price',
        'taxable_amount', 'vat_rate', 'vat_amount', 'total_amount',
        'revenue_account_id', 'cost_center_id',
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

    public function customerInvoice()
    {
        return $this->belongsTo(CustomerInvoice::class);
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
