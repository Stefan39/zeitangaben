<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\NextOccurrence;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;

/**
 * Builds a clarification whose candidates lie in the next occurrence of a yearly span.
 */
final class YearCandidates
{
    /**
     * @param callable(int): Period          $span       the span used to pick the year; it starts in that year
     * @param callable(int): list<Candidate> $candidates the candidates of the picked year
     */
    public static function clarify(
        string $expression,
        Context $context,
        ClarificationReason $reason,
        string $question,
        callable $span,
        callable $candidates,
    ): Clarification {
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($expression);
        }

        $picked = NextOccurrence::of($context->referenceDate, $span);
        if (null === $picked) {
            return Clarifications::invalidDate($expression);
        }

        return new Clarification($reason, $question, $expression, $candidates((int) $picked->start->format('Y')));
    }
}
