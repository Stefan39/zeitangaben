<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\NoExpression;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Resolver;

final class ResolverTest extends TestCase
{
    public function testReferenceDateUsesItsOwnWallClockDay(): void
    {
        $reference = new \DateTimeImmutable('2026-09-16 23:30:00', new \DateTimeZone('America/New_York'));

        $result = Resolver::create()->resolve('morgen', new Context($reference));

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2026-09-17', $result->period->start->format('Y-m-d'));
        self::assertSame('2026-09-17', $result->period->end->format('Y-m-d'));
    }

    public function testSameInputGivesSameResultRegardlessOfClock(): void
    {
        $context = new Context(new \DateTimeImmutable('2026-09-16', new \DateTimeZone('UTC')));
        $resolver = Resolver::create();

        $first = json_encode($resolver->resolve('im Juli', $context), JSON_THROW_ON_ERROR);
        usleep(1000);
        $second = json_encode($resolver->resolve('im Juli', $context), JSON_THROW_ON_ERROR);

        self::assertSame($first, $second);
    }

    public function testNoReferenceNeverUsesToday(): void
    {
        $result = Resolver::create()->resolve('morgen');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::MissingReferenceDate, $result->reason);
        self::assertSame([], $result->candidates);
        self::assertStringContainsString('morgen', $result->question);
    }

    public function testGreetingIsNotAnExpression(): void
    {
        self::assertInstanceOf(NoExpression::class, Resolver::create()->resolve('Guten Morgen zusammen'));
        self::assertInstanceOf(NoExpression::class, Resolver::create()->resolve('   '));
    }

    public function testTwoDifferentExpressionsNeedClarification(): void
    {
        $context = new Context(new \DateTimeImmutable('2026-09-16', new \DateTimeZone('UTC')));

        $result = Resolver::create()->resolve('am 3. Oktober oder am 5. Oktober', $context);

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::MultipleExpressions, $result->reason);
        self::assertStringContainsString('3. oktober', $result->question);
        self::assertStringContainsString('5. oktober', $result->question);
    }

    public function testRepeatedIdenticalExpressionCountsOnce(): void
    {
        $context = new Context(new \DateTimeImmutable('2026-09-16', new \DateTimeZone('UTC')));

        $result = Resolver::create()->resolve('morgen, ja morgen', $context);

        self::assertInstanceOf(Resolved::class, $result);
    }

    public function testStateInTextOverridesContextState(): void
    {
        $context = new Context(new \DateTimeImmutable('2026-09-16', new \DateTimeZone('UTC')));

        self::assertInstanceOf(Resolved::class, Resolver::create()->resolve('morgen in Bayern', $context));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function edgeCases(): iterable
    {
        yield 'leap day without year skips to leap year' => ['am 29.02.', '2026-09-16', '2028-02-29', '2028-02-29'];
        yield 'range over new year without year' => ['vom 28.12. bis 3.1.', '2026-09-16', '2026-12-28', '2027-01-03'];
        yield 'range with start year only' => ['vom 12.10.2027 bis 19.10.', '2026-09-16', '2027-10-12', '2027-10-19'];
        yield 'ongoing month still counts' => ['im September', '2026-09-16', '2026-09-01', '2026-09-30'];
        yield 'month with year' => ['im Februar 2028', '2026-09-16', '2028-02-01', '2028-02-29'];
        yield 'weekend seen from a sunday' => ['dieses Wochenende', '2026-09-20', '2026-09-18', '2026-09-20'];
        yield 'week 53 in a long year' => ['KW 53 2026', '2026-09-16', '2026-12-28', '2027-01-03'];
        yield 'month name with umlaut' => ['am 5. März 2027', '2026-09-16', '2027-03-05', '2027-03-05'];
        yield 'kw without year rolls over' => ['KW 2', '2026-09-16', '2027-01-11', '2027-01-17'];
    }

    #[DataProvider('edgeCases')]
    public function testEdgeCases(string $text, string $reference, string $start, string $end): void
    {
        $context = new Context(new \DateTimeImmutable($reference, new \DateTimeZone('UTC')));

        $result = Resolver::create()->resolve($text, $context);

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame($start, $result->period->start->format('Y-m-d'));
        self::assertSame($end, $result->period->end->format('Y-m-d'));
    }

    public function testWeek53InShortYearIsInvalid(): void
    {
        $result = Resolver::create()->resolve('KW 53 2027');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::InvalidDate, $result->reason);
    }

    public function testRangeWithoutYearNeedsReference(): void
    {
        $result = Resolver::create()->resolve('vom 12. bis 19. Oktober');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::MissingReferenceDate, $result->reason);
    }

    public function testInvalidDateQuestionNamesTheDate(): void
    {
        $result = Resolver::create()->resolve('am 31.02.2027');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame('„31.02.2027“ ist kein gültiges Datum. Welches Datum meinen Sie?', $result->question);
    }

    public function testFeastWordsWithSuffixAreNotFixedDays(): void
    {
        $context = new Context(new \DateTimeImmutable('2026-09-16', new \DateTimeZone('UTC')));

        self::assertInstanceOf(NoExpression::class, Resolver::create()->resolve('Neujahrsfeier', $context));
        self::assertInstanceOf(NoExpression::class, Resolver::create()->resolve('Einheitsfeier', $context));
    }
}
