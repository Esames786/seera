<?php

namespace App\Services\Accounting;

use App\Models\ActivityLog;
use App\Models\GoodsReceiptLine;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Purchase tax decisions use catalogue/source records, never client tax flags. */
class PurchaseVatPolicy
{
    public const STANDARD_RATE = 15.0;

    public function itemRate(Item $item): float
    {
        // The current catalogue distinguishes VAT applicable / non-VAT only.
        // Do not invent zero-rated/exemption classifications from a typed rate.
        return $item->vat_applicable ? self::STANDARD_RATE : 0.0;
    }

    public function receiptRate(GoodsReceiptLine $line): float
    {
        // Approved PO lines are immutable. Legacy standalone receipts retain their
        // saved header treatment; later catalogue edits must not re-tax history.
        return (float) ($line->purchaseOrderLine?->vat_rate ?? $line->goodsReceipt->vat_rate);
    }

    public function authorize(Request $request, float $rate, float $expected, string $field): void
    {
        if ($rate === $expected) {
            return;
        }

        if (! $request->user()?->isSuperAdmin()) {
            throw ValidationException::withMessages([$field => __('Only a Super Admin may override the configured purchase VAT rate.')]);
        }

        $reason = $request->validate(['vat_override_reason' => ['required', 'string', 'max:500']])['vat_override_reason'];
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['vat_override_reason' => __('Enter a reason for the VAT override.')]);
        }

        $changes = $request->attributes->get('purchase_vat_overrides', []);
        $changes[$field] = ['configured' => $expected, 'requested' => $rate];
        $request->attributes->set('purchase_vat_overrides', $changes);
    }

    /** Call inside the document write transaction, so the audit cannot be lost. */
    public function record(Request $request, string $document): void
    {
        $changes = $request->attributes->get('purchase_vat_overrides', []);
        if ($changes !== []) {
            ActivityLog::record($request, 'Inventory', 'Overrode purchase VAT', $document.' | '.trim($request->input('vat_override_reason')).' | '.json_encode($changes, JSON_THROW_ON_ERROR));
        }
    }
}
