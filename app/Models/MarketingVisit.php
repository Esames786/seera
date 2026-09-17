<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One visit to a prospect: who went, when and where, whom they met, what came
 * out of it and what happens next (client change request NR-16).
 */
class MarketingVisit extends Model
{
    public const OUTCOMES = [
        'interested' => 'Interested',
        'quotation_requested' => 'Quotation requested',
        'follow_up' => 'Follow-up needed',
        'not_interested' => 'Not interested',
        'won' => 'Deal won',
    ];

    protected $fillable = [
        'marketing_lead_id', 'user_id', 'visit_date', 'visit_time', 'location', 'person_met', 'person_title',
        'outcome', 'remarks', 'next_follow_up_date', 'next_action',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'next_follow_up_date' => 'date',
        ];
    }

    public function lead()
    {
        return $this->belongsTo(MarketingLead::class, 'marketing_lead_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outcomeLabel(): string
    {
        return self::OUTCOMES[$this->outcome] ?? ucfirst(str_replace('_', ' ', (string) $this->outcome));
    }

    /** "HH:MM" without seconds for display. */
    public function timeLabel(): ?string
    {
        return $this->visit_time ? substr((string) $this->visit_time, 0, 5) : null;
    }
}
