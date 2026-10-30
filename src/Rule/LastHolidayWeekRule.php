<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Result;

/**
 * "die letzte Ferienwoche": depends on which holidays are meant.
 */
final class LastHolidayWeekRule implements Rule
{
    public function pattern(): string
    {
        return '\bletzte\s+ferienwoche\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        return new Clarification(
            ClarificationReason::Ambiguous,
            sprintf('Die „%s“ hängt von den Ferien ab. Welche Ferien meinen Sie?', $match->original),
            $match->original,
        );
    }
}
