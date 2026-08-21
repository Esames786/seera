<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockIssue extends Model
{
    public const STATUSES = ['draft', 'posted', 'cancelled'];

    protected $fillable = [
        'issue_number', 'warehouse_id', 'project_id', 'site_id', 'requested_by',
        'approved_by', 'issue_date', 'purpose', 'total_cost', 'status',
        'accounting_posted', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'total_cost' => 'decimal:2',
            'accounting_posted' => 'boolean',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function lines()
    {
        return $this->hasMany(StockIssueLine::class);
    }

    public static function nextNumber(int $year): string
    {
        $prefix = 'ISS-'.$year.'-';

        return app(\App\Services\DocumentNumberService::class)
            ->next('stock-issue-'.$year, $prefix, 'stock_issues', 'issue_number');
    }
}
