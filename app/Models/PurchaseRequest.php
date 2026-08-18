<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    public const STATUSES = ['draft', 'pending', 'approved', 'rejected', 'converted'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'pr_number', 'request_date', 'requested_by', 'project_id', 'site_id',
        'warehouse_id', 'required_date', 'priority', 'reason', 'estimated_total',
        'status', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'required_date' => 'date',
            'approved_at' => 'datetime',
            'estimated_total' => 'decimal:2',
        ];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'pending'], true);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines()
    {
        return $this->hasMany(PurchaseRequestLine::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public static function nextNumber(int $year): string
    {
        $prefix = 'PR-'.$year.'-';

        return $prefix.str_pad((string) (static::where('pr_number', 'like', $prefix.'%')->count() + 1), 4, '0', STR_PAD_LEFT);
    }
}
