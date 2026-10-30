<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Result;

/**
 * "diese Woche", "nächste Woche", "übernächste Woche": the Monday to Sunday week around the reference date.
 */
final class RelativeWeekNameRule implements Rule
{
    private const array WEEKS_AHEAD = ['diese' => 0, 'naechste' => 1, 'kommende' => 1, 'uebernaechste' => 2];

    public function pattern(): string
    {
        return '\b(?<which>diese|naechste|kommende|uebernaechste)\s+woche\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($match->original);
        }

        $monday = $context->referenceDate
            ->modify('monday this week')
            ->modify(sprintf('+%d days', self::WEEKS_AHEAD[$match->groups['which']] * 7));

        return new Resolved(new Period($monday, $monday->modify('+6 days')), $match->original);
    }
}
