<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A supplier quotation (PDF or scanned image) attached to a purchase order.
 * Files live on the private local disk and are only served through the
 * authenticated download route.
 */
class PurchaseOrderAttachment extends Model
{
    public const DISK = 'local';

    public const DIRECTORY = 'purchase-orders/quotations';

    protected $fillable = [
        'purchase_order_id', 'file_name', 'file_path', 'mime_type', 'file_size', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->file_size;

        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0).' KB',
            default => $bytes.' B',
        };
    }
}
