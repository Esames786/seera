<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Private file of an asset (registration, insurance, inspection, service invoice, fuel receipt …). Served only through the authorized controller. */
class EquipmentDocument extends Model
{
    public const TYPES = [
        'registration' => 'Registration', 'insurance' => 'Insurance', 'inspection' => 'Inspection certificate', 'permit' => 'Permit',
        'rental_agreement' => 'Rental agreement', 'service_invoice' => 'Service invoice', 'fuel_receipt' => 'Fuel receipt', 'other' => 'Other',
    ];

    protected $fillable = [
        'equipment_asset_id', 'document_type', 'document_number', 'issue_date', 'expiry_date', 'original_filename', 'path', 'mime_type', 'size', 'uploaded_by', 'notes',
    ];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'expiry_date' => 'date'];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->document_type] ?? ucfirst((string) $this->document_type);
    }

    public function expiryState(): string
    {
        return EquipmentAsset::expiryState($this->expiry_date);
    }

    public function asset()
    {
        return $this->belongsTo(EquipmentAsset::class, 'equipment_asset_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
