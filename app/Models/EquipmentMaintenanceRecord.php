<?php

namespace App\Models;

use App\Models\Concerns\EquipmentCostDocument;
use Illuminate\Database\Eloquent\Model;

/** A service / repair of an asset with its cost, next-service data and completion state. Completed rows are history. */
class EquipmentMaintenanceRecord extends Model
{
    use EquipmentCostDocument;

    public const SOURCE_MODULE = 'Equipment Maintenance';

    public const TYPES = ['preventive' => 'Preventive service', 'repair' => 'Repair', 'inspection' => 'Inspection', 'other' => 'Other'];

    public const STATUSES = ['open' => 'Open', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    protected $fillable = [
        'maintenance_number', 'equipment_asset_id', 'equipment_assignment_id', 'project_id', 'site_id', 'service_date', 'meter_reading',
        'maintenance_type', 'description', 'next_service_date', 'next_service_meter', 'status', 'completed_at',
        'taxable_amount', 'vat_rate', 'vat_amount', 'total_amount', 'supplier_id', 'reference_number',
        'payment_type', 'payment_account_id', 'expense_category_id', 'accounting_status', 'journal_entry_id', 'supplier_bill_id',
        'posting_error', 'posted_at', 'document_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'next_service_date' => 'date',
            'meter_reading' => 'decimal:1',
            'next_service_meter' => 'decimal:1',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'completed_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function number(): string
    {
        return $this->maintenance_number;
    }

    public function documentDate()
    {
        return $this->service_date;
    }

    public function description(): string
    {
        return (self::TYPES[$this->maintenance_type] ?? 'Maintenance').': '.mb_substr($this->description, 0, 120);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->maintenance_type] ?? ucfirst((string) $this->maintenance_type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
