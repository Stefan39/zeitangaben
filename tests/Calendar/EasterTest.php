<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests\Calendar;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Calendar\Easter;

final class EasterTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function sundays(): iterable
    {
        yield '2026' => [2026, '2026-04-05'];
        yield '2027' => [2027, '2027-03-28'];
        yield '2028' => [2028, '2028-04-16'];
        yield '2038' => [2038, '2038-04-25'];
    }

    #[DataProvider('sundays')]
    public function testSunday(int $year, string $expected): void
    {
        self::assertSame($expected, Easter::sunday($year)->format('Y-m-d'));
    }

    public function testOffsetsFromEasterSunday(): void
    {
        self::assertSame('2027-03-26', Easter::offset(2027, Easter::GOOD_FRIDAY)->format('Y-m-d'));
        self::assertSame('2027-05-06', Easter::offset(2027, Easter::ASCENSION)->format('Y-m-d'));
        self::assertSame('2027-05-15', Easter::offset(2027, Easter::PENTECOST_SATURDAY)->format('Y-m-d'));
        self::assertSame('2027-05-17', Easter::offset(2027, Easter::WHIT_MONDAY)->format('Y-m-d'));
        self::assertSame('2027-05-27', Easter::offset(2027, Easter::CORPUS_CHRISTI)->format('Y-m-d'));
    }
}
