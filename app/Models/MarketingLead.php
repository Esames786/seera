<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A prospect the marketing team is working on (client change request NR-16).
 * Created by a manager, taken up by the assigned staff member, moved along by
 * recorded visits, and finally won (converted to a customer) or lost.
 */
class MarketingLead extends Model
{
    public const STATUSES = [
        'new' => 'New',
        'assigned' => 'Assigned',
        'visited' => 'Visited',
        'follow_up' => 'Follow-up',
        'won' => 'Won',
        'lost' => 'Lost',
    ];

    public const OPEN_STATUSES = ['new', 'assigned', 'visited', 'follow_up'];

    public const SOURCES = ['Referral', 'Site visit', 'Tender', 'Website', 'Phone call', 'Exhibition', 'Existing customer', 'Other'];

    protected $fillable = [
        'lead_code', 'company_name', 'contact_name', 'contact_title', 'contact_phone', 'contact_email',
        'city', 'location', 'source', 'requirement', 'estimated_value', 'status',
        'assigned_to', 'created_by', 'customer_id', 'next_follow_up_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'next_follow_up_date' => 'date',
        ];
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function visits()
    {
        return $this->hasMany(MarketingVisit::class)->orderByDesc('visit_date')->orderByDesc('visit_time')->orderByDesc('id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isFollowUpDue(): bool
    {
        return $this->isOpen() && $this->next_follow_up_date && $this->next_follow_up_date->lte(today());
    }

    /**
     * Managers (Marketing "approve") and Super Admins see every lead; staff see
     * the leads assigned to them or that they created.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->hasPermission('Marketing', 'approve')) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
}
