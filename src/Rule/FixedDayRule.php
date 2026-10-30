<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\YearlyDate;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Result;

/**
 * Days with a fixed calendar date: Silvester, Neujahr, Tag der Deutschen Einheit.
 */
final class FixedDayRule implements Rule
{
    public function pattern(): string
    {
        return '\b(?:(?<silvester>silvester|jahreswechsel)|(?<neujahr>neujahr(?:stag)?)|(?<einheit>tag\s+der\s+deutschen\s+einheit))\b'
            .'(?:\s+(?<year>\d{4})\b)?';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        [$month, $day] = match (true) {
            isset($match->groups['silvester']) => [12, 31],
            isset($match->groups['neujahr']) => [1, 1],
            default => [10, 3],
        };
        $year = isset($match->groups['year']) ? (int) $match->groups['year'] : null;

        return YearlyDate::resolve($match->expression, $month, $day, $year, $context);
    }
}
