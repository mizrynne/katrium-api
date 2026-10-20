<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for how guest contact data is canonicalised before it is
 * stored or matched. Every dedupe path (staff create, FormRequest uniqueness checks,
 * OTA webhook ingest) MUST go through this class so "0917 123 4567", "+63 917-123-4567"
 * and "639171234567" all collapse to the same key.
 */
final class ContactNormalizer
{
    /** Calling code assumed for numbers written in national format (leading 0). 63 = Philippines. */
    public const DEFAULT_CALLING_CODE = '63';

    public static function email(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = mb_strtolower(trim($email));

        return $email === '' ? null : $email;
    }

    /**
     * Returns an E.164-style string ("+639171234567") or null when the input
     * cannot plausibly be a phone number (7-15 digits).
     */
    public static function phone(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        // Reject junk before any country code is prepended ("12345" is not a phone number).
        if (strlen($digits) < 7) {
            return null;
        }

        $code = self::DEFAULT_CALLING_CODE;

        if (str_starts_with($raw, '+')) {
            $e164 = $digits;
        } elseif (str_starts_with($digits, '00')) {
            $e164 = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            // National format: 0917... => 63917...
            $e164 = $code.substr($digits, 1);
        } elseif (strlen($digits) >= 11) {
            // Already carries a country code, just missing the "+".
            $e164 = $digits;
        } else {
            $e164 = $code.$digits;
        }

        // "+63 (0)917..." style: drop the redundant trunk zero after the country code.
        if (str_starts_with($e164, $code.'0')) {
            $e164 = $code.substr($e164, strlen($code) + 1);
        }

        $length = strlen($e164);

        return ($length < 7 || $length > 15) ? null : '+'.$e164;
    }
}
