<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Result;

/**
 * "heute", "morgen", "uebermorgen", relative to the reference date.
 *
 * @internal
 */
final class RelativeDayRule implements Rule
{
    private const array OFFSETS = ['heute' => 0, 'morgen' => 1, 'uebermorgen' => 2];

    public function pattern(): string
    {
        return '\b(?:(?<day>uebermorgen|morgen)|(?<today>heute)(?:\s+(?:morgen|frueh|vormittag|mittag|nachmittag|abend|nacht))?)\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($match->original);
        }

        $offset = self::OFFSETS[$match->groups['day'] ?? 'heute'];

        return new Resolved(Period::day($context->referenceDate->modify(sprintf('+%d days', $offset))), $match->original);
    }
}
