<?php

namespace App\Support;

class PhoneNumber
{
    /** Normalise to E.164, assuming South Africa (+27) for local numbers. */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $digits = preg_replace('/[^\d+]/', '', trim($raw));
        if ($digits === '' || $digits === '+') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }
        if (str_starts_with($digits, '+')) {
            $e164 = '+'.preg_replace('/\D/', '', $digits);
        } elseif (str_starts_with($digits, '27') && strlen($digits) === 11) {
            $e164 = '+'.$digits;
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $e164 = '+27'.substr($digits, 1);
        } else {
            $e164 = '+'.$digits;
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $e164) ? $e164 : null;
    }

    public static function mask(?string $e164): string
    {
        if (! $e164) {
            return '';
        }

        return substr($e164, 0, 3).' ••• ••'.substr($e164, -3);
    }

    public static function display(?string $e164): string
    {
        if (! $e164) {
            return '';
        }
        if (str_starts_with($e164, '+27') && strlen($e164) === 12) {
            $local = '0'.substr($e164, 3);

            return substr($local, 0, 3).' '.substr($local, 3, 3).' '.substr($local, 6);
        }

        return $e164;
    }
}
