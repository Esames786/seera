<?php

namespace App\Models;

use App\Models\Concerns\EquipmentCostDocument;
use Illuminate\Database\Eloquent\Model;

/** A fuel fill for an asset. Total = quantity × rate on the server; project / site come from the assignment at the time. */
class EquipmentFuelLog extends Model
{
    use EquipmentCostDocument;

    public const SOURCE_MODULE = 'Equipment Fuel';

    protected $fillable = [
        'fuel_number', 'equipment_asset_id', 'equipment_assignment_id', 'project_id', 'site_id', 'fuel_date', 'meter_reading',
        'quantity', 'unit', 'rate', 'taxable_amount', 'vat_rate', 'vat_amount', 'total_amount', 'supplier_id', 'reference_number',
        'payment_type', 'payment_account_id', 'expense_category_id', 'accounting_status', 'journal_entry_id', 'supplier_bill_id',
        'posting_error', 'posted_at', 'document_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fuel_date' => 'date',
            'meter_reading' => 'decimal:1',
            'quantity' => 'decimal:3',
            'rate' => 'decimal:4',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function number(): string
    {
        return $this->fuel_number;
    }

    public function documentDate()
    {
        return $this->fuel_date;
    }

    public function description(): string
    {
        return 'Fuel '.number_format((float) $this->quantity, 3).' '.$this->unit.' for '.($this->asset?->label() ?? 'asset');
    }
}
