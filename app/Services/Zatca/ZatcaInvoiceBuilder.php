<?php

namespace App\Services\Zatca;

use XMLWriter;

/**
 * UBL 2.1 invoice XML per the ZATCA Electronic Invoice XML Implementation
 * Standard v1.2 and the Data Dictionary vF (2023-05-19). Built with XMLWriter
 * (never in Blade): element order follows the UBL schema, every amount is a
 * two-decimal string with currencyID, empty elements are never written
 * (BR-KSA-F-03). The three blocks that the hash transforms remove (the
 * ext:UBLExtensions stamp, the QR AdditionalDocumentReference and
 * cac:Signature) are written as placeholders in their final positions, so the
 * whitespace around them is identical before and after stamping and the
 * digest of the unsigned document is the digest of the stamped one. The
 * signature service fills the placeholders; the PIH and ICV come from the
 * caller (the EGS unit chain state).
 */
final class ZatcaInvoiceBuilder
{
    public const QR_PLACEHOLDER = 'QR-PENDING-STAMP';

    public const EXT_PLACEHOLDER = '<ext:UBLExtensions/>';

    /** @param array<string, mixed> $doc see ZatcaDocumentFactory */
    public function build(array $doc): string
    {
        $w = new XMLWriter;
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('    ');
        $w->startDocument('1.0', 'UTF-8');
        $w->startElementNs(null, 'Invoice', ZatcaHashService::UBL_NS);
        $w->writeAttribute('xmlns:cac', ZatcaHashService::CAC_NS);
        $w->writeAttribute('xmlns:cbc', ZatcaHashService::CBC_NS);
        $w->writeAttribute('xmlns:ext', ZatcaHashService::EXT_NS);
        $w->writeElement('ext:UBLExtensions');

        $this->text($w, 'cbc:ProfileID', 'reporting:1.0');
        $this->text($w, 'cbc:ID', $doc['number']);
        $this->text($w, 'cbc:UUID', $doc['uuid']);
        $this->text($w, 'cbc:IssueDate', $doc['issue_date']);
        $this->text($w, 'cbc:IssueTime', $doc['issue_time']);
        $w->startElement('cbc:InvoiceTypeCode');
        $w->writeAttribute('name', $doc['transaction_code']);
        $w->text($doc['type_code']);
        $w->endElement();
        if (! empty($doc['note'])) {
            $w->startElement('cbc:Note');
            $w->writeAttribute('languageID', 'ar');
            $w->text($doc['note']);
            $w->endElement();
        }
        $this->text($w, 'cbc:DocumentCurrencyCode', $doc['currency']);
        $this->text($w, 'cbc:TaxCurrencyCode', $doc['currency']);
        if (! empty($doc['billing_reference'])) {
            $w->startElement('cac:BillingReference');
            $w->startElement('cac:InvoiceDocumentReference');
            $this->text($w, 'cbc:ID', $doc['billing_reference']);
            $w->endElement();
            $w->endElement();
        }
        $w->startElement('cac:AdditionalDocumentReference');
        $this->text($w, 'cbc:ID', 'ICV');
        $this->text($w, 'cbc:UUID', (string) $doc['icv']);
        $w->endElement();
        $w->startElement('cac:AdditionalDocumentReference');
        $this->text($w, 'cbc:ID', 'PIH');
        $w->startElement('cac:Attachment');
        $w->startElement('cbc:EmbeddedDocumentBinaryObject');
        $w->writeAttribute('mimeCode', 'text/plain');
        $w->text($doc['previous_hash']);
        $w->endElement();
        $w->endElement();
        $w->endElement();
        $w->startElement('cac:AdditionalDocumentReference');
        $this->text($w, 'cbc:ID', 'QR');
        $w->startElement('cac:Attachment');
        $w->startElement('cbc:EmbeddedDocumentBinaryObject');
        $w->writeAttribute('mimeCode', 'text/plain');
        $w->text(self::QR_PLACEHOLDER);
        $w->endElement();
        $w->endElement();
        $w->endElement();
        $w->startElement('cac:Signature');
        $this->text($w, 'cbc:ID', 'urn:oasis:names:specification:ubl:signature:Invoice');
        $this->text($w, 'cbc:SignatureMethod', 'urn:oasis:names:specification:ubl:dsig:enveloped:xades');
        $w->endElement();

        $this->party($w, 'cac:AccountingSupplierParty', $doc['seller'], true, $doc['type']);
        $this->party($w, 'cac:AccountingCustomerParty', $doc['buyer'], false, $doc['type']);

        if (! empty($doc['supply_date'])) {
            $w->startElement('cac:Delivery');
            $this->text($w, 'cbc:ActualDeliveryDate', $doc['supply_date']);
            $w->endElement();
        }
        if (! empty($doc['note_reason'])) {
            $w->startElement('cac:PaymentMeans');
            $this->text($w, 'cbc:PaymentMeansCode', '10');
            $this->text($w, 'cbc:InstructionNote', $doc['note_reason']);
            $w->endElement();
        }

        $t = $doc['totals'];
        $w->startElement('cac:TaxTotal');
        $this->amount($w, 'cbc:TaxAmount', $t['vat'], $doc['currency']);
        foreach ($t['vat_breakdown'] as $breakdown) {
            $w->startElement('cac:TaxSubtotal');
            $this->amount($w, 'cbc:TaxableAmount', $breakdown['taxable'], $doc['currency']);
            $this->amount($w, 'cbc:TaxAmount', $breakdown['vat'], $doc['currency']);
            $w->startElement('cac:TaxCategory');
            $this->text($w, 'cbc:ID', $breakdown['category']);
            $this->text($w, 'cbc:Percent', $breakdown['rate']);
            $this->taxScheme($w);
            $w->endElement();
            $w->endElement();
        }
        $w->endElement();
        // BR-KSA-EN16931-09: one tax total without subtotals in the tax currency.
        $w->startElement('cac:TaxTotal');
        $this->amount($w, 'cbc:TaxAmount', $t['vat'], $doc['currency']);
        $w->endElement();

        $w->startElement('cac:LegalMonetaryTotal');
        $this->amount($w, 'cbc:LineExtensionAmount', $t['line_extension'], $doc['currency']);
        $this->amount($w, 'cbc:TaxExclusiveAmount', $t['tax_exclusive'], $doc['currency']);
        $this->amount($w, 'cbc:TaxInclusiveAmount', $t['tax_inclusive'], $doc['currency']);
        $this->amount($w, 'cbc:AllowanceTotalAmount', $t['allowance'], $doc['currency']);
        $this->amount($w, 'cbc:PrepaidAmount', $t['prepaid'], $doc['currency']);
        $this->amount($w, 'cbc:PayableAmount', $t['payable'], $doc['currency']);
        $w->endElement();

        foreach ($doc['lines'] as $line) {
            $w->startElement('cac:InvoiceLine');
            $this->text($w, 'cbc:ID', $line['id']);
            $w->startElement('cbc:InvoicedQuantity');
            $w->writeAttribute('unitCode', $line['unit_code']);
            $w->text($line['quantity']);
            $w->endElement();
            $this->amount($w, 'cbc:LineExtensionAmount', $line['net_amount'], $doc['currency']);
            $w->startElement('cac:TaxTotal');
            $this->amount($w, 'cbc:TaxAmount', $line['vat_amount'], $doc['currency']);
            $this->amount($w, 'cbc:RoundingAmount', $line['amount_with_vat'], $doc['currency']);
            $w->endElement();
            $w->startElement('cac:Item');
            $this->text($w, 'cbc:Name', $line['name']);
            $w->startElement('cac:ClassifiedTaxCategory');
            $this->text($w, 'cbc:ID', $line['vat_category']);
            $this->text($w, 'cbc:Percent', $line['vat_rate']);
            $this->taxScheme($w);
            $w->endElement();
            $w->endElement();
            $w->startElement('cac:Price');
            $this->amount($w, 'cbc:PriceAmount', $line['unit_price'], $doc['currency']);
            $w->endElement();
            $w->endElement();
        }

        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    /** @param array<string, mixed> $party */
    private function party(XMLWriter $w, string $element, array $party, bool $seller, string $type): void
    {
        $w->startElement($element);
        $w->startElement('cac:Party');
        $scheme = $party['id_scheme'] ?? null;
        $value = $party['id_value'] ?? '';
        // Seller: one identification (BR-KSA-08). Buyer: identification only when there is no VAT number (BR-KSA-14).
        if ($value !== '' && $scheme && ($seller || ($party['vat_number'] ?? '') === '')) {
            $w->startElement('cac:PartyIdentification');
            $w->startElement('cbc:ID');
            $w->writeAttribute('schemeID', $scheme);
            $w->text($value);
            $w->endElement();
            $w->endElement();
        }
        $address = array_filter([
            'cbc:StreetName' => $party['street'] ?? '',
            'cbc:BuildingNumber' => $party['building_number'] ?? '',
            'cbc:PlotIdentification' => $party['additional_number'] ?? '',
            'cbc:CitySubdivisionName' => $party['district'] ?? '',
            'cbc:CityName' => $party['city'] ?? '',
            'cbc:PostalZone' => $party['postal_code'] ?? '',
        ], fn ($v) => $v !== '' && $v !== null);
        if ($address !== [] || $seller || $type === 'standard') {
            $w->startElement('cac:PostalAddress');
            foreach ($address as $tag => $text) {
                $this->text($w, $tag, $text);
            }
            $w->startElement('cac:Country');
            $this->text($w, 'cbc:IdentificationCode', $party['country_code'] ?? 'SA');
            $w->endElement();
            $w->endElement();
        }
        $w->startElement('cac:PartyTaxScheme');
        if (($party['vat_number'] ?? '') !== '') {
            $this->text($w, 'cbc:CompanyID', $party['vat_number']);
        }
        $this->taxScheme($w);
        $w->endElement();
        if (($party['name'] ?? '') !== '') {
            $w->startElement('cac:PartyLegalEntity');
            $this->text($w, 'cbc:RegistrationName', $party['name']);
            $w->endElement();
        }
        $w->endElement();
        $w->endElement();
    }

    private function taxScheme(XMLWriter $w): void
    {
        $w->startElement('cac:TaxScheme');
        $this->text($w, 'cbc:ID', 'VAT');
        $w->endElement();
    }

    private function amount(XMLWriter $w, string $tag, string $value, string $currency): void
    {
        $w->startElement($tag);
        $w->writeAttribute('currencyID', $currency);
        $w->text($value);
        $w->endElement();
    }

    private function text(XMLWriter $w, string $tag, string $value): void
    {
        $w->startElement($tag);
        $w->text($value);
        $w->endElement();
    }
}
