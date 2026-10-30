<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben;

final readonly class Candidate
{
    public function __construct(public Period $period, public string $label)
    {
    }
}
