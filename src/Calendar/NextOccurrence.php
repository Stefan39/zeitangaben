<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Calendar;

use Stejac\Zeitangaben\Period;

final class NextOccurrence
{
    private const int LOOKAHEAD_YEARS = 8;

    /**
     * Returns the first period, from the reference year on, whose end is on or after
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
        $firstYear = (int) $reference->format('Y');
        for ($year = $firstYear; $year <= $firstYear + self::LOOKAHEAD_YEARS; ++$year) {
            $period = $forYear($year);
            if (null !== $period && $period->end->format('Y-m-d') >= $today) {
                return $period;
            }
        }

        return null;
    }
}
