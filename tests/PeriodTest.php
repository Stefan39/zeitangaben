<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests;

use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\NoExpression;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\State;

final class PeriodTest extends TestCase
{
    public function testEndBeforeStartThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Period::of('2026-10-02', '2026-10-01');
    }

    public function testTimeAndTimezoneAreDropped(): void
    {
        $period = new Period(
            new \DateTimeImmutable('2026-10-01 15:30:00', new \DateTimeZone('Europe/Berlin')),
            new \DateTimeImmutable('2026-10-03 23:59:59', new \DateTimeZone('America/New_York')),
        );

        self::assertSame('2026-10-01T00:00:00+00:00', $period->start->format('c'));
        self::assertSame('2026-10-03T00:00:00+00:00', $period->end->format('c'));
    }

    public function testToArrayFormat(): void
    {
        self::assertSame(['start' => '2026-10-12', 'end' => '2026-10-24'], Period::of('2026-10-12', '2026-10-24')->toArray());
    }

    public function testDayIsSingleDayPeriod(): void
    {
        $period = Period::day(new \DateTimeImmutable('2026-12-24 18:00'));

        self::assertSame(['start' => '2026-12-24', 'end' => '2026-12-24'], $period->toArray());
    }

    public function testContextReducesReferenceDateToMidnightUtc(): void
    {
        $context = new Context(new \DateTimeImmutable('2026-09-16 23:30:00', new \DateTimeZone('Europe/Berlin')));

        self::assertNotNull($context->referenceDate);
        self::assertSame('2026-09-16T00:00:00+00:00', $context->referenceDate->format('c'));
    }

    public function testContextWithStateKeepsReferenceDate(): void
    {
        $context = (new Context(new \DateTimeImmutable('2026-09-16')))->withState(State::NI);

        self::assertSame(State::NI, $context->state);
        self::assertSame('2026-09-16', $context->referenceDate?->format('Y-m-d'));
    }

    public function testContextWithoutReferenceDate(): void
    {
        self::assertNull((new Context())->referenceDate);
    }

    public function testResolvedJsonShape(): void
    {
        $resolved = new Resolved(Period::of('2026-10-12', '2026-10-24'), 'Herbstferien', ['Note']);

        self::assertSame(
            ['type' => 'resolved', 'start' => '2026-10-12', 'end' => '2026-10-24', 'expression' => 'Herbstferien', 'notes' => ['Note']],
            $resolved->jsonSerialize(),
        );
    }

    public function testClarificationJsonShape(): void
    {
        $clarification = new Clarification(
            ClarificationReason::Ambiguous,
            'Which one?',
            'über Ostern',
            [new Candidate(Period::of('2027-03-26', '2027-03-29'), 'Feiertage')],
        );

        self::assertSame(
            [
                'type' => 'clarification',
                'reason' => 'ambiguous',
                'question' => 'Which one?',
                'expression' => 'über Ostern',
                'candidates' => [['start' => '2027-03-26', 'end' => '2027-03-29', 'label' => 'Feiertage']],
            ],
            $clarification->jsonSerialize(),
        );
    }

    public function testNoExpressionJsonShape(): void
    {
        self::assertSame('{"type":"none"}', json_encode(new NoExpression(), JSON_THROW_ON_ERROR));
    }
}
