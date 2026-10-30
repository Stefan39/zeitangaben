<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben;

final readonly class Resolved implements Result
{
    /**
     * @param list<string> $notes
     */
    public function __construct(
        public Period $period,
        public string $expression,
        public array $notes = [],
        public ?State $state = null,
    ) {
    }

    /**
     * @return array{type: string, start: string, end: string, expression: string, notes: list<string>, state: ?string}
     */
    public function jsonSerialize(): array
    {
        return ['type' => 'resolved'] + $this->period->toArray() + [
            'expression' => $this->expression,
            'notes' => $this->notes,
            'state' => $this->state?->value,
        ];
    }
}
