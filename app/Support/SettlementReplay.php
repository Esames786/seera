<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class SettlementReplay
{
    /** A key identifies one document AND one immutable, normalized request. */
    public static function assertMatches(Model $existing, array $expected, string $dateField): void
    {
        foreach ($expected as $field => $value) {
            if ($field === 'idempotency_key') {
                continue;
            }

            $stored = $existing->getAttribute($field);
            $matches = match ($field) {
                'amount' => (int) round((float) $stored * 100) === (int) round((float) $value * 100),
                $dateField => Carbon::parse($stored)->toDateString() === Carbon::parse($value)->toDateString(),
                default => (string) ($stored ?? '') === (string) ($value ?? ''),
            };

            if (! $matches) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'This submission key was already used for a different document or different details. Nothing was recorded. Open a new payment or receipt form for a new operation.',
                ]);
            }
        }
    }
}
