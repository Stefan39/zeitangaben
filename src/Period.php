<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben;

final readonly class Period
{
    public \DateTimeImmutable $start;
    public \DateTimeImmutable $end;

    public function __construct(\DateTimeImmutable $start, \DateTimeImmutable $end)
    {
        $this->start = self::toMidnightUtc($start);
        $this->end = self::toMidnightUtc($end);
        if ($this->end < $this->start) {
            throw new \InvalidArgumentException('The end of a period must not be before its start.');
        }
    }

    public static function of(string $start, string $end): self
    {
        return new self(
            new \DateTimeImmutable($start, new \DateTimeZone('UTC')),
            new \DateTimeImmutable($end, new \DateTimeZone('UTC')),
        );
    }

    public static function day(\DateTimeImmutable $day): self
    {
        return new self($day, $day);
    }

    /**
     * @return array{start: string, end: string}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start->format('Y-m-d'),
            'end' => $this->end->format('Y-m-d'),
        ];
    }

    /**
     * Keeps the wall-clock date of the given instant and drops time and zone.
     */
    private static function toMidnightUtc(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date->format('Y-m-d'), new \DateTimeZone('UTC'));
    }
}
