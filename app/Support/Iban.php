<?php

namespace App\Support;

/**
 * IBAN handling for employee and supplier bank details: normalisation,
 * the ISO 13616 check-digit test (mod 97) and masking for screens that must
 * not show the full account. Format only; no bank or WPS validation.
 */
final class Iban
{
    /** Upper-case, without spaces or hyphens; null when nothing was entered. */
    public static function normalize(?string $value): ?string
    {
        $clean = strtoupper((string) preg_replace('/[\s\-]+/', '', (string) $value));

        return $clean === '' ? null : $clean;
    }

    public static function isValid(?string $value): bool
    {
        $iban = self::normalize($value);
        if ($iban === null || strlen($iban) < 15 || strlen($iban) > 34 || ! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            return false;
        }
        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $digits = '';
        foreach (str_split($rearranged) as $char) {
            $digits .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }
        $remainder = 0;
        foreach (str_split($digits) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder === 1;
    }

    /** "SA03 8000 0000 6080 1016 7519" → "SA•• •••• 7519": country and last four only. */
    public static function mask(?string $value): ?string
    {
        $iban = self::normalize($value);
        if ($iban === null) {
            return null;
        }
        if (strlen($iban) <= 6) {
            return str_repeat('•', strlen($iban));
        }

        return substr($iban, 0, 2).'•• •••• '.substr($iban, -4);
    }

    /** Display with a space every four characters. */
    public static function format(?string $value): ?string
    {
        $iban = self::normalize($value);

        return $iban === null ? null : trim(chunk_split($iban, 4, ' '));
    }
}
