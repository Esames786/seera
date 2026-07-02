<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    protected $fillable = [
        'name', 'module', 'trigger_action', 'department_id', 'scope',
        'auto_posting', 'notify_requester', 'lock_after_approval', 'status',
    ];

    protected function casts(): array
    {
        return [
            'notify_requester' => 'boolean',
            'lock_after_approval' => 'boolean',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function steps()
    {
        return $this->hasMany(ApprovalWorkflowStep::class)->orderBy('step_no');
    }
}
