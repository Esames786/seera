<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Durable server-side receipt of one queued offline action, keyed by the
 * client-generated UUID. The receipt is the idempotency authority for sync:
 * one UUID, one outcome, one business record at most.
 */
class OfflineSyncReceipt extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_NEEDS_ATTENTION = 'needs_attention';

    public const STATUS_FAILED = 'failed';

    public const OPERATION_ATTENDANCE = 'attendance';

    public const OPERATION_SITE_EXPENSE = 'site_expense';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'captured_at_client' => 'datetime',
            'first_received_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'completed_at' => 'datetime',
            'response' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function isSynced(): bool
    {
        return $this->status === self::STATUS_SYNCED;
    }

    public function statusLabel(): string
    {
        return __('offline_sync.status_'.$this->status);
    }

    public function operationLabel(): string
    {
        return __('offline_sync.operation_'.$this->operation_type.'_'.$this->action);
    }
}
