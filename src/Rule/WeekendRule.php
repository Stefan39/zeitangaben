<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Result;

/**
 * "am Wochenende", "nächstes Wochenende", "übernächstes Wochenende". "dieses Wochenende" belongs to ThisWeekendRule.
 */
final class WeekendRule implements Rule
{
    public function pattern(): string
    {
        return '\b(?:(?<next>naechstes|kommendes)|(?<after>uebernaechstes)|(?<any>am|ein|uebers))\s+wochenende\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $expression = $match->original;
        if (isset($match->groups['any'])) {
            return new Clarification(
                ClarificationReason::Ambiguous,
                sprintf('Welches Wochenende meinen Sie mit „%s“? Bitte nennen Sie das Datum.', $expression),
                $expression,
            );
        }
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($expression);
        }

        $first = isset($match->groups['after']) ? 1 : 0;
        $monday = $context->referenceDate->modify('monday this week');

        return new Clarification(
            ClarificationReason::Ambiguous,
            sprintf('Welches Wochenende meinen Sie mit „%s“? Das Wort lässt sich auf zwei Wochenenden beziehen.', $expression),
            $expression,
            [$this->weekend($monday, $first), $this->weekend($monday, $first + 1)],
        );
    }

    private function weekend(\DateTimeImmutable $monday, int $weeksAhead): Candidate
    {
        $friday = $monday->modify(sprintf('+%d days', $weeksAhead * 7 + 4));

        return new Candidate(new Period($friday, $friday->modify('+2 days')), 'Wochenende von Freitag bis Sonntag');
    }
}
