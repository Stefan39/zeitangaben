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
use Stejac\Zeitangaben\State;

final class VagueRulesTest extends TestCase
{
    private const string REFERENCE = '2026-09-16';

    private function clarify(string $text, ?string $reference = self::REFERENCE, ?State $state = null): Clarification
    {
        $date = null === $reference ? null : new \DateTimeImmutable($reference, new \DateTimeZone('UTC'));
        $result = Resolver::create()->resolve($text, new Context($date, $state));
        self::assertInstanceOf(Clarification::class, $result);

        return $result;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function needingAReferenceDate(): iterable
    {
        yield 'month part' => ['Anfang Juli'];
        yield 'spring' => ['im Frühjahr'];
        yield 'next weekend' => ['nächstes Wochenende'];
        yield 'long weekend in month' => ['am langen Wochenende im Oktober'];
        yield 'christmas' => ['über Weihnachten'];
        yield 'relative week' => ['in zwei Wochen'];
    }

    #[DataProvider('needingAReferenceDate')]
    public function testAsksForTheReferenceDateWhenNoneIsGiven(string $text): void
    {
        self::assertSame(ClarificationReason::MissingReferenceDate, $this->clarify($text, null)->reason);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function independentOfTheReferenceDate(): iterable
    {
        yield 'season term' => ['in der Nebensaison'];
        yield 'last holiday week' => ['die letzte Ferienwoche'];
        yield 'weekday' => ['am Sonnabend'];
        yield 'weekend' => ['am Wochenende'];
        yield 'long weekend' => ['ein verlängertes Wochenende'];
        yield 'relative to feast' => ['nach Pfingsten'];
    }

    #[DataProvider('independentOfTheReferenceDate')]
    public function testNeedsNoReferenceDate(string $text): void
    {
        self::assertNotSame(ClarificationReason::MissingReferenceDate, $this->clarify($text, null)->reason);
    }

    public function testNotACalendarTermQuestionNamesTheTerm(): void
    {
        $result = $this->clarify('Nebensaison');

        self::assertSame('„Nebensaison“ ist kein Kalenderbegriff. Welchen Zeitraum meinen Sie?', $result->question);
    }

    public function testWeekBeforeChristmasIsNotReadAsRelativeToTheFeast(): void
    {
        self::assertSame(ClarificationReason::Ambiguous, $this->clarify('die Woche vor Weihnachten')->reason);
        self::assertSame(ClarificationReason::Vague, $this->clarify('vor Weihnachten')->reason);
        self::assertSame(ClarificationReason::Vague, $this->clarify('nach Weihnachten')->reason);
    }

    public function testChristmasHolidaysAreLeftToTheSchoolHolidayRule(): void
    {
        $result = Resolver::create()->resolve('in den Weihnachtsferien', new Context(new \DateTimeImmutable(self::REFERENCE), State::NI));

        self::assertInstanceOf(Resolved::class, $result);
    }

    public function testSpringHolidaysAreLeftToTheSchoolHolidayRule(): void
    {
        $result = Resolver::create()->resolve('Frühjahrsferien', new Context(new \DateTimeImmutable(self::REFERENCE), State::HH));

        self::assertNotInstanceOf(NoExpression::class, $result);
        self::assertFalse($result instanceof Clarification && ClarificationReason::Vague === $result->reason);
    }

    public function testLongWeekendListsAWeekendFormedByTwoHolidaysOnce(): void
    {
        $result = $this->clarify('am langen Wochenende im Dezember', '2026-09-16');

        self::assertSame(ClarificationReason::Vague, $result->reason);
        self::assertCount(1, $result->candidates);
    }

    public function testLongWeekendSkipsAWednesdayHoliday(): void
    {
        $result = $this->clarify('langes Wochenende im Oktober', '2029-09-16');

        self::assertSame([], $result->candidates);
    }

    public function testRelativeWeekAcceptsDigits(): void
    {
        $result = $this->clarify('in 3 Wochen');

        self::assertSame('2026-10-05', $result->candidates[0]->period->start->format('Y-m-d'));
    }

    public function testMonthPartPicksNextYearOnceTheStretchIsOver(): void
    {
        $result = $this->clarify('Anfang Juli', '2027-07-10');
        self::assertSame('2027-07-01', $result->candidates[0]->period->start->format('Y-m-d'));

        $result = $this->clarify('Anfang Juli', '2027-07-11');
        self::assertSame('2028-07-01', $result->candidates[0]->period->start->format('Y-m-d'));
    }

    public function testEndOfFebruaryFollowsTheLeapYear(): void
    {
        $result = $this->clarify('Ende Februar', '2028-01-01');

        self::assertSame('2028-02-29', $result->candidates[0]->period->end->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function lookalikes(): iterable
    {
        yield 'samstagsdienst' => ['Samstagsdienst'];
        yield 'morgenstern' => ['Morgenstern'];
        yield 'ende gut' => ['Ende gut'];
        yield 'rosenmontag' => ['Rosenmontag'];
        yield 'maiglöckchen' => ['Maiglöckchen im Garten'];
    }

    #[DataProvider('lookalikes')]
    public function testWordsContainingATermDoNotFire(string $text): void
    {
        self::assertInstanceOf(NoExpression::class, Resolver::create()->resolve($text, new Context()));
    }

    public function testExplicitDateWinsOverTheWeekday(): void
    {
        $result = Resolver::create()->resolve('am Samstag, 19.9.2026', new Context());

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2026-09-19', $result->period->start->format('Y-m-d'));
    }
}
