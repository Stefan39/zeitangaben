<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Result;

/**
 * "heute", "morgen", "uebermorgen", relative to the reference date.
 */
final class RelativeDayRule implements Rule
{
    private const array OFFSETS = ['heute' => 0, 'morgen' => 1, 'uebermorgen' => 2];

    public function pattern(): string
    {
        return '\b(?<day>uebermorgen|morgen|heute)\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($match->expression);
        }

        $offset = self::OFFSETS[$match->groups['day']];

        return new Resolved(Period::day($context->referenceDate->modify(sprintf('+%d days', $offset))), $match->expression);
    }
}
