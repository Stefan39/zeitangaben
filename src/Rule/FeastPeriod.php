<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\NextOccurrence;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\School\SchoolHoliday;

/**
 * Shared year selection and wording for the feast rules.
 */
final class FeastPeriod
{
    public const string YEAR_PATTERN = '(?:\s+(?<year>(?:19|20)\d\d)\b)?';

    public static function yearOf(RuleMatch $match): ?int
    {
        return isset($match->groups['year']) ? (int) $match->groups['year'] : null;
    }

    /**
     * An explicit year wins; otherwise the next occurrence on or after the reference date.
     *
     * @param callable(int): Period $forYear
     */
    public static function find(string $expression, ?int $year, Context $context, callable $forYear): Period|Clarification
    {
        if (null !== $year) {
            return $forYear($year);
        }
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($expression);
        }

        return NextOccurrence::of($context->referenceDate, $forYear) ?? Clarifications::invalidDate($expression);
    }

    /**
     * A feast that may mean the days of the feast or the school holidays around it.
     */
    public static function feastOrHolidays(
        string $expression,
        Period $feast,
        string $feastLabel,
        ?SchoolHoliday $holidays,
        string $holidayLabel,
    ): Clarification {
        $candidates = [new Candidate($feast, $feastLabel)];
        if (null === $holidays) {
            $question = sprintf(
                'Meinen Sie bei „%s“ die Feiertage (%s)? Für die Schulferien nennen Sie bitte das Bundesland.',
                $expression,
                self::range($feast),
            );
        } else {
            $candidates[] = new Candidate($holidays->period, $holidayLabel);
            $question = sprintf(
                'Meinen Sie bei „%s“ die Feiertage (%s) oder die Schulferien (%s)?',
                $expression,
                self::range($feast),
                self::range($holidays->period),
            );
        }

        return new Clarification(ClarificationReason::Ambiguous, $question, $expression, $candidates);
    }

    private static function range(Period $period): string
    {
        return $period->start->format('d.m.').' bis '.$period->end->format('d.m.Y');
    }
}
