<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Result;

/**
 * "die letzte Ferienwoche" and plain "Ferien": depend on which holidays are meant.
 */
final class LastHolidayWeekRule implements Rule
{
    public function pattern(): string
    {
        return '\b(?<week>letzte\s+ferienwoche)\b|\b(?:in\s+den\s+)?ferien\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $question = isset($match->groups['week'])
            ? sprintf('Die „%s“ hängt von den Ferien ab. Welche Ferien meinen Sie?', $match->original)
            : sprintf('Welche Ferien meinen Sie mit „%s“?', $match->original);

        return new Clarification(
            ClarificationReason::Ambiguous,
            $question,
            $match->original,
        );
    }
}
