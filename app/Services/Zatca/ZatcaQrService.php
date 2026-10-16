<?php

namespace App\Services\Zatca;

/**
 * Phase-2 QR (Security Features v1.2 §4.1, Table 3): TLV tuples, one byte tag,
 * one byte length, UTF-8 value; tags 1-8 for every document and tag 9 (the
 * CA's signature over the stamping certificate) for simplified documents and
 * their notes; the byte array base64 encoded. Built only from the signed
 * invoice's own values; Blade never computes it.
 */
final class ZatcaQrService
{
    /**
     * @param  array{seller_name: string, vat_number: string, timestamp: string, total_with_vat: string, vat_total: string, invoice_hash: string, signature: string, public_key_der: string, certificate_signature?: string|null}  $fields
     */
    public function encode(array $fields, bool $simplified): string
    {
        $tlv = $this->tuple(1, $fields['seller_name'])
            .$this->tuple(2, $fields['vat_number'])
            .$this->tuple(3, $fields['timestamp'])
            .$this->tuple(4, $fields['total_with_vat'])
            .$this->tuple(5, $fields['vat_total'])
            .$this->tuple(6, $fields['invoice_hash'])
            .$this->tuple(7, $fields['signature'])
            .$this->tuple(8, $fields['public_key_der']);
        if ($simplified) {
            if (empty($fields['certificate_signature'])) {
                throw ZatcaException::configuration('qr_tag9_missing', __('zatca.err_qr_tag9'));
            }
            $tlv .= $this->tuple(9, $fields['certificate_signature']);
        }
        $encoded = base64_encode($tlv);
        if (strlen($encoded) > 700) {
            throw new ZatcaException(ZatcaException::LOCAL_VALIDATION, 'qr_too_long', __('zatca.err_qr_too_long', ['length' => strlen($encoded)]));
        }

        return $encoded;
    }

    /** @return array<int, string> tag => raw value (for display and tests) */
    public function decode(string $base64): array
    {
        $bytes = base64_decode($base64, true) ?: '';
        $out = [];
        $offset = 0;
        while ($offset + 2 <= strlen($bytes)) {
            $tag = ord($bytes[$offset]);
            $length = ord($bytes[$offset + 1]);
            $out[$tag] = substr($bytes, $offset + 2, $length);
            $offset += 2 + $length;
        }

        return $out;
    }

    private function tuple(int $tag, string $value): string
    {
        $length = strlen($value);
        if ($length > 255) {
            throw new ZatcaException(ZatcaException::LOCAL_VALIDATION, 'qr_value_too_long', __('zatca.err_qr_value_too_long', ['tag' => $tag]));
        }

        return chr($tag).chr($length).$value;
    }
}
