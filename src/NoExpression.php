<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben;

final readonly class NoExpression implements Result
{
    /**
     * @return array{type: string}
     */
    public function jsonSerialize(): array
    {
        return ['type' => 'none'];
    }
}
