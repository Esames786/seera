<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VatPeriod extends Model
{
    public const STATUSES = ['draft', 'finalized', 'submitted'];

    protected $fillable = [
        'period_name', 'start_date', 'end_date', 'sales_taxable_amount', 'output_vat',
        'purchase_taxable_amount', 'input_vat', 'vat_payable', 'status',
        'submitted_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'submitted_at' => 'datetime',
            'sales_taxable_amount' => 'decimal:2',
            'output_vat' => 'decimal:2',
            'purchase_taxable_amount' => 'decimal:2',
            'input_vat' => 'decimal:2',
            'vat_payable' => 'decimal:2',
        ];
    }

    public function transactions()
    {
        return $this->hasMany(VatTransaction::class);
    }

    /**
     * Roll the period totals up from its VAT transactions. VAT payable = output - input.
     */
    public function recalculate(): void
    {
        DB::transaction(function () {
            $period = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            if ($period->status !== 'draft') {
                throw ValidationException::withMessages([
                    'vat' => 'A '.$period->status.' VAT period can no longer be recalculated.',
                ]);
            }

            // Locking reads see committed rows after waiting, even under MySQL
            // REPEATABLE READ. The period lock serializes all application VAT writers.
            $rows = $period->transactions()->lockForUpdate()->get();
            $output = $rows->where('vat_type', 'output');
            $input = $rows->where('vat_type', 'input');

            $outputVat = (float) (clone $output)->sum('vat_amount');
            $inputVat = (float) (clone $input)->sum('vat_amount');

            $period->update([
                'sales_taxable_amount' => round((float) (clone $output)->sum('taxable_amount'), 2),
                'output_vat' => round($outputVat, 2),
                'purchase_taxable_amount' => round((float) (clone $input)->sum('taxable_amount'), 2),
                'input_vat' => round($inputVat, 2),
                'vat_payable' => round($outputVat - $inputVat, 2),
            ]);
            $this->setRawAttributes($period->getAttributes(), true);
        });
    }
}
