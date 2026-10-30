<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

/**
 * @internal
 */
final readonly class RuleMatch
{
    /**
     * @param string                    $expression normalised text the pattern matched
     * @param string                    $original   the same span as the user wrote it
     * @param array<int|string, string> $groups     non-empty capture groups of the pattern
     * @param ?int                      $year       the year the text names for this expression, if any
     */
    public function __construct(
        public string $expression,
        public string $original,
        public array $groups,
        public int $offset,
        public ?int $year = null,
    ) {
    }

    public function withYear(?int $year): self
    {
        return new self($this->expression, $this->original, $this->groups, $this->offset, $year);
    }

    public function end(): int
    {
        return $this->offset + strlen($this->expression);
    }
}
