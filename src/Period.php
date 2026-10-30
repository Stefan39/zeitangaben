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
            throw new InvalidPeriodException('The end of a period must not be before its start.');
        }
    }

    public static function of(string $start, string $end): self
    {
        return new self(self::strictDate($start), self::strictDate($end));
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

    private static function strictDate(string $text): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $text, new \DateTimeZone('UTC'));
        if (false === $date || $date->format('Y-m-d') !== $text) {
            throw new InvalidPeriodException(sprintf('"%s" is not a date in the form YYYY-MM-DD.', $text));
        }

        return $date;
    }

    /**
     * Keeps the wall-clock date of the given instant and drops time and zone.
     */
    private static function toMidnightUtc(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date->format('Y-m-d'), new \DateTimeZone('UTC'));
    }
}
