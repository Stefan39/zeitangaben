<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Result;

/**
 * "dieses Wochenende": Friday to Sunday of the reference date's calendar week.
 */
final class ThisWeekendRule implements Rule
{
    public function pattern(): string
    {
        return '\bdieses\s+wochenende\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($match->expression);
        }

        $monday = $context->referenceDate->modify('monday this week');

        return new Resolved(new Period($monday->modify('+4 days'), $monday->modify('+6 days')), $match->expression);
    }
}
