<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalInstance extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'requested_at' => 'datetime', 'completed_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function steps()
    {
        return $this->hasMany(ApprovalInstanceStep::class)->orderBy('step_no');
    }

    public function currentStep(): ?ApprovalInstanceStep
    {
        return $this->status === 'pending'
            ? $this->steps->first(fn ($step) => $step->is_required && $step->status === 'pending')
            : null;
    }
}
