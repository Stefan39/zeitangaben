<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\YearlyDate;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Result;

/**
 * "im Frühjahr": the meteorological spring. "Frühjahrsferien" is a school holiday and not matched here.
 */
final class SeasonRule implements Rule
{
    public function pattern(): string
    {
        return '\bim\s+(?:fruehjahr|fruehling)\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $spring = static fn (int $year): Period => new Period(YearlyDate::on($year, 3, 1), YearlyDate::on($year, 5, 31));

        return YearCandidates::clarify(
            $match->expression,
            $context,
            ClarificationReason::Vague,
            sprintf('„%s“ ist kein genauer Zeitraum. Meinen Sie März bis Mai, oder einen bestimmten Abschnitt davon?', $match->expression),
            $spring,
            static fn (int $year): array => [new Candidate($spring($year), 'Frühjahr (März bis Mai)')],
        );
    }
}
