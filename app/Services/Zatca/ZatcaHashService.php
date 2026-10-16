<?php

namespace App\Services\Zatca;

use DOMDocument;
use DOMXPath;

/**
 * Invoice hash (KSA-13 / BR-KSA-26 and Security Features v1.2 §3): remove
 * the UBLExtensions block, the QR AdditionalDocumentReference and the
 * cac:Signature block, canonicalise, SHA-256, base64.
 *
 * Canonicalisation note: the standard names C14N11. PHP's DOMDocument::C14N()
 * implements Canonical XML 1.0; the two differ only for xml:base / xml:id /
 * xml:lang / xml:space inheritance, which a UBL invoice does not use, so the
 * output is byte-identical for these documents. Recorded in the source
 * baseline as a decision to confirm with the official SDK.
 */
final class ZatcaHashService
{
    public const UBL_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    public const CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    public const CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public const EXT_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    /** @return array{hash: string, canonical: string} base64 SHA-256 and the canonical bytes that were hashed */
    public function hash(string $xml): array
    {
        $canonical = $this->canonical($xml);

        return ['hash' => base64_encode(hash('sha256', $canonical, true)), 'canonical' => $canonical];
    }

    public function canonical(string $xml): string
    {
        $dom = $this->load($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ext', self::EXT_NS);
        $xpath->registerNamespace('cac', self::CAC_NS);
        $xpath->registerNamespace('cbc', self::CBC_NS);
        foreach (['//ext:UBLExtensions', "//cac:AdditionalDocumentReference[cbc:ID='QR']", '//cac:Signature'] as $query) {
            foreach (iterator_to_array($xpath->query($query)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        return $dom->C14N(false, false);
    }

    public function load(string $xml): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            $first = $errors[0] ?? null;
            throw new ZatcaException(ZatcaException::LOCAL_VALIDATION, 'xml_not_well_formed', __('zatca.err_xml_not_well_formed', ['detail' => $first ? trim($first->message) : '']));
        }

        return $dom;
    }
}
