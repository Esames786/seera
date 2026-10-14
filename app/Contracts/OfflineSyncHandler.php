<?php

namespace App\Contracts;

use App\Models\OfflineSyncReceipt;
use App\Models\User;
use App\Services\Sync\SyncResult;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * A domain handler for one offline operation type. The transport
 * (OfflineSyncService) owns the receipt, the UUID lock, replay and the
 * payload-hash conflict; the handler owns the business rules, which run
 * again in full at sync time exactly as they do online.
 */
interface OfflineSyncHandler
{
    /** The operation type stored on the receipt (OfflineSyncReceipt::OPERATION_*). */
    public function operation(): string;

    /** @return array<int, string> the actions this handler accepts */
    public function actions(): array;

    /** @return array<string, array<int, mixed>> validation rules for `payload.*` of one item */
    public function payloadRules(string $action): array;

    /**
     * The payload fields that define the identity of the action for the
     * conflict check (a replay must carry the same values).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function identity(string $action, array $payload): array;

    /**
     * Order a batch so logically earlier actions run first (for example a
     * check-in before the check-out of the same day).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function order(array $items): array;

    /**
     * Apply the action. Must not leave partial writes behind when it returns a
     * non-synced result or throws.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Request $request, User $user, OfflineSyncReceipt $receipt, string $action, array $payload, Carbon $capturedAt, ?UploadedFile $file): SyncResult;
}
