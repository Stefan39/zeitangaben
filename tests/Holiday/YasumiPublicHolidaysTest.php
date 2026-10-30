<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests\Holiday;

use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Holiday\YasumiPublicHolidays;
use Stejac\Zeitangaben\State;

final class YasumiPublicHolidaysTest extends TestCase
{
    private function day(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
    }

    /**
     * @param list<\DateTimeImmutable> $days
     *
     * @return list<string>
     */
    private function format(array $days): array
    {
        return array_map(static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d'), $days);
    }

    public function testCorpusChristiOnlyStatutoryInBavaria(): void
    {
        $holidays = new YasumiPublicHolidays();

        self::assertTrue($holidays->isStatutory($this->day('2027-05-27'), State::BY));
        self::assertFalse($holidays->isStatutory($this->day('2027-05-27'), State::NI));
    }

    public function testAllSaintsOnlyStatutoryInBavaria(): void
    {
        $holidays = new YasumiPublicHolidays();

        self::assertTrue($holidays->isStatutory($this->day('2026-11-01'), State::BY));
        self::assertFalse($holidays->isStatutory($this->day('2026-11-01'), State::NI));
    }

    public function testNationwideMonthHasOnlyNationalHolidays(): void
    {
        self::assertSame(['2026-10-03'], $this->format((new YasumiPublicHolidays())->inMonth(2026, 10, null)));
    }

    public function testReformationDayIsStatutoryInLowerSaxony(): void
    {
        $days = $this->format((new YasumiPublicHolidays())->inMonth(2026, 10, State::NI));

        self::assertContains('2026-10-03', $days);
        self::assertContains('2026-10-31', $days);
    }

    public function testOrdinaryDayIsNotStatutory(): void
    {
        self::assertFalse((new YasumiPublicHolidays())->isStatutory($this->day('2026-10-14'), State::NI));
    }

    public function testMonthResultIsSortedAscending(): void
    {
        $days = $this->format((new YasumiPublicHolidays())->inMonth(2027, 5, State::BY));

        $sorted = $days;
        sort($sorted);
        self::assertSame($sorted, $days);
        self::assertContains('2027-05-27', $days);
    }
}
