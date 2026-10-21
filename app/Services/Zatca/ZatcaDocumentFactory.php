<?php

namespace App\Services\Zatca;

use App\Contracts\ZatcaDocument;
use App\Models\CustomerInvoice;
use App\Models\ZatcaInvoiceRecord;

/**
 * Turns an issued Seera document (invoice or credit / debit note, with its
 * frozen snapshots and its compliance record) into the neutral document array
 * the XML builder and the validator consume. Amounts come straight from the
 * posted document and its lines: no second monetary engine. Every number is
 * formatted from the stored decimal strings, never recomputed. A note carries
 * the original invoice number as its billing reference (BT-25) and its reason
 * (KSA-10); its amounts are positive, the type code says what they mean.
 */
final class ZatcaDocumentFactory
{
    /** @return array<string, mixed> */
    public function fromDocument(ZatcaDocument $document, ZatcaInvoiceRecord $record, int $icv, string $previousHash): array
    {
        $document->loadMissing('lines');
        if (empty($document->seller_snapshot) || empty($document->buyer_snapshot)) {
            throw ZatcaException::state('snapshot_missing', __('zatca.err_snapshot_missing'));
        }
        $type = $document->invoice_type === CustomerInvoice::TYPE_SIMPLIFIED ? 'simplified' : 'standard';
        $lines = [];
        foreach ($document->lines->values() as $index => $line) {
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
        $amounts = $document->zatcaAmounts();
        $taxable = $this->money((string) $amounts['taxable_amount']);
        $vat = $this->money((string) $amounts['vat_amount']);
        $total = $this->money((string) $amounts['total_amount']);

        return [
            'uuid' => $record->uuid,
            'number' => $document->zatcaNumber(),
            'issue_date' => $document->zatcaIssueDate()->toDateString(),
            'issue_time' => $document->issue_time ?: '00:00:00',
            'type' => $type,
            'type_code' => $document->zatcaTypeCode(),
            'transaction_code' => ($type === 'simplified' ? '02' : '01').'00000',
            'currency' => 'SAR',
            'icv' => $icv,
            'previous_hash' => $previousHash,
            'seller' => $document->seller_snapshot,
            'buyer' => $document->buyer_snapshot,
            'supply_date' => $document->supply_date?->toDateString(),
            'billing_reference' => $document->zatcaBillingReference(),
            'note_reason' => $document->zatcaNoteReason(),
            'note' => $document->zatcaFreeNote(),
            'lines' => $lines,
            'totals' => [
                'line_extension' => $taxable, 'tax_exclusive' => $taxable, 'vat' => $vat, 'tax_inclusive' => $total,
                'allowance' => '0.00', 'prepaid' => '0.00', 'payable' => $total,
                'vat_breakdown' => [['category' => 'S', 'rate' => $this->money((string) $amounts['vat_rate']), 'taxable' => $taxable, 'vat' => $vat]],
            ],
        ];
    }

    /** @deprecated kept for callers that still pass an invoice; same output as fromDocument(). */
    public function fromInvoice(CustomerInvoice $invoice, ZatcaInvoiceRecord $record, int $icv, string $previousHash): array
    {
        return $this->fromDocument($invoice, $record, $icv, $previousHash);
    }

    /** Two-decimal string, half-up (XML Implementation Standard §10). */
    public function money(string $value, int $decimals = 2): string
    {
        return number_format(round((float) $value, $decimals, PHP_ROUND_HALF_UP), $decimals, '.', '');
    }
}
