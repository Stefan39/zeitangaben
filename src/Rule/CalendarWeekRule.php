<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\NextOccurrence;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Result;

/**
 * "KW 42", "Kalenderwoche 42 2027": an ISO week, Monday to Sunday.
 */
final class CalendarWeekRule implements Rule
{
    public function pattern(): string
    {
        return '\b(?:kw|kalenderwoche)\s*(?<week>\d{1,2})\b(?:\s+(?<year>\d{4})\b)?';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $week = (int) $match->groups['week'];
        if ($week < 1 || $week > 53) {
            return Clarifications::invalidWeek($match->expression);
        }
        $year = isset($match->groups['year']) ? (int) $match->groups['year'] : null;
        if (null === $year && null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($match->expression);
        }

        $period = null === $year
            ? NextOccurrence::of($context->referenceDate, static fn (int $y): ?Period => self::weekOf($y, $week))
            : self::weekOf($year, $week);

        return null === $period
            ? Clarifications::invalidWeek($match->expression)
            : new Resolved($period, $match->expression);
    }

    private static function weekOf(int $year, int $week): ?Period
    {
        $monday = (new \DateTimeImmutable('2000-01-01', new \DateTimeZone('UTC')))->setISODate($year, $week, 1);
        if ((int) $monday->format('o') !== $year || (int) $monday->format('W') !== $week) {
            return null;
        }

        return new Period($monday, $monday->modify('+6 days'));
    }
}
