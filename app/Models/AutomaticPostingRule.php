<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomaticPostingRule extends Model
{
    public const COST_CENTER_RULES = [
        'None', 'Employee Project / Department', 'Selected Project / Site',
        'Warehouse / Project', 'Invoice Project', 'Branch',
    ];

    protected $fillable = [
        'source_module', 'trigger_event', 'debit_account_id', 'credit_account_id',
        'cost_center_rule', 'auto_post', 'approval_required', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'auto_post' => 'boolean',
            'approval_required' => 'boolean',
        ];
    }

    public function debitAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }
}
