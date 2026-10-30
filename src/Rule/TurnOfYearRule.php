<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\YearlyDate;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Result;
use Stejac\Zeitangaben\Text\YearToken;

/**
 * "Jahreswechsel": no single day; the likely reading is the night from Silvester to Neujahr.
 *
 * @internal
 */
final class TurnOfYearRule implements Rule
{
    public function pattern(): string
    {
        return YearToken::around('\bjahreswechsels?\b');
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $span = static fn (int $year): Period => new Period(YearlyDate::on($year, 12, 31), YearlyDate::on($year + 1, 1, 1));

        return YearCandidates::clarify(
            $match->original,
            $context,
            ClarificationReason::Vague,
            sprintf('„%s“ ist kein genauer Zeitraum. Von welchem bis zu welchem Tag meinen Sie?', $match->original),
            $span,
            static fn (int $year): array => [new Candidate($span($year), 'Silvester und Neujahr')],
            $match->year,
        );
    }
}
