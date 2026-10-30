<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Resolver;
use Stejac\Zeitangaben\Result;

final class ExplicitDateRangeTest extends TestCase
{
    private function resolve(string $text): Result
    {
        return Resolver::create()->resolve($text, new Context(new \DateTimeImmutable('2026-10-30')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function matchingAbbreviations(): iterable
    {
        yield 'with comma' => ['Sa, 12.06.2027', '2027-06-12'];
        yield 'with dot' => ['Sa. 12.06.2027', '2027-06-12'];
        yield 'bare' => ['sa 12.06.2027', '2027-06-12'];
        yield 'with article' => ['Fr., den 11.06.2027', '2027-06-11'];
        yield 'monday' => ['Mo 14.06.2027', '2027-06-14'];
        yield 'tuesday' => ['Di 15.06.2027', '2027-06-15'];
        yield 'wednesday' => ['Mi 16.06.2027', '2027-06-16'];
        yield 'thursday' => ['Do 17.06.2027', '2027-06-17'];
        yield 'sunday' => ['So 20.06.2027', '2027-06-20'];
        yield 'range' => ['Fr 11.06.2027 bis So 13.06.2027', '2027-06-11'];
    }

    #[DataProvider('matchingAbbreviations')]
    public function testAbbreviatedWeekdayIsCheckedAndConsumed(string $text, string $start): void
    {
        $result = $this->resolve($text);

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame($start, $result->period->start->format('Y-m-d'));
    }

    public function testAbbreviatedWeekdayThatContradictsTheDateIsSpelledOutInTheQuestion(): void
    {
        $result = $this->resolve('Mo. 12.06.2027');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::InvalidDate, $result->reason);
        self::assertSame('Der 12.06.2027 ist ein Samstag, kein Montag. Welches Datum meinen Sie?', $result->question);
    }

    public function testAbbreviationAtTheEndOfARangeIsChecked(): void
    {
        $result = $this->resolve('Fr 11.06.2027 bis Mo 13.06.2027');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::InvalidDate, $result->reason);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function ranges(): iterable
    {
        yield 'until and including' => ['vom 3. bis einschließlich 5. November', '2026-11-03', '2026-11-05'];
        yield 'dash with spaces' => ['12.10. - 19.10.2027', '2027-10-12', '2027-10-19'];
        yield 'en dash' => ['12.–19.10.', '2027-10-12', '2027-10-19'];
        yield 'from without vom' => ['ab 12.06. bis 19.06.', '2027-06-12', '2027-06-19'];
        yield 'between without article' => ['zwischen 12.10. und 19.10.', '2027-10-12', '2027-10-19'];
        yield 'years on both sides' => ['vom 12.10.2026 bis 19.10.2027', '2026-10-12', '2027-10-19'];
        yield 'crosses from january to february' => ['vom 30.1. bis 2.2.', '2027-01-30', '2027-02-02'];
        yield 'leap day picks a leap year' => ['vom 29.2. bis 3.3.', '2028-02-29', '2028-03-03'];
        yield 'matching weekdays' => ['von Montag, 12.10.2026 bis Freitag, 16.10.2026', '2026-10-12', '2026-10-16'];
    }

    #[DataProvider('ranges')]
    public function testRanges(string $text, string $start, string $end): void
    {
        $result = $this->resolve($text);

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame($start, $result->period->start->format('Y-m-d'));
        self::assertSame($end, $result->period->end->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'crosses but not from the end of the year' => ['vom 28.10. bis 3.1.'];
        yield 'crosses but not into the start of the year' => ['vom 15.11. bis 2.3.'];
        yield 'year on the end only, wrong order' => ['vom 5.1. bis 3.1.2027'];
        yield 'year on the start only, wrong order' => ['vom 5.1.2027 bis 3.1.'];
        yield 'both years, wrong order' => ['vom 3.1.2028 bis 5.1.2027'];
        yield 'day does not exist in the end year' => ['vom 28.12.2026 bis 29.2.'];
        yield 'day that never exists' => ['vom 31.2. bis 3.3.'];
        yield 'iso day that does not exist' => ['2027-02-30'];
    }

    #[DataProvider('invalid')]
    public function testInvalidRanges(string $text): void
    {
        $result = $this->resolve($text);

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::InvalidDate, $result->reason);
    }

    public function testWeekdayMismatchNamesBothWeekdays(): void
    {
        $result = $this->resolve('Montag, 12.06.2027');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame('Der 12.06.2027 ist ein Samstag, kein Montag. Welches Datum meinen Sie?', $result->question);
    }

    public function testWeekdayMismatchAtTheEndOfARange(): void
    {
        $result = $this->resolve('von Montag, 12.10.2026 bis Montag, 16.10.2026');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame('Der 16.10.2026 ist ein Freitag, kein Montag. Welches Datum meinen Sie?', $result->question);
    }

    public function testRangeWithoutYearNeedsAReferenceDate(): void
    {
        $result = Resolver::create()->resolve('vom 12. bis 19. Oktober');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::MissingReferenceDate, $result->reason);
    }
}
