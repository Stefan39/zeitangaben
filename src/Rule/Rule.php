<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Result;

/**
 * @internal
 */
interface Rule
{
    /**
     * Regular expression body (no delimiters, must not contain "~") matched against
     * lowercase, umlaut-transliterated text. Use \b word boundaries.
     */
    public function pattern(): string;

    public function evaluate(RuleMatch $match, Context $context): Result;
}
