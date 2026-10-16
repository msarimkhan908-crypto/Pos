<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Resolves the current business date, honouring the configured "business day starts at" time.
 *
 * If a business day starts at 04:00 and the current time is 01:30, the sale belongs to the
 * previous calendar date (the still-open business day).
 */
class BusinessDay
{
    /**
     * The business date for the given instant (defaults to now), in the configured timezone.
     *
     * @param  CarbonInterface|null  $now  The instant to evaluate; defaults to now().
     */
    public static function current(?CarbonInterface $now = null): CarbonImmutable
    {
        $tz   = settings('general.timezone') ?? config('app.timezone', 'UTC');
        $now  = ($now ? CarbonImmutable::instance($now) : CarbonImmutable::now())->setTimezone($tz);
        $start = (string) (settings('general.business_day_start') ?? '00:00');

        [$hour, $minute] = array_map('intval', array_pad(explode(':', $start, 2), 2, 0));

        // If the current time hasn't reached the start of this calendar day's business day,
        // the sale still belongs to the previous calendar day.
        $cutoff = $now->copy()->setTime($hour, $minute, 0);

        if ($now->lt($cutoff)) {
            return $now->subDay()->toImmutable()->startOfDay()->toImmutable();
        }

        return $now->startOfDay()->toImmutable();
    }
}
