<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZatcaInvoiceRecord extends Model
{
    public const CLEARANCE_STATUSES = ['draft', 'generated', 'pending', 'cleared', 'failed', 'cancelled'];

    protected $fillable = [
        'customer_invoice_id', 'uuid', 'qr_code_data', 'xml_file_path',
        'digital_signature_status', 'clearance_status', 'zatca_response_code',
        'zatca_response_message', 'retry_count', 'cleared_at', 'failed_reason',
        'tamper_proof_hash',
    ];

    protected function casts(): array
    {
        return ['cleared_at' => 'datetime'];
    }

    public function customerInvoice()
    {
        return $this->belongsTo(CustomerInvoice::class);
    }

    public function qrStatus(): string
    {
        return $this->qr_code_data ? 'generated' : 'pending';
    }

    public function xmlStatus(): string
    {
        return $this->xml_file_path ? 'generated' : 'pending';
    }

    public function tamperProofStatus(): string
    {
        return $this->tamper_proof_hash ? 'stored' : 'pending';
    }
}
