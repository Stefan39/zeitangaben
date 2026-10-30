<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests\Calendar;

use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Calendar\NextOccurrence;
use Stejac\Zeitangaben\Period;

final class NextOccurrenceTest extends TestCase
{
    public function testWithoutReferenceThereIsNoOccurrence(): void
    {
        self::assertNull(NextOccurrence::of(null, static fn (int $y): Period => Period::of("$y-01-01", "$y-01-01")));
    }

    public function testPeriodEndingOnTheReferenceDayStillCounts(): void
    {
        $period = NextOccurrence::of(
            new \DateTimeImmutable('2026-10-03', new \DateTimeZone('UTC')),
            static fn (int $y): Period => Period::of("$y-10-01", "$y-10-03"),
        );

        self::assertSame('2026-10-01', $period?->start->format('Y-m-d'));
    }

    public function testRollsOverToNextYearAndSkipsYearsWithoutOccurrence(): void
    {
        $period = NextOccurrence::of(
            new \DateTimeImmutable('2027-03-01', new \DateTimeZone('UTC')),
            static fn (int $y): ?Period => 0 === $y % 4 ? Period::of("$y-02-29", "$y-02-29") : null,
        );

        self::assertSame('2028-02-29', $period?->start->format('Y-m-d'));
    }

    public function testGivesUpWhenNothingOccursInTheLookahead(): void
    {
        $period = NextOccurrence::of(
            new \DateTimeImmutable('2026-01-01', new \DateTimeZone('UTC')),
            static fn (int $y): ?Period => null,
        );

        self::assertNull($period);
    }
}
