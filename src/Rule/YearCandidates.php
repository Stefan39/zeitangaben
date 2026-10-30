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
     * @param ?int                           $year       an explicit year; then no reference date is needed
     */
    public static function clarify(
        string $expression,
        Context $context,
        ClarificationReason $reason,
        string $question,
        callable $span,
        callable $candidates,
        ?int $year = null,
    ): Clarification {
        $picked = $year ?? self::pickYear($expression, $context, $span);
        if ($picked instanceof Clarification) {
            return $picked;
        }

        return new Clarification($reason, $question, $expression, $candidates($picked));
    }

    /**
     * @param callable(int): Period $span
     */
    private static function pickYear(string $expression, Context $context, callable $span): int|Clarification
    {
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($expression);
        }

        $picked = NextOccurrence::of($context->referenceDate, $span);

        return null === $picked ? Clarifications::invalidDate($expression) : (int) $picked->start->format('Y');
    }
}
