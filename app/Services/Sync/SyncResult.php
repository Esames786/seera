<?php

namespace App\Services\Sync;

use App\Models\OfflineSyncReceipt;

/**
 * The outcome of one queued item. `synced` carries the business record;
 * `needs_attention` carries a structured reason a person can act on;
 * `failed` is a permanent validation refusal (an identical retry fails the
 * same way).
 */
final class SyncResult
{
    /**
     * @param  array<string, mixed>|null  $record
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public readonly string $status,
        public readonly ?string $code,
        public readonly ?string $reason,
        public readonly ?string $message,
        public readonly ?array $record,
        public readonly array $data = [],
    ) {}

    /** @param array<string, mixed> $record */
    public static function synced(array $record, ?string $message = null, array $data = []): self
    {
        return new self(OfflineSyncReceipt::STATUS_SYNCED, null, null, $message, $record, $data);
    }

    public static function needsAttention(string $code, string $reason, string $message, array $data = [], ?array $record = null): self
    {
        return new self(OfflineSyncReceipt::STATUS_NEEDS_ATTENTION, $code, $reason, $message, $record, $data);
    }

    public static function failed(string $code, string $reason, string $message, array $data = []): self
    {
        return new self(OfflineSyncReceipt::STATUS_FAILED, $code, $reason, $message, null, $data);
    }

    /** A transient outcome: nothing was stored, the client keeps the item pending and retries later. */
    public static function temporary(string $message): self
    {
        return new self(OfflineSyncService::STATUS_ERROR, SyncError::TEMPORARY_SERVER_ERROR, 'temporary', $message, null, []);
    }

    /** The stored outcome of an already synced receipt, returned to an exact replay. */
    public static function replay(array $stored): self
    {
        return new self(OfflineSyncReceipt::STATUS_SYNCED, null, null, $stored['message'] ?? null, $stored['record'] ?? null, ($stored['data'] ?? []) + ['replay' => true]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'code' => $this->code,
            'reason' => $this->reason,
            'message' => $this->message,
            'record' => $this->record,
            'data' => $this->data,
        ];
    }
}
