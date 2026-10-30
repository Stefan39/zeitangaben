<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Text;

/**
 * Normalised text that remembers where each byte came from, so a match can be quoted as the user wrote it.
 *
 * @internal
 */
final readonly class NormalizedText
{
    /**
     * @param list<int> $starts original byte offset where the source of each normalised byte begins
     * @param list<int> $ends   original byte offset where it ends
     */
    public function __construct(
        public string $original,
        public string $text,
        private array $starts,
        private array $ends,
    ) {
    }

    /**
     * The original substring behind a span of the normalised text.
     */
    public function originalOf(int $offset, int $length): string
    {
        if (0 === $length) {
            return '';
        }
        $start = $this->starts[$offset];

        return substr($this->original, $start, $this->ends[$offset + $length - 1] - $start);
    }
}
