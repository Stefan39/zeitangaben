<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Calendar;

use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Result;
use Stejac\Zeitangaben\Rule\Clarifications;

/**
 * Resolves a calendar day given as month and day with an optional year.
 *
 * @internal
 */
final class YearlyDate
{
    public static function resolve(string $expression, int $month, int $day, ?int $year, Context $context): Result
    {
        if (!checkdate($month, $day, $year ?? 2000)) {
            return Clarifications::invalidDate($expression);
        }
        if (null !== $year) {
            return new Resolved(Period::day(self::on($year, $month, $day)), $expression);
        }

        return self::upcoming($expression, $month, $day, $context);
    }

    private static function upcoming(string $expression, int $month, int $day, Context $context): Result
    {
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($expression);
        }

        $period = NextOccurrence::of(
            $context->referenceDate,
            static fn (int $y): ?Period => checkdate($month, $day, $y) ? Period::day(self::on($y, $month, $day)) : null,
        );

        return null === $period
            ? Clarifications::invalidDate($expression)
            : new Resolved($period, $expression);
    }

    public static function on(int $year, int $month, int $day): \DateTimeImmutable
    {
        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new \DateTimeZone('UTC'));
    }
}
