<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One version of a project's detailed budget (BOQ). Draft versions are
 * editable; a version set as Current is immutable and stays readable after a
 * later revision supersedes it. Planning data only: never accounting.
 */
class ProjectBudget extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CURRENT = 'current';

    public const STATUS_SUPERSEDED = 'superseded';

    /** A draft the planner abandoned: kept for history and version numbering, never editable or current. */
    public const STATUS_DISCARDED = 'discarded';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_CURRENT, self::STATUS_SUPERSEDED, self::STATUS_DISCARDED];

    protected $fillable = [
        'project_id', 'version', 'reference', 'title', 'status', 'is_current', 'based_on_budget_id',
        'total_amount', 'line_count', 'created_by', 'finalized_by', 'finalized_at', 'superseded_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'total_amount' => 'decimal:2',
            'finalized_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCurrent(): bool
    {
        return $this->status === self::STATUS_CURRENT;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CURRENT => 'Current budget',
            self::STATUS_SUPERSEDED => 'Superseded',
            self::STATUS_DISCARDED => 'Discarded draft',
            default => 'Draft',
        };
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function lines()
    {
        return $this->hasMany(ProjectBudgetLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function basedOn()
    {
        return $this->belongsTo(self::class, 'based_on_budget_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
