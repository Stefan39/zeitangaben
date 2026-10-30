<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

final readonly class RuleMatch
{
    /**
     * @param string                    $expression normalised text the pattern matched
     * @param string                    $original   the same span as the user wrote it
     * @param array<int|string, string> $groups     non-empty capture groups of the pattern
     */
    public function __construct(
        public string $expression,
        public string $original,
        public array $groups,
        public int $offset,
    ) {
    }
}
