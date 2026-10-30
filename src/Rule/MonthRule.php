<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\Months;
use Stejac\Zeitangaben\Calendar\NextOccurrence;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Result;

/**
 * "im Juli [2027]" and "naechsten Monat": a whole calendar month.
 */
final class MonthRule implements Rule
{
    public function pattern(): string
    {
        return '\bim\s+(?<month>'.Months::PATTERN.')\b(?:\s+(?<year>\d{4})\b)?'
            .'|\b(?<next>naechsten|kommenden)\s+monat\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        if (isset($match->groups['year'])) {
            $period = self::monthOf((int) $match->groups['year'], Months::number($match->groups['month']));

            return new Resolved($period, $match->original);
        }
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($match->original);
        }

        return new Resolved($this->upcomingPeriod($match, $context->referenceDate), $match->original);
    }

    private function upcomingPeriod(RuleMatch $match, \DateTimeImmutable $reference): Period
    {
        if (isset($match->groups['next'])) {
            $first = $reference->modify('first day of next month');

            return self::monthOf((int) $first->format('Y'), (int) $first->format('n'));
        }

        $month = Months::number($match->groups['month']);

        return NextOccurrence::of($reference, static fn (int $year): Period => self::monthOf($year, $month))
            ?? self::monthOf((int) $reference->format('Y'), $month);
    }

    private static function monthOf(int $year, int $month): Period
    {
        $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), new \DateTimeZone('UTC'));

        return new Period($first, $first->modify('last day of this month'));
    }
}
