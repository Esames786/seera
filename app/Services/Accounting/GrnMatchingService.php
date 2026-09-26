<?php

namespace App\Services\Accounting;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\SupplierBill;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Matches supplier bill lines to posted goods receipt lines (F04).
 *
 * A match is saved with the draft bill and only *committed* when the bill is
 * approved: that is the moment the GRN line's invoiced quantity is consumed
 * and the journal clears GRNI. Reopening the bill releases it again. Every
 * check runs against the access-scoped GRN query, so a goods receipt the user
 * cannot see cannot be matched.
 */
class GrnMatchingService
{
    public const TOLERANCE = 0.0005;

    /**
     * Posted receipt lines that still have uninvoiced quantity, for the bill form.
     */
    public function openLines(?int $supplierId = null): Collection
    {
        return GoodsReceiptLine::query()
            ->with(['goodsReceipt.supplier', 'item'])
            ->whereHas('goodsReceipt', fn ($q) => $q->where('status', 'posted')
                ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId)))
            ->whereColumn('accepted_quantity', '>', 'invoiced_quantity')
            ->orderByDesc('goods_receipt_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * Lines a bill can be pre-filled with from one posted receipt.
     *
     * @return array<int, array<string, mixed>>
     */
    public function prefillFromReceipt(GoodsReceipt $grn): array
    {
        $grn->loadMissing('lines.item');

        return $grn->lines
            ->filter(fn (GoodsReceiptLine $line) => $line->uninvoicedQuantity() > self::TOLERANCE)
            ->map(fn (GoodsReceiptLine $line) => [
                'description' => $line->item?->label(),
                'goods_receipt_line_id' => $line->id,
                'matched_quantity' => $line->uninvoicedQuantity(),
                'quantity' => $line->uninvoicedQuantity(),
                'unit_price' => (float) $line->unit_cost,
            ])->values()->all();
    }

    /**
     * Validate the matches a bill form submitted and return them keyed by line index.
     *
     * @param  array<int, array{goods_receipt_line_id: mixed, matched_quantity: mixed}>  $requests
     * @return array<int, array<string, mixed>>
     */
    public function resolve(int $supplierId, array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map(fn ($r) => (int) $r['goods_receipt_line_id'], $requests)));
        // The global access scope applies here: an out-of-scope receipt is simply not found.
        $grnLines = GoodsReceiptLine::with(['goodsReceipt', 'item'])->whereIn('id', $ids)->get()->keyBy('id');

        $resolved = [];
        $requestedPerLine = [];

        foreach ($requests as $index => $request) {
            $lineId = (int) $request['goods_receipt_line_id'];
            $grnLine = $grnLines->get($lineId);

            if (! $grnLine) {
                $this->refuse('Received goods line #'.$lineId.' is not available to your account or no longer exists.');
            }

            $grn = $grnLine->goodsReceipt;
            if ($grn->status !== 'posted') {
                $this->refuse('Goods receipt '.$grn->grn_number.' is not posted; only posted receipts can be invoiced.');
            }
            if ((int) $grn->supplier_id !== $supplierId) {
                $this->refuse('Goods receipt '.$grn->grn_number.' belongs to a different supplier than this bill.');
            }

            $quantity = round((float) ($request['matched_quantity'] ?? 0), 3);
            if ($quantity <= 0) {
                $this->refuse('Enter the quantity of '.$grn->grn_number.' / '.($grnLine->item?->label() ?? 'item').' this bill line invoices.');
            }

            $requestedPerLine[$lineId] = round(($requestedPerLine[$lineId] ?? 0) + $quantity, 3);
            if ($requestedPerLine[$lineId] > $grnLine->uninvoicedQuantity() + self::TOLERANCE) {
                $this->refuse(sprintf(
                    '%s / %s has only %s uninvoiced; this bill asks for %s.',
                    $grn->grn_number, $grnLine->item?->label() ?? 'item',
                    rtrim(rtrim(number_format($grnLine->uninvoicedQuantity(), 3, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($requestedPerLine[$lineId], 3, '.', ''), '0'), '.')
                ));
            }

            $resolved[$index] = [
                'goods_receipt_id' => $grn->id,
                'goods_receipt_line_id' => $grnLine->id,
                'matched_quantity' => $quantity,
                'matched_taxable_amount' => round($quantity * (float) $grnLine->unit_cost, 2),
                'unit_cost' => (float) $grnLine->unit_cost,
                'item_label' => $grnLine->item?->label(),
                'grn_number' => $grn->grn_number,
            ];
        }

        return $resolved;
    }

    /**
     * Consume the matched quantities. Call inside the approval transaction with
     * the bill row already locked; the receipt lines are locked here and every
     * rule is re-checked against the current rows, so a bill approved in the
     * meantime, or a stale duplicate, is refused before anything is posted.
     */
    public function commit(SupplierBill $bill): void
    {
        $matches = $bill->grnMatches()->whereNull('committed_at')->get();
        if ($matches->isEmpty()) {
            return;
        }

        $perLine = $matches->groupBy('goods_receipt_line_id');

        foreach ($perLine as $lineId => $group) {
            $grnLine = GoodsReceiptLine::with('goodsReceipt')->whereKey($lineId)->lockForUpdate()->first();
            if (! $grnLine) {
                $this->refuse('A matched goods receipt line is no longer available; nothing was posted.');
            }

            $grn = $grnLine->goodsReceipt;
            if ($grn->status !== 'posted' || (int) $grn->supplier_id !== (int) $bill->supplier_id) {
                $this->refuse('Goods receipt '.$grn->grn_number.' can no longer be invoiced by this bill; nothing was posted.');
            }

            $wanted = round((float) $group->sum('matched_quantity'), 3);
            if ($wanted > $grnLine->uninvoicedQuantity() + self::TOLERANCE) {
                $this->refuse(sprintf(
                    'Goods receipt %s / %s has only %s uninvoiced now (another bill was approved first); this bill asks for %s. Nothing was posted.',
                    $grn->grn_number, $grnLine->item?->label() ?? 'item',
                    rtrim(rtrim(number_format($grnLine->uninvoicedQuantity(), 3, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($wanted, 3, '.', ''), '0'), '.')
                ));
            }

            $grnLine->update(['invoiced_quantity' => round((float) $grnLine->invoiced_quantity + $wanted, 3)]);
        }

        $bill->grnMatches()->whereNull('committed_at')->update(['committed_at' => now()]);
    }

    /** Give the matched quantities back when an approved bill is reopened. */
    public function release(SupplierBill $bill): void
    {
        $matches = $bill->grnMatches()->whereNotNull('committed_at')->get();

        foreach ($matches->groupBy('goods_receipt_line_id') as $lineId => $group) {
            $grnLine = GoodsReceiptLine::withoutGlobalScopes()->whereKey($lineId)->lockForUpdate()->first();
            if ($grnLine) {
                $grnLine->update(['invoiced_quantity' => round(max((float) $grnLine->invoiced_quantity - (float) $group->sum('matched_quantity'), 0), 3)]);
            }
        }

        $bill->grnMatches()->whereNotNull('committed_at')->update(['committed_at' => null]);
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['matching' => $message]);
    }
}
