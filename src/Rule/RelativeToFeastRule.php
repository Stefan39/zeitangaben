<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Result;

/**
 * "nach Pfingsten", "vor Ostern": open-ended, so never mapped to the feast itself.
 */
final class RelativeToFeastRule implements Rule
{
    public function pattern(): string
    {
        return '\b(?:nach|vor)\s+(?:ostern|pfingsten|weihnachten|(?:christi\s+)?himmelfahrt|silvester)\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        return new Clarification(
            ClarificationReason::Vague,
            sprintf('„%s“ ist kein genauer Zeitraum. Von welchem bis zu welchem Tag meinen Sie?', $match->original),
            $match->original,
        );
    }
}
