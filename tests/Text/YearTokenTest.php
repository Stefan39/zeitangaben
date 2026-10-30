<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests\Text;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\NoExpression;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Resolver;
use Stejac\Zeitangaben\State;
use Stejac\Zeitangaben\Text\YearToken;

final class YearTokenTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function expressions(): iterable
    {
        yield 'next year before' => ['nächstes Jahr im Juli', '2027-07-01', 'nächstes Jahr im Juli'];
        yield 'next year inflected' => ['im nächsten Jahr im Juli', '2027-07-01', 'im nächsten Jahr im Juli'];
        yield 'this year' => ['Silvester dieses Jahr', '2026-12-31', 'Silvester dieses Jahr'];
        yield 'this year inflected' => ['Silvester in diesem Jahr', '2026-12-31', 'Silvester in diesem Jahr'];
        yield 'last year before' => ['voriges Jahr im Mai', '2025-05-01', 'voriges Jahr im Mai'];
        yield 'date with relative year' => ['3. Oktober nächstes Jahr', '2027-10-03', '3. Oktober nächstes Jahr'];
        yield 'range with relative year' => ['vom 12. bis 19. Oktober nächstes Jahr', '2027-10-12', 'vom 12. bis 19. Oktober nächstes Jahr'];
        yield 'leading year before a feast' => ['2028 Silvester', '2028-12-31', '2028 Silvester'];
        yield 'capital noun after a named date' => ['am 12. Juni 2028 Anreise', '2028-06-12', '12. Juni 2028'];
        yield 'harmless word after a stated year' => ['Silvester 2028 gerne', '2028-12-31', 'Silvester 2028'];
        yield 'next year, genitive' => ['im November nächsten Jahres', '2027-11-01', 'im November nächsten Jahres'];
        yield 'coming year, genitive' => ['im November kommenden Jahres', '2027-11-01', 'im November kommenden Jahres'];
        yield 'last year, genitive' => ['im November letzten Jahres', '2025-11-01', 'im November letzten Jahres'];
        yield 'previous year, genitive' => ['im November vorigen Jahres', '2025-11-01', 'im November vorigen Jahres'];
        yield 'year after next' => ['Silvester übernächstes Jahr', '2028-12-31', 'Silvester übernächstes Jahr'];
        yield 'year after next, genitive' => ['im Juli übernächsten Jahres', '2028-07-01', 'im Juli übernächsten Jahres'];
        yield 'year after next, leading' => ['übernächstes Jahr im Juli', '2028-07-01', 'übernächstes Jahr im Juli'];
        yield 'apostrophe' => ["im November '27", '2027-11-01', "im November '27"];
        yield 'typographic apostrophe' => ['im November ’27', '2027-11-01', 'im November ’27'];
        yield 'apostrophe after a date' => ["12.06.'27", '2027-06-12', "12.06.'27"];
        yield 'apostrophe in a sentence' => ["im Juli '27 kommen wir", '2027-07-01', "im Juli '27"];
        yield 'apostrophe after a week' => ["KW 42 '28", '2028-10-16', "KW 42 '28"];
        yield 'two digits at the end' => ['im Juli 28', '2028-07-01', 'im Juli 28'];
        yield 'greatest two-digit year' => ['im Juli 69', '2069-07-01', 'im Juli 69'];
    }

    #[DataProvider('expressions')]
    public function testYearHandling(string $text, string $start, string $expression): void
    {
        $result = Resolver::create()->resolve($text, new Context(new \DateTimeImmutable('2026-10-30')));

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame($start, $result->period->start->format('Y-m-d'));
        self::assertSame($expression, $result->expression);
    }

    /**
     * A number that is no year stays in the rest of the text, so the library asks about the period without it.
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function noYear(): iterable
    {
        yield 'two digits in front of a comma' => ['im Juli 28, danach nicht', '2027-07-01', 'im Juli'];
        yield 'two digits in front of a dot' => ['im Juli 28.', '2027-07-01', 'im Juli'];
        yield 'two digits in front of a greeting' => ['im Juli 28 frohe Ostern', '2027-07-01', 'im Juli'];
        yield 'two digits before a word' => ['im Juli 28 Grad', '2027-07-01', 'im Juli'];
        yield 'two digits never after a week' => ['KW 42 28', '2027-10-18', 'KW 42'];
        yield 'old two digits' => ['im Juli 85', '2027-07-01', 'im Juli'];
        yield 'old two digits after a named date' => ['12. Juni 85', '2027-06-12', '12. Juni'];
        yield 'old apostrophe' => ["im Juli '85", '2027-07-01', 'im Juli'];
        yield 'outside the window' => ['im Juli 1999', '2027-07-01', 'im Juli'];
        yield 'unit after a number' => ['Silvester 2000 Euro', '2026-12-31', 'Silvester'];
        yield 'counted pieces' => ['Silvester 2030 Stück', '2026-12-31', 'Silvester'];
        yield 'quantity before' => ['2028 Personen im Mai', '2027-05-01', 'im Mai'];
        yield 'currency sign' => ['im Oktober 2000€', '2026-10-01', 'im Oktober'];
        yield 'percent sign' => ['im Oktober 2030 %', '2026-10-01', 'im Oktober'];
        yield 'clock time' => ['Silvester 18:00', '2026-12-31', 'Silvester'];
    }

    #[DataProvider('noYear')]
    public function testANumberThatIsNoYearAsks(string $text, string $start, string $expression): void
    {
        $result = Resolver::create()->resolve($text, new Context(new \DateTimeImmutable('2026-10-30')));

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::UnclearContext, $result->reason);
        self::assertSame($expression, $result->expression);
        self::assertSame($start, $result->candidates[0]->period->start->format('Y-m-d'));
    }

    public function testStatedYearStaysInTheCandidateInFrontOfAnUnknownWord(): void
    {
        $result = Resolver::create()->resolve('Silvester 2028 Party', new Context(new \DateTimeImmutable('2026-10-30')));

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::UnclearContext, $result->reason);
        self::assertSame('Silvester 2028', $result->expression);
        self::assertSame('2028-12-31', $result->candidates[0]->period->start->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function oldShortYear(): iterable
    {
        yield 'single date' => ['12.06.85'];
        yield 'first old year' => ['12.06.70'];
        yield 'start of a range' => ['vom 12.06.85 bis 13.06.'];
        yield 'end of a range' => ['vom 12.06. bis 13.06.85'];
    }

    #[DataProvider('oldShortYear')]
    public function testOldTwoDigitYearInADateIsInvalid(string $text): void
    {
        $result = Resolver::create()->resolve($text, new Context(new \DateTimeImmutable('2026-10-30')));

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::InvalidDate, $result->reason);
    }

    public function testGreatestTwoDigitYearInADate(): void
    {
        $result = Resolver::create()->resolve('12.06.69');

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2069-06-12', $result->period->start->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notADate(): iterable
    {
        yield 'year outside the window' => ['12.06.1999'];
        yield 'year beyond the window' => ['12.06.3000'];
        yield 'iso outside the window' => ['1999-01-01'];
        yield 'four parts' => ['10.12.34.5'];
    }

    #[DataProvider('notADate')]
    public function testDottedNumbersAreNoDates(string $text): void
    {
        self::assertInstanceOf(NoExpression::class, Resolver::create()->resolve($text, new Context(new \DateTimeImmutable('2026-10-30'))));
    }

    public function testRelativeYearBeforeTheExpressionNeedsAReferenceDate(): void
    {
        $result = Resolver::create()->resolve('nächstes Jahr im Juli');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::MissingReferenceDate, $result->reason);
    }

    public function testStatedYearOfASchoolHolidayStaysInFrontOfAnOrdinaryWord(): void
    {
        $result = Resolver::create()->resolve('Sommerferien 2028 Anreise', new Context(new \DateTimeImmutable('2026-10-30'), State::NI));

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2028-07-20', $result->period->start->format('Y-m-d'));
        self::assertSame('2028-08-30', $result->period->end->format('Y-m-d'));
    }

    public function testMaskKeepsOffsetsAndOnlyBlanksQuantities(): void
    {
        self::assertSame('silvester      euro, neujahr 2028', YearToken::mask('silvester 2000 euro, neujahr 2028'));
    }

    public function testOfWithoutYearGroupsIsNull(): void
    {
        self::assertNull(YearToken::of([], new \DateTimeImmutable('2026-10-30')));
    }
}
