<?php

namespace App\Services\Accounting;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Sales (Accounts Receivable) VAT authorization. The standard rate is 15 %;
 * a customer invoice carries one rate that every line inherits. Any other
 * manual rate is an override that only a Super Admin may save, with a written
 * reason that is logged inside the document transaction. Client-supplied
 * flags, permissions or line rates are never trusted.
 *
 * Sales has no customer or line tax classification today (zero-rated, exempt
 * or out-of-scope supplies), so a zero or reduced rate is always a manual
 * override here; the ZATCA document factory still writes category "S", which
 * the local validator refuses at 0 % (BR-S-05). Modelling the other categories
 * is a separate Finance decision; this policy does not invent one.
 *
 * Credit / debit notes inherit the posted invoice's rate and never pass
 * through this policy; posted invoices are immutable regardless.
 */
class SalesVatPolicy
{
    public const STANDARD_RATE = 15.0;

    public const ACTION = 'Overrode sales VAT';

    /** Refuse a nonstandard rate unless the Super Admin gives a reason; remember the override for the audit row. */
    public function authorize(Request $request, float $rate, string $field = 'vat_rate'): void
    {
        if (abs($rate - self::STANDARD_RATE) < 0.0001) {
            return;
        }

        if (! $request->user()?->isSuperAdmin()) {
            throw ValidationException::withMessages([$field => __('Only a Super Admin may override the standard 15% sales VAT rate.')]);
        }

        $reason = trim((string) $request->input('vat_override_reason', ''));
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['vat_override_reason' => __('Enter a reason (up to 500 characters) for the sales VAT override.')]);
        }

        $changes = $request->attributes->get('sales_vat_overrides', []);
        $changes[$field] = ['standard' => self::STANDARD_RATE, 'requested' => $rate];
        $request->attributes->set('sales_vat_overrides', $changes);
    }

    /** Lines must carry the invoice rate: a submitted line rate that differs is a forged override. */
    public function assertLinesFollowHeader(array $lines, float $rate): void
    {
        foreach ($lines as $index => $line) {
            if (filled($line['vat_rate'] ?? null) && abs((float) $line['vat_rate'] - $rate) >= 0.0001) {
                throw ValidationException::withMessages(["lines.$index.vat_rate" => __('Invoice lines carry the invoice VAT rate; a different line rate is not allowed.')]);
            }
        }
    }

    /** Call inside the document write transaction, so the audit row cannot be lost. */
    public function record(Request $request, string $document): void
    {
        $changes = $request->attributes->get('sales_vat_overrides', []);
        if ($changes !== []) {
            ActivityLog::record($request, 'Accounting', self::ACTION, $document.' | '.trim((string) $request->input('vat_override_reason')).' | '.json_encode($changes, JSON_THROW_ON_ERROR));
        }
    }
}
