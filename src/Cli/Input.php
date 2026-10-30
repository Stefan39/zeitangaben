<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Cli;

use Stejac\Zeitangaben\State;

final readonly class Input
{
    public function __construct(
        public string $text,
        public ?State $state,
        public ?\DateTimeImmutable $referenceDate,
        public bool $json,
    ) {
    }
}
