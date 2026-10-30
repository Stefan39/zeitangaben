<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\Easter;
use Stejac\Zeitangaben\Calendar\LongWeekend;
use Stejac\Zeitangaben\Calendar\YearlyDate;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Result;

/**
 * "Brückentag im Mai": the Friday after Himmelfahrt and the long weekend around 1 May.
 */
final class BridgeDayRule implements Rule
{
    public function pattern(): string
    {
        return '\bbrueckentag(?:e)?\s+im\s+mai\b'.FeastPeriod::YEAR_PATTERN;
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $expression = $match->original;
        $span = FeastPeriod::find(
            $expression,
            FeastPeriod::yearOf($match),
            $context,
            fn (int $year): Period => $this->spanOf($this->candidatesIn($year)),
        );
        if (!$span instanceof Period) {
            return $span;
        }

        return new Clarification(
            ClarificationReason::Ambiguous,
            sprintf('Welchen Brückentag im Mai meinen Sie? Bei „%s“ kommen mehrere in Frage.', $expression),
            $expression,
            $this->candidatesIn((int) $span->start->format('Y')),
        );
    }

    /**
     * @return non-empty-list<Candidate>
     */
    private function candidatesIn(int $year): array
    {
        $bridge = Easter::offset($year, Easter::ASCENSION + 1);
        $candidates = [new Candidate(Period::day($bridge), 'Freitag nach Christi Himmelfahrt')];

        $mayDay = LongWeekend::around(YearlyDate::on($year, 5, 1));
        if (null !== $mayDay) {
            $candidates[] = new Candidate($mayDay, 'Langes Wochenende um den 1. Mai');
        }

        return $candidates;
    }

    /**
     * @param non-empty-list<Candidate> $candidates
     */
    private function spanOf(array $candidates): Period
    {
        $starts = array_map(static fn (Candidate $c): \DateTimeImmutable => $c->period->start, $candidates);
        $ends = array_map(static fn (Candidate $c): \DateTimeImmutable => $c->period->end, $candidates);

        return new Period(min($starts), max($ends));
    }
}
