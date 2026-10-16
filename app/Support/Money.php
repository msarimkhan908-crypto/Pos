<?php

namespace App\Support;

use Illuminate\Support\Number;

class Money
{
    /**
     * Format an amount in the business currency (Settings → General). A currency symbol,
     * when set (e.g. "Rs"), is used instead of the ISO code formatting.
     *
     * When `general.hide_trailing_zeros` is on (the default), whole amounts are printed
     * without decimals (350) and fractional amounts use exactly 2 decimals (350.50).
     */
    public static function format(float|int|string|null $amount): string
    {
        $symbol = settings('general.currency_symbol');
        $value = (float) $amount;

        if (filled($symbol)) {
            $sign = $value < 0 ? '-' : '';

            return $sign.$symbol.' '.self::smartNumber(abs($value));
        }

        $formatted = Number::currency($value, in: self::currency());

        if (self::shouldHideTrailingZeros()) {
            $abs = round(abs($value), 2);
            if (fmod($abs, 1.0) === 0.0) {
                // Strip the trailing ".00" that Number::currency() always emits.
                $formatted = preg_replace('/\.00$/', '', $formatted) ?? $formatted;
            }
        }

        return $formatted;
    }

    /**
     * Plain number with thousands separators, applying the hide-trailing-zeros rule
     * (e.g. "350" or "350.50"). Use this for receipt line items, subtotals, etc.
     */
    public static function number(float|int|string|null $amount): string
    {
        return self::smartNumber((float) $amount);
    }

    /**
     * Always outputs exactly 2 decimals with NO thousands separators.
     * For CSV exports, sync payloads, and any machine-readable output.
     */
    public static function plain(string|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    public static function currency(): string
    {
        return (string) (settings('general.currency') ?: config('pos.currency', 'USD'));
    }

    /**
     * Format a number applying the hide-trailing-zeros rule.
     * Whole amounts get no decimal places; fractional amounts get exactly 2.
     * Preserves the sign.
     */
    private static function smartNumber(float $value): string
    {
        $abs = round(abs($value), 2);
        $sign = $value < 0 ? '-' : '';

        if (! self::shouldHideTrailingZeros()) {
            return $sign.number_format($abs, 2);
        }

        $decimals = (fmod($abs, 1.0) === 0.0) ? 0 : 2;

        return $sign.number_format($abs, $decimals);
    }

    private static function shouldHideTrailingZeros(): bool
    {
        return (bool) settings('general.hide_trailing_zeros', true);
    }
}
