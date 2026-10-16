<?php

namespace App\Services\Zatca;

use App\Models\ZatcaEgsUnit;
use Illuminate\Support\Carbon;

/**
 * Cryptographic stamp (Security Features v1.2 §2.3.3, XAdES B-B enveloped in
 * ext:UBLExtensions; Detailed Technical Guidelines v2 §5.2 steps 1-6):
 *  1. invoice hash of the unsigned document (ZatcaHashService),
 *  2. ECDSA-SHA256 signature of that hash with the unit's private key,
 *  3. certificate digest = base64(hex(sha256(certificate base64 string))),
 *  4. SignedProperties (SigningTime, SigningCertificate with CertDigest and
 *     IssuerSerial) and its SHA-256 digest,
 *  5. the ds:Signature with both References, SignatureValue, KeyInfo,
 *  6. the QR (ZatcaQrService) placed in AdditionalDocumentReference "QR" and
 *     the cac:Signature element.
 * The exact serialisation of the SignedProperties block matters because its
 * digest is computed over the block as embedded; the block below keeps the
 * fixed layout the official toolbox uses.
 */
final class ZatcaSignatureService
{
    public function __construct(private readonly ZatcaHashService $hashes, private readonly ZatcaCrypto $crypto, private readonly ZatcaQrService $qr) {}

    /**
     * @param  array{seller_name: string, vat_number: string, timestamp: string, total_with_vat: string, vat_total: string}  $qrFields
     * @return array{xml: string, invoice_hash: string, signature: string, qr: string, signed_at: Carbon}
     */
    public function sign(string $unsignedXml, ZatcaEgsUnit $unit, array $qrFields, bool $simplified, ?Carbon $signedAt = null): array
    {
        $certificate = $unit->signingCertificate();
        $privateKey = $unit->privateKeyPem();
        if ($certificate === null || $privateKey === null) {
            throw ZatcaException::configuration('no_signing_credential', __('zatca.err_no_signing_credential'));
        }
        $signedAt = ($signedAt ?? Carbon::now())->copy()->setTimezone('UTC');
        $hashed = $this->hashes->hash($unsignedXml);
        $invoiceHash = $hashed['hash'];
        $digestBytes = base64_decode($invoiceHash, true);
        $signature = $this->crypto->sign($privateKey, (bool) config('zatca.sign_raw_digest', true) ? $digestBytes : $invoiceHash);
        $details = $this->crypto->certificateDetails($certificate);
        $certificateDigest = base64_encode(hash('sha256', $certificate));
        $signedProperties = $this->signedProperties($signedAt->format('Y-m-d\TH:i:s\Z'), $certificateDigest, $details['issuer_name'], $details['serial_number']);
        $signedPropertiesDigest = base64_encode(hash('sha256', $signedProperties, true));

        $extension = $this->extension($invoiceHash, $signedPropertiesDigest, $signature, $certificate, $signedProperties);
        $qr = $this->qr->encode($qrFields + [
            'invoice_hash' => $invoiceHash, 'signature' => $signature, 'public_key_der' => $details['public_key_der'],
            'certificate_signature' => $simplified ? $details['signature_der'] : null,
        ], $simplified);

        return ['xml' => $this->embed($unsignedXml, $extension, $qr), 'invoice_hash' => $invoiceHash, 'signature' => $signature, 'qr' => $qr, 'signed_at' => $signedAt];
    }

