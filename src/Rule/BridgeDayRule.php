<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\Easter;
use Stejac\Zeitangaben\Calendar\LongWeekend;
use Stejac\Zeitangaben\Calendar\Months;
use Stejac\Zeitangaben\Calendar\YearlyDate;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Result;
use Stejac\Zeitangaben\Text\YearToken;

/**
 * "Brückentag im Mai": the Friday after Himmelfahrt and the long weekend around 1 May.
 *
 * @internal
 */
final class BridgeDayRule implements Rule
{
    public function pattern(): string
    {
        return YearToken::around('\bbrueckentag(?:e)?\s+im\s+mai\b').'|\bbrueckentag(?:e)?\b(?!\s+im\s+(?:'.Months::PATTERN.')\b)';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $expression = $match->original;
        if (!str_contains($match->expression, ' im ')) {
            return new Clarification(
                ClarificationReason::Vague,
                sprintf('Welchen Tag meinen Sie mit „%s“? Bitte nennen Sie das Datum.', $expression),
                $expression,
            );
        }

        return YearCandidates::clarify(
            $expression,
            $context,
            ClarificationReason::Ambiguous,
            sprintf('Welchen Brückentag im Mai meinen Sie? Bei „%s“ kommen mehrere in Frage.', $expression),
            fn (int $year): Period => $this->spanOf($this->candidatesIn($year)),
            fn (int $year): array => $this->candidatesIn($year),
            $match->year,
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
