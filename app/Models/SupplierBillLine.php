<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierBillLine extends Model
{
    protected $fillable = [
        'supplier_bill_id', 'description', 'expense_category_id', 'chart_of_account_id',
        'quantity', 'unit_price', 'taxable_amount', 'vat_rate', 'vat_amount',
        'total_amount', 'cost_center_id',
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

    public function supplierBill()
    {
        return $this->belongsTo(SupplierBill::class);
    }

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }
}
