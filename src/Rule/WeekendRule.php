<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\Months;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Result;

/**
 * "am Wochenende", "nächstes Wochenende", "übernächstes Wochenende". "dieses Wochenende" belongs to ThisWeekendRule.
 *
 * @internal
 */
final class WeekendRule implements Rule
{
    public function pattern(): string
    {
        return '\b(?:(?:(?<next>naechstes|naechsten|kommendes|kommenden)|(?<after>uebernaechstes|uebernaechsten)|am|ein|uebers)\s+)?wochenende\b'
            .'(?!\s+im\s+(?:'.Months::PATTERN.')\b)';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $expression = $match->original;
        if (!isset($match->groups['next']) && !isset($match->groups['after'])) {
            return new Clarification(
                ClarificationReason::Ambiguous,
                sprintf('Welches Wochenende meinen Sie mit „%s“? Bitte nennen Sie das Datum.', $expression),
                $expression,
            );
        }
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($expression);
        }

        [$first, $labels] = isset($match->groups['after'])
            ? [1, ['Zweites kommendes Wochenende', 'Drittes kommendes Wochenende']]
            : [0, ['Kommendes Wochenende', 'Wochenende danach']];
        $monday = $context->referenceDate->modify('monday this week');

        return new Clarification(
            ClarificationReason::Ambiguous,
            sprintf('Welches Wochenende meinen Sie mit „%s“? Das Wort lässt sich auf zwei Wochenenden beziehen.', $expression),
            $expression,
            [$this->weekend($monday, $first, $labels[0]), $this->weekend($monday, $first + 1, $labels[1])],
        );
    }

    private function weekend(\DateTimeImmutable $monday, int $weeksAhead, string $label): Candidate
    {
        $friday = $monday->modify(sprintf('+%d days', $weeksAhead * 7 + 4));

        return new Candidate(new Period($friday, $friday->modify('+2 days')), $label);
    }
}
