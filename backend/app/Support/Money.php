<?php

namespace App\Support;

class Money
{
    public static function round(float $amount): float
    {
        return round($amount, 2);
    }

    /** Round a customer-facing total up to the nearest R5, the way a premium price list reads. */
    public static function roundQuote(float $amount): float
    {
        return ceil($amount / 5) * 5;
    }

    public static function format(float|int|string|null $amount, bool $cents = true): string
    {
        $amount = (float) ($amount ?? 0);

        return 'R'.number_format($amount, $cents ? 2 : 0, '.', ' ');
    }

    public static function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
