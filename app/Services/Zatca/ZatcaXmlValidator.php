<?php

namespace App\Services\Zatca;

use DOMXPath;

/**
 * Local validation of a generated document before signing or submission.
 * It checks well-formedness and a subset of the XML Implementation Standard
 * v1.2 rules that Seera data can violate (identifiers, addresses, dates,
 * codes, decimals, arithmetic, VAT reconciliation). It is NOT the official
 * validation: XSD / schematron / referential checks run only in ZATCA's
 * Compliance and Enablement Toolbox (SDK) or on the platform itself, and a
 * local pass never means compliance. Results are persisted with rule ids.
 */
final class ZatcaXmlValidator
{
    public function __construct(private readonly ZatcaHashService $hashes) {}

    /**
     * @param  array<string, mixed>  $doc
     * @return array{errors: array<int, array{rule: string, code: string, message: string}>, warnings: array<int, array{rule: string, code: string, message: string}>}
     */
    public function validate(array $doc, string $xml): array
    {
        $errors = [];
        $warnings = [];
        $error = function (string $rule, string $code, array $params = []) use (&$errors) {
            $errors[] = ['rule' => $rule, 'code' => $code, 'message' => __('zatca.val_'.$code, $params)];
        };
        $warn = function (string $rule, string $code, array $params = []) use (&$warnings) {
            $warnings[] = ['rule' => $rule, 'code' => $code, 'message' => __('zatca.val_'.$code, $params)];
        };

        // Well-formedness and root.
        try {
            $dom = $this->hashes->load($xml);
        } catch (ZatcaException $e) {
            return ['errors' => [['rule' => 'XSD', 'code' => 'xml_not_well_formed', 'message' => $e->getMessage()]], 'warnings' => []];
        }
        if ($dom->documentElement->namespaceURI !== ZatcaHashService::UBL_NS || $dom->documentElement->localName !== 'Invoice') {
            $error('XSD', 'root_invalid');
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', ZatcaHashService::CAC_NS);
        $xpath->registerNamespace('cbc', ZatcaHashService::CBC_NS);
        foreach ($xpath->query('//*[not(*) and not(normalize-space())]') as $empty) {
            if ($empty->localName === 'UBLExtensions') {
                continue; // stamp placeholder, filled at signing
            }
            $error('BR-KSA-F-03', 'empty_element', ['element' => $empty->nodeName]);
            break;
        }

        // Identifiers and header.
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $doc['uuid'])) {
            $error('BR-KSA-03', 'uuid_invalid');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $doc['issue_date']) || $doc['issue_date'] > now()->toDateString()) {
            $error('BR-KSA-04', 'issue_date_invalid');
        }
        if (! preg_match('/^\d{2}:\d{2}:\d{2}$/', (string) $doc['issue_time'])) {
            $error('KSA-25', 'issue_time_invalid');
        }
        if (! in_array($doc['type_code'], ['388', '381', '383', '386'], true)) {
            $error('BR-KSA-05', 'type_code_invalid');
        }
        if (! preg_match('/^(01|02)[01]{5}$/', (string) $doc['transaction_code'])) {
            $error('BR-KSA-06', 'transaction_code_invalid');
        }
        if (in_array($doc['type_code'], ['381', '383'], true) && (empty($doc['billing_reference']) || empty($doc['note_reason']))) {
            $error('BR-KSA-17 / BT-25', 'note_reference_missing');
        }
        if ($doc['type'] === 'standard' && $doc['type_code'] === '388' && empty($doc['supply_date'])) {
            $error('BR-KSA-15', 'supply_date_missing');
        }
        if ($doc['currency'] !== 'SAR') {
            $error('BR-KSA-EN16931-02', 'currency_invalid');
        }
        if (! ctype_digit((string) $doc['icv']) || (int) $doc['icv'] < 1) {
            $error('BR-KSA-33/34', 'icv_invalid');
        }
        $pih = base64_decode((string) $doc['previous_hash'], true);
        if ($pih === false || ! (strlen($pih) === 32 || (strlen($pih) === 64 && ctype_xdigit($pih)))) {
            $error('BR-KSA-26', 'pih_invalid');
        }

        // Seller (BR-KSA-08/09/37/39/40/66).
        $seller = $doc['seller'];
        if (! preg_match('/^3\d{13}3$/', (string) $seller['vat_number'])) {
            $error('BR-KSA-39/40', 'seller_vat_invalid');
        }
        if (($seller['name'] ?? '') === '') {
            $error('BT-27', 'seller_name_missing');
        }
        foreach (['street', 'district', 'city'] as $field) {
            if (($seller[$field] ?? '') === '') {
                $error('BR-KSA-09', 'seller_address_missing', ['field' => $field]);
            }
        }
        if (! preg_match('/^\d{4}$/', (string) ($seller['building_number'] ?? ''))) {
            $error('BR-KSA-37', 'seller_building_invalid');
        }
        if (! preg_match('/^\d{5}$/', (string) ($seller['postal_code'] ?? ''))) {
            $error('BR-KSA-66', 'seller_postal_invalid');
        }
        if (($seller['id_value'] ?? '') === '' || ! preg_match('/^[A-Za-z0-9]+$/', (string) $seller['id_value'])) {
            $error('BR-KSA-08', 'seller_id_invalid');
        }

        // Buyer (standard only: BR-KSA-10/14/44).
        $buyer = $doc['buyer'];
        if ($doc['type'] === 'standard') {
            foreach (['street', 'city'] as $field) {
                if (($buyer[$field] ?? '') === '') {
                    $error('BR-KSA-10', 'buyer_address_missing', ['field' => $field]);
                }
            }
            if (($buyer['name'] ?? '') === '') {
                $error('BT-44', 'buyer_name_missing');
            }
            if (($buyer['vat_number'] ?? '') !== '') {
                if (! preg_match('/^3\d{13}3$/', (string) $buyer['vat_number'])) {
                    $error('BR-KSA-44', 'buyer_vat_invalid');
                }
            } elseif (($buyer['id_value'] ?? '') === '') {
                $error('BR-KSA-14', 'buyer_id_missing');
            }
        }

        // Lines and totals (BR-CO-10/13/15/16, BR-S-08/09, BR-KSA-DEC, KSA-11/12), two-decimal half-up throughout.
        $sumNet = '0.00';
        $sumVat = '0.00';
        foreach ($doc['lines'] as $index => $line) {
            foreach (['quantity', 'unit_price', 'net_amount', 'vat_amount', 'amount_with_vat', 'vat_rate'] as $field) {
                if (! preg_match('/^\d+\.\d{2}$/', (string) $line[$field])) {
                    $error('BR-KSA-DEC', 'line_decimals_invalid', ['line' => $index + 1, 'field' => $field]);
                }
            }
            if ((float) $line['net_amount'] < 0 || (float) $line['quantity'] < 0) {
                $error('BR-KSA-F-04', 'line_negative', ['line' => $index + 1]);
            }
            $expectedNet = $this->round((float) $line['quantity'] * (float) $line['unit_price']);
            if (bccomp($expectedNet, $line['net_amount'], 2) !== 0) {
                $error('BR-KSA-EN16931-11', 'line_net_mismatch', ['line' => $index + 1, 'expected' => $expectedNet, 'actual' => $line['net_amount']]);
            }
            $expectedVat = $this->round((float) $line['net_amount'] * (float) $line['vat_rate'] / 100);
            if (bccomp($expectedVat, $line['vat_amount'], 2) !== 0) {
                $error('KSA-11', 'line_vat_mismatch', ['line' => $index + 1, 'expected' => $expectedVat, 'actual' => $line['vat_amount']]);
            }
            if (bccomp(bcadd($line['net_amount'], $line['vat_amount'], 2), $line['amount_with_vat'], 2) !== 0) {
                $error('KSA-12', 'line_total_mismatch', ['line' => $index + 1]);
            }
            if ($line['vat_category'] === 'S' && (float) $line['vat_rate'] <= 0) {
                $error('BR-S-05', 'standard_rate_zero', ['line' => $index + 1]);
            }
            if (($line['name'] ?? '') === '') {
                $error('BT-153', 'line_name_missing', ['line' => $index + 1]);
            }
            $sumNet = bcadd($sumNet, $line['net_amount'], 2);
            $sumVat = bcadd($sumVat, $line['vat_amount'], 2);
        }
        if ($doc['lines'] === []) {
            $error('BG-25', 'no_lines');
        }
        $t = $doc['totals'];
        if (bccomp($sumNet, $t['line_extension'], 2) !== 0) {
            $error('BR-CO-10', 'line_extension_mismatch', ['expected' => $sumNet, 'actual' => $t['line_extension']]);
        }
        if (bccomp(bcsub(bcadd($t['line_extension'], '0.00', 2), $t['allowance'], 2), $t['tax_exclusive'], 2) !== 0) {
            $error('BR-CO-13', 'tax_exclusive_mismatch');
        }
        if (bccomp(bcadd($t['tax_exclusive'], $t['vat'], 2), $t['tax_inclusive'], 2) !== 0) {
            $error('BR-CO-15', 'tax_inclusive_mismatch');
        }
        if (bccomp(bcsub($t['tax_inclusive'], $t['prepaid'], 2), $t['payable'], 2) !== 0) {
            $error('BR-CO-16', 'payable_mismatch');
        }
        $breakdownVat = '0.00';
        foreach ($t['vat_breakdown'] as $breakdown) {
            // BR-S-09: category VAT = round(category taxable x rate) at document level.
            $expected = $this->round((float) $breakdown['taxable'] * (float) $breakdown['rate'] / 100);
            if (bccomp($expected, $breakdown['vat'], 2) !== 0) {
                $error('BR-S-09 / §10 Rounding', 'vat_rounding_mismatch', ['expected' => $expected, 'actual' => $breakdown['vat']]);
            }
            $breakdownVat = bcadd($breakdownVat, $breakdown['vat'], 2);
        }
        if (bccomp($breakdownVat, $t['vat'], 2) !== 0) {
            $error('BR-CO-14', 'vat_total_mismatch');
        }
        if (bccomp($sumVat, $t['vat'], 2) !== 0) {
            // Line VAT (rounded per line) and document VAT (rounded on the category total) may legitimately differ by rounding; keep it visible.
            $warn('§10 Rounding', 'line_vat_sum_differs', ['lines' => $sumVat, 'document' => $t['vat']]);
        }

        return compact('errors', 'warnings');
    }

    private function round(float $value): string
    {
        return number_format(round($value, 2, PHP_ROUND_HALF_UP), 2, '.', '');
    }
}
