<?php

namespace App\Services\Zatca;

use App\Models\CustomerInvoice;
use App\Models\ZatcaInvoiceRecord;

/**
 * Turns an issued Seera invoice (with its frozen snapshots and its
 * compliance record) into the neutral document array the XML builder and the
 * validator consume. Amounts come straight from the posted invoice and its
 * lines: no second monetary engine. Every number is formatted from the stored
 * decimal strings, never recomputed.
 */
final class ZatcaDocumentFactory
{
    /** @return array<string, mixed> */
    public function fromInvoice(CustomerInvoice $invoice, ZatcaInvoiceRecord $record, int $icv, string $previousHash): array
    {
        $invoice->loadMissing('lines');
        if (empty($invoice->seller_snapshot) || empty($invoice->buyer_snapshot)) {
            throw ZatcaException::state('snapshot_missing', __('zatca.err_snapshot_missing'));
        }
        $type = $invoice->invoice_type === CustomerInvoice::TYPE_SIMPLIFIED ? 'simplified' : 'standard';
        $lines = [];
        foreach ($invoice->lines->values() as $index => $line) {
            $lines[] = [
                'id' => (string) ($index + 1),
                'name' => (string) $line->description,
                'quantity' => $this->money((string) $line->quantity),
                'unit_code' => 'PCE',
                'unit_price' => $this->money((string) $line->unit_price),
                'net_amount' => $this->money((string) $line->taxable_amount),
                'vat_category' => 'S',
                'vat_rate' => $this->money((string) $line->vat_rate),
                'vat_amount' => $this->money((string) $line->vat_amount),
                'amount_with_vat' => $this->money((string) $line->total_amount),
            ];
        }
        $taxable = $this->money((string) $invoice->taxable_amount);
        $vat = $this->money((string) $invoice->vat_amount);
        $total = $this->money((string) $invoice->total_amount);

        return [
            'uuid' => $record->uuid,
            'number' => (string) $invoice->invoice_number,
            'issue_date' => $invoice->invoice_date->toDateString(),
            'issue_time' => $invoice->issue_time ?: '00:00:00',
            'type' => $type,
            'type_code' => '388',
            'transaction_code' => ($type === 'simplified' ? '02' : '01').'00000',
            'currency' => 'SAR',
            'icv' => $icv,
            'previous_hash' => $previousHash,
            'seller' => $invoice->seller_snapshot,
            'buyer' => $invoice->buyer_snapshot,
            'supply_date' => $invoice->supply_date?->toDateString(),
            'billing_reference' => null,
            'note_reason' => null,
            'note' => $invoice->notes ? mb_substr(trim(preg_replace('/\s+/', ' ', $invoice->notes)), 0, 1000) : null,
            'lines' => $lines,
            'totals' => [
                'line_extension' => $taxable, 'tax_exclusive' => $taxable, 'vat' => $vat, 'tax_inclusive' => $total,
                'allowance' => '0.00', 'prepaid' => '0.00', 'payable' => $total,
                'vat_breakdown' => [['category' => 'S', 'rate' => $this->money((string) $invoice->vat_rate), 'taxable' => $taxable, 'vat' => $vat]],
            ],
        ];
    }

    /** Two-decimal string, half-up (XML Implementation Standard §10). */
    public function money(string $value, int $decimals = 2): string
    {
        return number_format(round((float) $value, $decimals, PHP_ROUND_HALF_UP), $decimals, '.', '');
    }
}
