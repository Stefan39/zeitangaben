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
use Stejac\Zeitangaben\Text\Normalizer;
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
        yield 'end of a clause' => ['im Juli 28, danach nicht', '2028-07-01', 'im Juli 28'];
        yield 'capital noun after a named date keeps the year' => ['am 12. Juni 2028 Anreise', '2028-06-12', '12. Juni 2028'];
        yield 'capital noun takes the number' => ['Silvester 2028 Raketen', '2026-12-31', 'Silvester'];
        yield 'quantity before' => ['2028 Personen im Mai', '2027-05-01', 'im Mai'];
        yield 'currency sign' => ['im Oktober 2000€', '2026-10-01', 'im Oktober'];
        yield 'percent sign' => ['im Oktober 2030 %', '2026-10-01', 'im Oktober'];
        yield 'two digits before a word' => ['im Juli 28 Grad', '2027-07-01', 'im Juli'];
        yield 'clock time' => ['Silvester 18:00', '2026-12-31', 'Silvester'];
        yield 'two digits never after a week' => ['KW 42 28', '2027-10-18', 'KW 42'];
        yield 'outside the window' => ['im Juli 1999', '2027-07-01', 'im Juli'];
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

    public function testMaskKeepsOffsetsAndOnlyBlanksQuantities(): void
    {
        $source = Normalizer::withOffsets('Silvester 2000 Euro, Neujahr 2028');

        self::assertSame('silvester      euro, neujahr 2028', YearToken::mask($source, $source->text));
    }

    public function testOfWithoutYearGroupsIsNull(): void
    {
        self::assertNull(YearToken::of([], new \DateTimeImmutable('2026-10-30')));
    }
}
