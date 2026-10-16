<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Dates and times in the formats chosen under Settings → General.
 * All values are converted to the configured timezone before formatting.
 */
class Format
{
    private static function toLocal(?DateTimeInterface $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        $tz = settings('general.timezone') ?: config('app.timezone', 'UTC');

        return Carbon::instance($value)->setTimezone($tz);
    }

    public static function date(?DateTimeInterface $value): string
    {
        $local = self::toLocal($value);

        return $local ? $local->format(settings('general.date_format') ?: 'd M Y') : '';
    }

    public static function time(?DateTimeInterface $value): string
    {
        $local = self::toLocal($value);

        return $local ? $local->format(settings('general.time_format') ?: 'H:i') : '';
    }

    public static function dateTime(?DateTimeInterface $value): string
    {
        return $value ? self::date($value).' '.self::time($value) : '';
    }
}
