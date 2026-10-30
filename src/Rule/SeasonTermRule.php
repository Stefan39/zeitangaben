<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Result;

/**
 * "Nebensaison", "Hauptsaison": tourism jargon, not a calendar term.
 */
final class SeasonTermRule implements Rule
{
    public function pattern(): string
    {
        return '\b(?:nebensaison|hauptsaison|zwischensaison)\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $term = ucfirst($match->expression);

        return new Clarification(
            ClarificationReason::NotACalendarTerm,
            sprintf('„%s“ ist kein Kalenderbegriff. Welchen Zeitraum meinen Sie?', $term),
            $match->expression,
        );
    }
}
