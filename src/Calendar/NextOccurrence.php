<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Calendar;

use Stejac\Zeitangaben\Period;

/**
 * @internal
 */
final class NextOccurrence
{
    private const int LOOKAHEAD_YEARS = 8;

    /**
     * Returns the first period, from the year before the reference year on (a span may cross New Year
     * and still be running), whose end is on or after
     * the reference date. A year for which the callback returns null is skipped.
     *
     * @param callable(int): ?Period $forYear
     */
    public static function of(?\DateTimeImmutable $reference, callable $forYear): ?Period
    {
        if (null === $reference) {
            return null;
        }

        $today = $reference->format('Y-m-d');
        $referenceYear = (int) $reference->format('Y');
        for ($year = $referenceYear - 1; $year <= $referenceYear + self::LOOKAHEAD_YEARS; ++$year) {
            $period = $forYear($year);
            if (null !== $period && $period->end->format('Y-m-d') >= $today) {
                return $period;
            }
        }

        return null;
    }
}
