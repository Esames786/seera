<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable = [
        'name', 'code', 'linked_account', 'approval_required', 'mobile_visible',
        'payment_type', 'invoice_photo_required', 'vat_treatment', 'description', 'status',
    ];

    protected function casts(): array
    {
        return [
            'approval_required' => 'boolean',
            'mobile_visible' => 'boolean',
            'invoice_photo_required' => 'boolean',
        ];
    }
}
