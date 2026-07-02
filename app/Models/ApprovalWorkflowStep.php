<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowStep extends Model
{
    protected $fillable = [
        'approval_workflow_id', 'step_no', 'approver_role_id', 'approver_user_id',
        'approver_note', 'is_required', 'amount_limit', 'sla_hours',
        'escalation_role_id', 'can_reject', 'can_send_back',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'can_reject' => 'boolean',
            'can_send_back' => 'boolean',
            'amount_limit' => 'decimal:2',
        ];
    }

    public function approverUser()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function approverRole()
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    public function escalationRole()
    {
        return $this->belongsTo(Role::class, 'escalation_role_id');
    }
}
