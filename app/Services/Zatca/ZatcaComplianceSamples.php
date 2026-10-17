<?php

namespace App\Services\Zatca;

use App\Models\ZatcaEgsUnit;
use Illuminate\Support\Str;

/**
 * The documents ZATCA requires for the compliance checks of an EGS unit
 * (Detailed Technical Guidelines v2 §3.3.4.2): for functionality "T" a
 * standard invoice, debit note and credit note; for "S" the simplified
 * three. These are onboarding test documents, not business records: they
 * are never stored as customer invoices, never touch VAT or the ledger, and
 * are clearly labelled. The seller is the real frozen seller snapshot (ZATCA
 * checks it against the CSR); the buyer is a fixed sample.
 */
final class ZatcaComplianceSamples
{
    /**
     * @param  array<string, mixed>  $seller
     * @return array<int, array<string, mixed>> documents without ICV / PIH (assigned under the unit lock)
     */
    public function documents(ZatcaEgsUnit $unit, array $seller): array
    {
        $docs = [];
        $types = [];
        if ($unit->supportsStandard()) {
            $types[] = 'standard';
        }
        if ($unit->supportsSimplified()) {
            $types[] = 'simplified';
        }
        foreach ($types as $type) {
            $invoiceNumber = 'ZATCA-CHECK-'.strtoupper(substr($type, 0, 3)).'-INV';
            $docs[] = $this->document($type, '388', $invoiceNumber, $seller);
            $docs[] = $this->document($type, '383', 'ZATCA-CHECK-'.strtoupper(substr($type, 0, 3)).'-DN', $seller, $invoiceNumber, 'Compliance check debit note');
            $docs[] = $this->document($type, '381', 'ZATCA-CHECK-'.strtoupper(substr($type, 0, 3)).'-CN', $seller, $invoiceNumber, 'Compliance check credit note');
        }

        return $docs;
    }

    /**
     * Buyer VAT numbers for the sample documents. ZATCA rejects a document whose buyer VAT equals the seller VAT
     * (BR-CUSTOM-VALIDATION-01, observed on the Developer Portal sandbox on 17 Oct 2026), so the sample buyer takes
     * whichever of the two valid dummy numbers differs from the unit seller.
     */
    public const SAMPLE_BUYER_VATS = ['399999999800003', '399999999900003'];

    /** @param array<string, mixed> $seller */
    private function document(string $type, string $typeCode, string $number, array $seller, ?string $billingReference = null, ?string $reason = null): array
    {
        $now = now('UTC');

        return [
            'uuid' => (string) Str::uuid(),
            'number' => $number,
            'issue_date' => $now->toDateString(),
            'issue_time' => $now->format('H:i:s'),
            'type' => $type,
            'type_code' => $typeCode,
            'transaction_code' => ($type === 'simplified' ? '02' : '01').'00000',
            'currency' => 'SAR',
            'icv' => 0,
            'previous_hash' => ZatcaEgsUnit::FIRST_PREVIOUS_HASH,
            'seller' => $seller,
            'buyer' => [
                'name' => 'Sample Buyer for ZATCA compliance checks', 'type' => 'Company', 'vat_number' => $this->sampleBuyerVat((string) ($seller['vat_number'] ?? '')),
                'id_scheme' => 'CRN', 'id_value' => '1010000000', 'street' => 'King Fahd Road', 'building_number' => '1234',
                'district' => 'Al Olaya', 'city' => 'Riyadh', 'postal_code' => '12211', 'country_code' => 'SA',
            ],
            'supply_date' => $now->toDateString(),
            'billing_reference' => $billingReference,
            'note_reason' => $reason,
            'note' => null,
            'lines' => [[
                'id' => '1', 'name' => 'Compliance check item', 'quantity' => '1.00', 'unit_code' => 'PCE', 'unit_price' => '100.00',
                'net_amount' => '100.00', 'vat_category' => 'S', 'vat_rate' => '15.00', 'vat_amount' => '15.00', 'amount_with_vat' => '115.00',
            ]],
            'totals' => ['line_extension' => '100.00', 'tax_exclusive' => '100.00', 'vat' => '15.00', 'tax_inclusive' => '115.00', 'allowance' => '0.00', 'prepaid' => '0.00', 'payable' => '115.00',
                'vat_breakdown' => [['category' => 'S', 'rate' => '15.00', 'taxable' => '100.00', 'vat' => '15.00']]],
        ];
    }

    public function sampleBuyerVat(string $sellerVat): string
    {
        foreach (self::SAMPLE_BUYER_VATS as $vat) {
            if ($vat !== $sellerVat) {
                return $vat;
            }
        }

        return self::SAMPLE_BUYER_VATS[0];
    }
}
