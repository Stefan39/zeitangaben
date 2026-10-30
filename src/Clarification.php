<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben;

final readonly class Clarification implements Result
{
    /**
     * @param list<Candidate> $candidates
     */
    public function __construct(
        public ClarificationReason $reason,
        public string $question,
        public string $expression,
        public array $candidates = [],
    ) {
    }

    /**
     * @return array{type: string, reason: string, question: string, expression: string, candidates: list<array{start: string, end: string, label: string}>}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => 'clarification',
            'reason' => $this->reason->value,
            'question' => $this->question,
            'expression' => $this->expression,
            'candidates' => array_map(
                static fn (Candidate $c): array => $c->period->toArray() + ['label' => $c->label],
                $this->candidates,
            ),
        ];
    }
}
