<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

final readonly class RuleMatch
{
    /**
     * @param array<int|string, string> $groups non-empty capture groups of the pattern
     */
    public function __construct(
        public string $expression,
        public array $groups,
        public int $offset,
    ) {
    }
}
