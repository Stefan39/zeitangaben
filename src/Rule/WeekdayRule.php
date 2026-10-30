<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Result;

/**
 * A bare weekday ("am Samstag", "ab Freitag") names no date, whatever the reference date is.
 */
final class WeekdayRule implements Rule
{
    public function pattern(): string
    {
        return '\b(?:(?:ab|am)\s+)?(?:montag|dienstag|mittwoch|donnerstag|freitag|samstag|sonnabend|sonntag)\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        return new Clarification(
            ClarificationReason::Ambiguous,
            sprintf('Welches Datum meinen Sie mit „%s“? Ein Wochentag allein nennt keinen bestimmten Tag.', $match->original),
            $match->original,
        );
    }
}
