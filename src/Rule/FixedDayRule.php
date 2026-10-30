<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\YearlyDate;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Result;

/**
 * Days with a fixed calendar date: Silvester, Neujahr, Tag der Deutschen Einheit, Tag der Arbeit, Heiligabend and the Christmas days.
 */
final class FixedDayRule implements Rule
{
    public function pattern(): string
    {
        return '\b(?:(?<silvester>silvester|jahreswechsel)|(?<neujahr>neujahr(?:stag)?)|(?<einheit>tag\s+der\s+deutschen\s+einheit)|(?<labour>tag\s+der\s+arbeit)|(?<eve>heiligabend)|(?<xmas1>(?:1\.|erster|ersten)\s+weihnachtstag)|(?<xmas2>(?:2\.|zweiter|zweiten)\s+weihnachtstag))\b'
            .'(?:\s+(?<year>\d{4})\b)?';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        [$month, $day] = match (true) {
            isset($match->groups['silvester']) => [12, 31],
            isset($match->groups['neujahr']) => [1, 1],
            isset($match->groups['labour']) => [5, 1],
            isset($match->groups['eve']) => [12, 24],
            isset($match->groups['xmas1']) => [12, 25],
            isset($match->groups['xmas2']) => [12, 26],
            default => [10, 3],
        };
        $year = isset($match->groups['year']) ? (int) $match->groups['year'] : null;

        return YearlyDate::resolve($match->original, $month, $day, $year, $context);
    }
}
