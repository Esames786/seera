<?php

namespace App\Services\Sync;

use App\Contracts\OfflineSyncHandler;
use App\Models\OfflineSyncReceipt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Transport for queued offline actions. Each item is processed on its own,
 * under a row lock on its receipt (the client UUID), so:
 *  - the first arrival creates the receipt and runs the domain handler in the
 *    same transaction as the business record it creates;
 *  - an exact replay (same UUID, same identity) returns the stored outcome
 *    and writes nothing;
 *  - the same UUID with a different identity is refused deterministically;
 *  - a UUID first received from another user is refused;
 *  - a non-synced item (needs attention / failed) may be retried: the rules
 *    run again against the current server state.
 * One bad item never fails the batch; unexpected errors are reported per item
 * as temporary and leave no receipt behind.
 */
final class OfflineSyncService
{
    public const STATUS_ERROR = 'error';

    /**
     * @param  array<int, array{client_uuid: string, action: string, payload: array<string, mixed>, captured_at: Carbon, file?: UploadedFile|null}>  $items
     * @return array<int, array<string, mixed>>
     */
    public function process(Request $request, OfflineSyncHandler $handler, array $items): array
    {
        $user = $request->user();
        $results = [];
        foreach ($handler->order($items) as $item) {
            $results[] = ['client_uuid' => $item['client_uuid'], 'action' => $item['action']] + $this->one($request, $user, $handler, $item)->toArray();
        }

        return $results;
    }

    /** @param array<string, mixed> $item */
    private function one(Request $request, User $user, OfflineSyncHandler $handler, array $item): SyncResult
    {
        $validator = Validator::make($item['payload'], $handler->payloadRules($item['action']));
        if ($validator->fails()) {
            // Malformed payloads never earn a receipt: the identical retry would fail identically.
            return SyncResult::failed(SyncError::VALIDATION_ERROR, 'invalid_payload', $validator->errors()->first(), ['errors' => $validator->errors()->toArray()]);
        }
        $payload = $validator->validated();

        try {
            return $this->attempt($request, $user, $handler, $item['client_uuid'], $item['action'], $payload, $item['captured_at'], $item['file'] ?? null);
        } catch (QueryException $exception) {
            if ($this->isDuplicateKey($exception)) {
                // Two requests carrying the same UUID raced on the receipt insert: the loser re-reads under the lock.
                try {
                    return $this->attempt($request, $user, $handler, $item['client_uuid'], $item['action'], $payload, $item['captured_at'], $item['file'] ?? null);
                } catch (Throwable $retry) {
                    return $this->temporary($retry);
                }
            }

            return $this->temporary($exception);
        } catch (Throwable $exception) {
            return $this->temporary($exception);
        }
    }

    /** @param array<string, mixed> $payload */
    private function attempt(Request $request, User $user, OfflineSyncHandler $handler, string $uuid, string $action, array $payload, Carbon $capturedAt, ?UploadedFile $file): SyncResult
    {
        $hash = $this->hash($handler, $action, $payload, $file);

        return DB::transaction(function () use ($request, $user, $handler, $uuid, $action, $payload, $capturedAt, $file, $hash) {
            $receipt = OfflineSyncReceipt::where('client_uuid', $uuid)->lockForUpdate()->first();
            if (! $receipt) {
                $receipt = OfflineSyncReceipt::create([
                    'client_uuid' => $uuid, 'user_id' => $user->id, 'operation_type' => $handler->operation(), 'action' => $action,
                    'payload_hash' => $hash, 'status' => OfflineSyncReceipt::STATUS_RECEIVED, 'captured_at_client' => $capturedAt,
                    'first_received_at' => now(), 'attempt_count' => 0,
                ]);
            } else {
                if ((int) $receipt->user_id !== (int) $user->id) {
                    return SyncResult::needsAttention(SyncError::PERMISSION_DENIED, 'foreign_receipt', __('offline_sync.foreign_receipt'));
                }
                if ($receipt->operation_type !== $handler->operation() || $receipt->action !== $action || $receipt->payload_hash !== $hash) {
                    return SyncResult::needsAttention(SyncError::BUSINESS_CONFLICT, 'payload_mismatch', __('offline_sync.payload_mismatch'),
                        ['first_received_at' => $receipt->first_received_at?->toIso8601String(), 'receipt_status' => $receipt->status]);
                }
                if ($receipt->isSynced()) {
                    return SyncResult::replay($receipt->response ?? []);
                }
            }

            $receipt->forceFill(['attempt_count' => $receipt->attempt_count + 1, 'last_attempt_at' => now()])->save();
            $result = $handler->handle($request, $user, $receipt, $action, $payload, $capturedAt, $file);
            $receipt->forceFill([
                'status' => $result->status,
                'error_code' => $result->code,
                'error_reason' => $result->reason,
                'error_message' => $result->message !== null ? mb_substr($result->message, 0, 500) : null,
                'business_record_type' => $result->record['type'] ?? $receipt->business_record_type,
                'business_record_id' => $result->record['id'] ?? $receipt->business_record_id,
                'completed_at' => $result->status === OfflineSyncReceipt::STATUS_SYNCED ? now() : null,
                'response' => $result->status === OfflineSyncReceipt::STATUS_SYNCED ? $result->toArray() : null,
            ])->save();

            return $result;
        }, 1);
    }

    /** Identity hash: the handler's identity fields plus the attached file's content. */
    private function hash(OfflineSyncHandler $handler, string $action, array $payload, ?UploadedFile $file): string
    {
        $identity = $this->normalize($handler->identity($action, $payload));
        $identity['_file'] = $file ? hash_file('sha256', $file->getRealPath()) : null;

        return hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed> */
    private function normalize(array $values): array
    {
        ksort($values);
        foreach ($values as $key => $value) {
            $values[$key] = is_array($value) ? $this->normalize($value) : ($value === null ? null : trim((string) $value));
        }

        return $values;
    }

    private function temporary(Throwable $exception): SyncResult
    {
        report($exception);

        return SyncResult::temporary(__('offline_sync.temporary_error'));
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique') || str_contains($message, 'duplicate');
    }
}
