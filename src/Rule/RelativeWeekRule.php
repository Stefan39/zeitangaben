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
 * "in zwei Wochen": the calendar week that lies that many weeks after the reference week.
 */
final class RelativeWeekRule implements Rule
{
    private const array WORDS = ['einer' => 1, 'zwei' => 2, 'drei' => 3, 'vier' => 4, 'fuenf' => 5, 'sechs' => 6];

    public function pattern(): string
    {
        return '\bin\s+(?<count>einer|zwei|drei|vier|fuenf|sechs|\d{1,2})\s+wochen?\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($match->expression);
        }

        $count = self::WORDS[$match->groups['count']] ?? (int) $match->groups['count'];
        $monday = $context->referenceDate->modify('monday this week')->modify(sprintf('+%d days', $count * 7));
        $week = new Period($monday, $monday->modify('+6 days'));

        return new Clarification(
            ClarificationReason::Vague,
            sprintf('„%s“ ist kein genauer Tag. Welchen Tag oder welche Tage meinen Sie?', $match->expression),
            $match->expression,
            [new Candidate($week, 'Kalenderwoche, Montag bis Sonntag')],
        );
    }
}
