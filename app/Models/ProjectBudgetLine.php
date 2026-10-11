<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A BOQ / budget line: cost code, category, optional site, item, unit,
 * quantity × rate (or a lump sum) and the expense account whose posted cost
 * it is compared with. Amount is always calculated on the server.
 */
class ProjectBudgetLine extends Model
{
    /** Business categories offered in Phase 1; stored as the key. */
    public const CATEGORIES = [
        'materials' => 'Materials',
        'labour' => 'Labour',
        'subcontract' => 'Subcontract',
        'site_expenses' => 'Site Expenses',
        'equipment' => 'Equipment',
        'other' => 'Other',
    ];

    protected $fillable = [
        'project_budget_id', 'sort_order', 'cost_code', 'category', 'description', 'site_id', 'item_id', 'unit_id',
        'quantity', 'rate', 'amount', 'chart_of_account_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    public static function categoryLabel(?string $key): string
    {
        return self::CATEGORIES[$key] ?? ucfirst((string) $key);
    }

    public function isLumpSum(): bool
    {
        return $this->quantity === null || $this->rate === null;
    }

    public function budget()
    {
        return $this->belongsTo(ProjectBudget::class, 'project_budget_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
}
