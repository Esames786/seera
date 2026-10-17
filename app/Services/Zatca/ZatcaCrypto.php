<?php

namespace App\Services\Zatca;

use OpenSSLAsymmetricKey;

/**
 * Key material and certificate handling on top of PHP's OpenSSL extension.
 * Nothing cryptographic is implemented by hand: ECDSA secp256k1 key pairs,
 * PKCS#10 CSRs with the ZATCA subject / extension fields, SHA-256 and ECDSA
 * signatures all come from OpenSSL (Security Features v1.2, 2.2.1 req. 6, 7,
 * 10, 16). Private keys are returned to the caller only to be stored
 * encrypted; they are never logged.
 */
final class ZatcaCrypto
{
    public const CURVE = 'secp256k1';

    /** @return array{private_key: string, public_key: string} PEM strings */
    public function generateKeyPair(): array
    {
        $config = $this->opensslConfig();
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => self::CURVE, 'config' => $config]);
        if ($key === false) {
            throw ZatcaException::configuration('key_generation_failed', __('zatca.err_openssl', ['detail' => $this->lastOpensslError()]));
        }
        if (! openssl_pkey_export($key, $private, null, ['config' => $config])) {
            throw ZatcaException::configuration('key_export_failed', __('zatca.err_openssl', ['detail' => $this->lastOpensslError()]));
        }
        $details = openssl_pkey_get_details($key);

        return ['private_key' => $private, 'public_key' => $details['key']];
    }

    /**
     * PKCS#10 CSR with the subject and the subjectAltName dirName fields of
     * Security Features v1.2 Table 1 (CSR "Subject" field content or RDNs).
     *
     * @param  array{common_name: string, organization_unit: string, organization_name: string, country_code: string, egs_serial: string, vat_number: string, invoice_types: string, location: string, industry: string}  $fields
     */
    public function generateCsr(string $privateKeyPem, array $fields, string $certificateTemplateName): string
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw ZatcaException::configuration('invalid_private_key', __('zatca.err_openssl', ['detail' => $this->lastOpensslError()]));
        }
        $configPath = $this->writeTempConfig($fields, $certificateTemplateName);
        try {
            $dn = ['C' => $fields['country_code'], 'OU' => $fields['organization_unit'], 'O' => $fields['organization_name'], 'CN' => $fields['common_name']];
            $csr = openssl_csr_new($dn, $key, ['config' => $configPath, 'digest_alg' => 'sha256', 'req_extensions' => 'v3_req']);
            if ($csr === false || ! openssl_csr_export($csr, $pem)) {
                throw ZatcaException::configuration('csr_failed', __('zatca.err_openssl', ['detail' => $this->lastOpensslError()]));
            }
        } finally {
            @unlink($configPath);
        }

        return $pem;
    }

    /** ECDSA-SHA256 signature (DER) of the given bytes, base64 encoded. */
    public function sign(string $privateKeyPem, string $bytes): string
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false || ! openssl_sign($bytes, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw ZatcaException::configuration('signing_failed', __('zatca.err_openssl', ['detail' => $this->lastOpensslError()]));
        }

        return base64_encode($signature);
    }

    public function verify(string $publicKeyPem, string $bytes, string $signatureBase64): bool
    {
        $key = openssl_pkey_get_public($publicKeyPem);

        return $key !== false && openssl_verify($bytes, base64_decode($signatureBase64, true) ?: '', $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Certificate facts needed by the stamp and the QR.
     *
     * @return array{issuer_name: string, serial_number: string, public_key_der: string, signature_der: string, not_after: int, subject: array<string, mixed>}
     */
    public function certificateDetails(string $certificateBase64): array
    {
        $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split(preg_replace('/\s+/', '', $certificateBase64), 64, "\n").'-----END CERTIFICATE-----';
        $parsed = openssl_x509_parse($pem);
        if ($parsed === false) {
            throw ZatcaException::configuration('invalid_certificate', __('zatca.err_openssl', ['detail' => $this->lastOpensslError()]));
        }
        $public = openssl_pkey_get_public($pem);
        $publicDetails = $public ? openssl_pkey_get_details($public) : false;
        if ($publicDetails === false) {
            throw ZatcaException::configuration('invalid_certificate_key', __('zatca.err_openssl', ['detail' => $this->lastOpensslError()]));
        }

        return [
            'issuer_name' => self::issuerName($parsed['issuer']),
            'serial_number' => self::serialDecimal((string) ($parsed['serialNumber'] ?? ''), (string) ($parsed['serialNumberHex'] ?? '')),
            'public_key_der' => $this->pemToDer($publicDetails['key']),
            'signature_der' => $this->certificateSignature(base64_decode(preg_replace('/\s+/', '', $certificateBase64), true) ?: ''),
            'not_after' => (int) ($parsed['validTo_time_t'] ?? 0),
            'subject' => $parsed['subject'] ?? [],
        ];
    }

    /**
     * Issuer DN in RFC 2253 order (most specific first), as X509IssuerName expects.
     * A multi-valued attribute (the ZATCA CA issuer is `DC=local, DC=gov, DC=extgazt, CN=PRZEINVOICESCA4-CA`)
     * is reversed as well, giving `CN=PRZEINVOICESCA4-CA, DC=extgazt, DC=gov, DC=local`.
     *
     * @param  array<string, string|array<int, string>>  $issuer  as returned by openssl_x509_parse()
     */
    public static function issuerName(array $issuer): string
    {
        $parts = [];
        foreach (array_reverse($issuer, true) as $key => $value) {
            foreach (array_reverse((array) $value) as $item) {
                $parts[] = $key.'='.$item;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * X509SerialNumber is an xs:integer. openssl_x509_parse() returns `serialNumber` in decimal only while it fits a
     * machine word and as `0x…` beyond that (the ZATCA production CA uses 19-byte serials), so anything that is not
     * plain digits is rebuilt from the hex form. Observed on the Developer Portal sandbox, 17 Oct 2026.
     */
    public static function serialDecimal(string $serialNumber, string $serialHex): string
    {
        if ($serialNumber !== '' && ctype_digit($serialNumber)) {
            return ltrim($serialNumber, '0') ?: '0';
        }
        $hex = preg_replace('/^0x/i', '', $serialHex !== '' ? $serialHex : $serialNumber);
        $dec = '0';
        foreach (str_split(strtolower($hex)) as $digit) {
            if (! ctype_xdigit($digit)) {
                throw ZatcaException::configuration('invalid_certificate', __('zatca.err_openssl', ['detail' => 'serial number']));
            }
            $dec = bcadd(bcmul($dec, '16'), (string) hexdec($digit));
        }

        return $dec;
    }

    private function pemToDer(string $pem): string
    {
        return base64_decode(preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem), true) ?: '';
    }

    /**
     * The CA's signature over the certificate (the BIT STRING that closes the
     * outer Certificate SEQUENCE), used as QR tag 9 for simplified documents.
     * Minimal DER walk: Certificate ::= SEQUENCE { tbsCertificate, signatureAlgorithm, signatureValue BIT STRING }.
     */
    private function certificateSignature(string $der): string
    {
        $offset = 0;
        [$tag, $length, $headerLength] = $this->tlv($der, $offset);
        if ($tag !== 0x30) {
            return '';
        }
        $offset += $headerLength;
        $end = $offset + $length;
        $elements = [];
        while ($offset < $end) {
            [$t, $l, $h] = $this->tlv($der, $offset);
            $elements[] = ['tag' => $t, 'start' => $offset + $h, 'length' => $l];
            $offset += $h + $l;
        }
        $last = end($elements);
        if (! $last || $last['tag'] !== 0x03) {
            return '';
        }
        // BIT STRING: first content byte is the number of unused bits (0 here).
        return substr($der, $last['start'] + 1, $last['length'] - 1);
    }

    /** @return array{0: int, 1: int, 2: int} tag, length, header length */
    private function tlv(string $der, int $offset): array
    {
        $tag = ord($der[$offset]);
        $first = ord($der[$offset + 1]);
        if ($first < 0x80) {
            return [$tag, $first, 2];
        }
        $bytes = $first & 0x7f;
        $length = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $length = ($length << 8) | ord($der[$offset + 2 + $i]);
        }

        return [$tag, $length, 2 + $bytes];
    }

    public function sha256Base64(string $bytes): string
    {
        return base64_encode(hash('sha256', $bytes, true));
    }

    /** OpenSSL configuration file: explicit when configured, else the PHP build's own, else none (system default). */
    public function opensslConfig(): ?string
    {
        $configured = config('zatca.openssl_config');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }
        $bundled = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';

        return is_file($bundled) ? $bundled : null;
    }

    private function writeTempConfig(array $fields, string $templateName): string
    {
        $escape = fn (string $value) => str_replace(["\r", "\n"], ' ', $value);
        $config = implode("\n", [
            '[req]', 'prompt = no', 'default_md = sha256', 'distinguished_name = dn', 'req_extensions = v3_req', '',
            '[dn]', 'C = '.$escape($fields['country_code']), 'OU = '.$escape($fields['organization_unit']), 'O = '.$escape($fields['organization_name']), 'CN = '.$escape($fields['common_name']), '',
            '[v3_req]', '1.3.6.1.4.1.311.20.2 = ASN1:PRINTABLESTRING:'.$templateName, 'subjectAltName = dirName:alt_names', '',
            '[alt_names]', 'SN = '.$escape($fields['egs_serial']), 'UID = '.$escape($fields['vat_number']), 'title = '.$escape($fields['invoice_types']),
            'registeredAddress = '.$escape($fields['location']), 'businessCategory = '.$escape($fields['industry']), '',
        ]);
        $path = tempnam(sys_get_temp_dir(), 'zatca-csr-');
        file_put_contents($path, $config);

        return $path;
    }

    private function lastOpensslError(): string
    {
        $messages = [];
        while (($error = openssl_error_string()) !== false) {
            $messages[] = $error;
        }

        return implode('; ', array_slice($messages, -3));
    }
}
