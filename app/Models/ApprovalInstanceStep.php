<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalInstanceStep extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'eligible_user_ids' => 'array', 'is_required' => 'boolean', 'can_reject' => 'boolean', 'decided_at' => 'datetime'];
    }

    public function instance()
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }
}
