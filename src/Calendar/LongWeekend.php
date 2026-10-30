<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Calendar;

use Stejac\Zeitangaben\Period;

/**
 * The stretch of days off that a public holiday creates together with the weekend.
 *
 * @internal
 */
final class LongWeekend
{
    /**
     * Returns null for a Wednesday, which does not attach to a weekend.
     */
    public static function around(\DateTimeImmutable $holiday): ?Period
    {
        return match ((int) $holiday->format('N')) {
            1 => new Period($holiday->modify('-2 days'), $holiday),
            2 => new Period($holiday->modify('-3 days'), $holiday),
            4 => new Period($holiday, $holiday->modify('+3 days')),
            5 => new Period($holiday, $holiday->modify('+2 days')),
            6 => new Period($holiday->modify('-1 day'), $holiday->modify('+1 day')),
            7 => new Period($holiday->modify('-2 days'), $holiday),
            default => null,
        };
    }
}