    private function signedProperties(string $time, string $certDigest, string $issuer, string $serial): string
    {
        $issuer = htmlspecialchars($issuer, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<xades:SignedProperties Id="xadesSignedProperties">'."\n"
            .'                                    <xades:SignedSignatureProperties>'."\n"
            .'                                        <xades:SigningTime>'.$time.'</xades:SigningTime>'."\n"
            .'                                        <xades:SigningCertificate>'."\n"
            .'                                            <xades:Cert>'."\n"
            .'                                                <xades:CertDigest>'."\n"
            .'                                                    <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>'."\n"
            .'                                                    <ds:DigestValue>'.$certDigest.'</ds:DigestValue>'."\n"
            .'                                                </xades:CertDigest>'."\n"
            .'                                                <xades:IssuerSerial>'."\n"
            .'                                                    <ds:X509IssuerName>'.$issuer.'</ds:X509IssuerName>'."\n"
            .'                                                    <ds:X509SerialNumber>'.$serial.'</ds:X509SerialNumber>'."\n"
            .'                                                </xades:IssuerSerial>'."\n"
            .'                                            </xades:Cert>'."\n"
            .'                                        </xades:SigningCertificate>'."\n"
            .'                                    </xades:SignedSignatureProperties>'."\n"
            .'                                </xades:SignedProperties>';
    }

    private function extension(string $invoiceHash, string $propertiesDigest, string $signature, string $certificate, string $signedProperties): string
    {
        return '<ext:UBLExtensions>'."\n"
            .'        <ext:UBLExtension>'."\n"
            .'            <ext:ExtensionURI>urn:oasis:names:specification:ubl:dsig:enveloped:xades</ext:ExtensionURI>'."\n"
            .'            <ext:ExtensionContent>'."\n"
            .'                <sig:UBLDocumentSignatures xmlns:sig="urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2" xmlns:sac="urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2" xmlns:sbc="urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2">'."\n"
            .'                    <sac:SignatureInformation>'."\n"
            .'                        <cbc:ID>urn:oasis:names:specification:ubl:signature:1</cbc:ID>'."\n"
            .'                        <sbc:ReferencedSignatureID>urn:oasis:names:specification:ubl:signature:Invoice</sbc:ReferencedSignatureID>'."\n"
            .'                        <ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="signature">'."\n"
            .'                            <ds:SignedInfo>'."\n"
            .'                                <ds:CanonicalizationMethod Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>'."\n"
            .'                                <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256"/>'."\n"
            .'                                <ds:Reference Id="invoiceSignedData" URI="">'."\n"
            .'                                    <ds:Transforms>'."\n"
            .'                                        <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">'."\n"
            .'                                            <ds:XPath>not(//ancestor-or-self::ext:UBLExtensions)</ds:XPath>'."\n"
            .'                                        </ds:Transform>'."\n"
            .'                                        <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">'."\n"
            .'                                            <ds:XPath>not(//ancestor-or-self::cac:Signature)</ds:XPath>'."\n"
            .'                                        </ds:Transform>'."\n"
            .'                                        <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">'."\n"
            .'                                            <ds:XPath>not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID=\'QR\'])</ds:XPath>'."\n"
            .'                                        </ds:Transform>'."\n"
            .'                                        <ds:Transform Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>'."\n"
            .'                                    </ds:Transforms>'."\n"
            .'                                    <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>'."\n"
            .'                                    <ds:DigestValue>'.$invoiceHash.'</ds:DigestValue>'."\n"
            .'                                </ds:Reference>'."\n"
            .'                                <ds:Reference Type="http://uri.etsi.org/01903#SignedProperties" URI="#xadesSignedProperties">'."\n"
            .'                                    <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>'."\n"
            .'                                    <ds:DigestValue>'.$propertiesDigest.'</ds:DigestValue>'."\n"
            .'                                </ds:Reference>'."\n"
            .'                            </ds:SignedInfo>'."\n"
            .'                            <ds:SignatureValue>'.$signature.'</ds:SignatureValue>'."\n"
            .'                            <ds:KeyInfo>'."\n"
            .'                                <ds:X509Data>'."\n"
            .'                                    <ds:X509Certificate>'.$certificate.'</ds:X509Certificate>'."\n"
            .'                                </ds:X509Data>'."\n"
            .'                            </ds:KeyInfo>'."\n"
            .'                            <ds:Object>'."\n"
            .'                                <xades:QualifyingProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Target="signature">'."\n"
            .'                                '.$signedProperties."\n"
            .'                                </xades:QualifyingProperties>'."\n"
            .'                            </ds:Object>'."\n"
            .'                        </ds:Signature>'."\n"
            .'                    </sac:SignatureInformation>'."\n"
            .'                </sig:UBLDocumentSignatures>'."\n"
            .'            </ext:ExtensionContent>'."\n"
            .'        </ext:UBLExtension>'."\n"
            .'    </ext:UBLExtensions>';
    }

    /** Fill the structural placeholders the builder wrote (whitespace outside them is untouched, so the digest holds). */
    private function embed(string $xml, string $extension, string $qr): string
    {
        $withExtension = str_replace(ZatcaInvoiceBuilder::EXT_PLACEHOLDER, $extension, $xml, $count);
        if ($count !== 1) {
            throw new ZatcaException(ZatcaException::LOCAL_VALIDATION, 'extension_placeholder_missing', __('zatca.err_xml_not_well_formed', ['detail' => 'UBLExtensions placeholder']));
        }
        $withQr = str_replace(ZatcaInvoiceBuilder::QR_PLACEHOLDER, $qr, $withExtension, $count);
        if ($count !== 1) {
            throw new ZatcaException(ZatcaException::LOCAL_VALIDATION, 'qr_placeholder_missing', __('zatca.err_xml_not_well_formed', ['detail' => 'QR placeholder']));
        }
        if ($this->hashes->hash($withQr)['hash'] !== $this->hashes->hash($xml)['hash']) {
            throw new ZatcaException(ZatcaException::LOCAL_VALIDATION, 'digest_drift', __('zatca.err_xml_not_well_formed', ['detail' => 'digest changed by stamping']));
        }

        return $withQr;
    }
}
