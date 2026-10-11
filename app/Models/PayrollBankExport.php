<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A generated payroll bank transfer file, frozen at generation: the rows are
 * stored so a later download reproduces exactly the same file even if an
 * employee's bank details change afterwards. Generic CSV; not a verified
 * Saudi WPS / Mudad format.
 */
class PayrollBankExport extends Model
{
    protected $fillable = [
        'payroll_run_id', 'sequence', 'generated_by', 'row_count', 'excluded_count',
        'total_amount', 'file_name', 'file_hash', 'rows',
    ];

    protected function casts(): array
    {
        return [
            'rows' => 'array',
            'total_amount' => 'decimal:2',
        ];
    }

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
