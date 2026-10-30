<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests\Calendar;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Calendar\LongWeekend;

final class LongWeekendTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?array{string, string}}>
     */
    public static function holidays(): iterable
    {
        yield 'monday' => ['2026-05-25', ['2026-05-23', '2026-05-25']];
        yield 'tuesday' => ['2026-12-01', ['2026-11-28', '2026-12-01']];
        yield 'wednesday' => ['2026-12-02', null];
        yield 'thursday' => ['2026-05-14', ['2026-05-14', '2026-05-17']];
        yield 'friday' => ['2026-05-01', ['2026-05-01', '2026-05-03']];
        yield 'saturday' => ['2026-10-03', ['2026-10-02', '2026-10-04']];
        yield 'saturday may day' => ['2027-05-01', ['2027-04-30', '2027-05-02']];
        yield 'sunday' => ['2026-11-01', ['2026-10-30', '2026-11-01']];
    }

    /**
     * @param ?array{string, string} $expected
     */
    #[DataProvider('holidays')]
    public function testAround(string $holiday, ?array $expected): void
    {
        $period = LongWeekend::around(new \DateTimeImmutable($holiday, new \DateTimeZone('UTC')));

        if (null === $expected) {
            self::assertNull($period);

            return;
        }
        self::assertNotNull($period);
        self::assertSame($expected, [$period->start->format('Y-m-d'), $period->end->format('Y-m-d')]);
    }
}
