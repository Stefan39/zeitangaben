<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben;

final readonly class Context
{
    public ?\DateTimeImmutable $referenceDate;

    public function __construct(?\DateTimeImmutable $referenceDate = null, public ?State $state = null)
    {
        $this->referenceDate = null === $referenceDate
            ? null
            : new \DateTimeImmutable($referenceDate->format('Y-m-d'), new \DateTimeZone('UTC'));
    }

    public function withState(State $state): self
    {
        return new self($this->referenceDate, $state);
    }
}
