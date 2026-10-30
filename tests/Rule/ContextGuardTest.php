<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\NoExpression;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Resolver;
use Stejac\Zeitangaben\Result;

final class ContextGuardTest extends TestCase
{
    private function resolve(string $text): Result
    {
        return Resolver::create()->resolve($text, new Context(new \DateTimeImmutable('2026-10-30')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function noTimeExpression(): iterable
    {
        yield 'section sign' => ['§ 3.4.'];
        yield 'abbreviated number' => ['Nr. 12.06.'];
        yield 'file name' => ['bericht-heute.pdf'];
        yield 'title' => ['Prof. Morgen'];
        yield 'good morning all' => ['Morgen zusammen!'];
        yield 'happy easter' => ['Frohe Ostern!'];
        yield 'nice weekend' => ['Schönes Wochenende!'];
        yield 'noun morning' => ['diesen Morgen'];
    }

    #[DataProvider('noTimeExpression')]
    public function testDiscardsNamesAndTechnicalTokens(string $text): void
    {
        self::assertInstanceOf(NoExpression::class, $this->resolve($text));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function plainExpressions(): iterable
    {
        yield 'hotel in july' => ['Wir suchen ein Hotel im Juli', '2027-07-01'];
        yield 'family in july' => ['Wir sind eine Familie im Juli', '2027-07-01'];
        yield 'hotel with date' => ['Hotel 12.06.', '2027-06-12'];
        yield 'people counted' => ['Wir sind 4 Personen im Juli', '2027-07-01'];
        yield 'number with capital noun' => ['3 Zimmer im Juli', '2027-07-01'];
        yield 'place after preposition' => ['Wir fahren nach Usedom im Juli', '2027-07-01'];
        yield 'state after preposition, lowercase' => ['wir fahren nach bayern im juli', '2027-07-01'];
        yield 'idiom' => ['vor allem im Juli', '2027-07-01'];
        yield 'noun between' => ['Tag der Anreise ist morgen', '2026-10-31'];
        yield 'other clause' => ['Das geht nicht. Im Juli passt es', '2027-07-01'];
        yield 'comma ends the clause' => ['Ich weiß nicht, im Juli vielleicht', '2027-07-01'];
        yield 'people after comma' => ['im Juli, 3 Personen', '2027-07-01'];
        yield 'people after and' => ['im Juli und 2 Kinder', '2027-07-01'];
        yield 'time after comma' => ['morgen, 14:30', '2026-10-31'];
        yield 'count before comma' => ['Wir sind 4, morgen kommen wir', '2026-10-31'];
        yield 'morning together' => ['Wir kommen morgen zusammen an', '2026-10-31'];
    }

    #[DataProvider('plainExpressions')]
    public function testKeepsOrdinarySentences(string $text, string $start): void
    {
        $result = $this->resolve($text);
        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame($start, $result->period->start->format('Y-m-d'));
    }

    public function testQuotesFromTheOutermostQualifierToTheEndOfTheSpan(): void
    {
        $result = $this->resolve('Wir möchten die erste Woche im Mai kommen');
        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::Vague, $result->reason);
        self::assertSame('erste Woche im Mai', $result->expression);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function qualified(): iterable
    {
        yield 'number with a time noun' => ['zwei Nächte im August', 'zwei Nächte im August'];
        yield 'every year' => ['jedes Jahr im Juli', 'jedes Jahr im Juli'];
        yield 'approximately, abbreviated' => ['ca. 12.06.', 'ca. 12.06.'];
        yield 'around' => ['um den 12. Oktober', 'um den 12. Oktober'];
        yield 'not with a filler' => ['nicht in den Sommerferien', 'nicht in den Sommerferien'];
    }

    #[DataProvider('qualified')]
    public function testQualifierAsks(string $text, string $expression): void
    {
        $result = $this->resolve($text);
        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::Vague, $result->reason);
        self::assertSame($expression, $result->expression);
        self::assertStringContainsString($expression, $result->question);
    }

    public function testDirectNumberBeforeTheSpanIsAQualifier(): void
    {
        $result = $this->resolve('am 3 im Mai');
        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::Vague, $result->reason);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function joined(): iterable
    {
        yield 'month after' => ['im Juli, August', 'im Juli; August'];
        yield 'hyphen month' => ['im Juli-August', 'im Juli; August'];
        yield 'number before' => ['12 bis 19.10.', '12; 19.10.'];
        yield 'day before with filler' => ['am 12. oder am 13. Oktober', '12.; 13. Oktober'];
    }

    #[DataProvider('joined')]
    public function testConnectorToAnUnclaimedTokenAsks(string $text, string $expression): void
    {
        $result = $this->resolve($text);
        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::MultipleExpressions, $result->reason);
        self::assertSame($expression, $result->expression);
    }
}
