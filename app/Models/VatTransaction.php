<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VatTransaction extends Model
{
    public const TYPES = ['input', 'output'];

    protected $fillable = [
        'transaction_date', 'source_module', 'source_id', 'source_reference',
        'party_type', 'party_id', 'party_name', 'taxable_amount', 'vat_rate',
        'vat_amount', 'vat_type', 'vat_period_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
        ];
    }

    public function vatPeriod()
    {
        return $this->belongsTo(VatPeriod::class);
    }
}
