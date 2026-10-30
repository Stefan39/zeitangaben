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

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2027-03-01', $result->period->start->format('Y-m-d'));
        self::assertSame('2027-03-12', $result->period->end->format('Y-m-d'));
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function yearlyExpressions(): iterable
    {
        yield 'christmas' => ['Weihnachten 2028'];
        yield 'over christmas' => ['über Weihnachten 2028'];
        yield 'week before christmas' => ['die Woche vor Weihnachten 2028'];
        yield 'between the years' => ['zwischen den Jahren 2028'];
        yield 'month part' => ['Ende Februar 2028'];
        yield 'spring' => ['im Frühjahr 2028'];
        yield 'summer' => ['im Sommer 2028'];
        yield 'autumn' => ['im Herbst 2028'];
        yield 'winter' => ['im Winter 2028'];
        yield 'long weekend' => ['am langen Wochenende im Oktober 2028'];
    }

    #[DataProvider('yearlyExpressions')]
    public function testAnExplicitYearIsHonouredWithoutReferenceDate(string $text): void
    {
        foreach ([null, '2026-09-16', '2031-01-01'] as $reference) {
            $result = $this->clarify($text, $reference, State::NI);

            self::assertNotSame(ClarificationReason::MissingReferenceDate, $result->reason);
            self::assertNotSame([], $result->candidates);
            foreach ($result->candidates as $candidate) {
                self::assertSame('2028', $candidate->period->start->format('Y'), $text);
            }
        }
    }

    public function testMonthWithYearKeepsTheYear(): void
    {
        $result = Resolver::create()->resolve('im Juli 2028', new Context(new \DateTimeImmutable('2031-01-01')));

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2028-07-01', $result->period->start->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function singleDays(): iterable
    {
        yield 'gruendonnerstag' => ['Gründonnerstag 2028', '2028-04-13'];
        yield 'karfreitag' => ['Karfreitag 2028', '2028-04-14'];
        yield 'ostersonntag' => ['Ostersonntag 2028', '2028-04-16'];
        yield 'ostermontag' => ['Ostermontag 2028', '2028-04-17'];
        yield 'pfingstsonntag' => ['Pfingstsonntag 2028', '2028-06-04'];
        yield 'pfingstmontag' => ['Pfingstmontag 2028', '2028-06-05'];
        yield 'heiligabend' => ['Heiligabend 2028', '2028-12-24'];
        yield 'erster weihnachtstag' => ['erster Weihnachtstag 2028', '2028-12-25'];
        yield 'ersten weihnachtstag' => ['am ersten Weihnachtstag 2028', '2028-12-25'];
        yield 'zweiter weihnachtstag' => ['2. Weihnachtstag 2028', '2028-12-26'];
    }

    #[DataProvider('singleDays')]
    public function testSingleFeastDaysResolve(string $text, string $day): void
    {
        $result = Resolver::create()->resolve($text, new Context());

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame($day, $result->period->start->format('Y-m-d'));
        self::assertSame($day, $result->period->end->format('Y-m-d'));
    }

    public function testMaundyThursdayDoesNotReachTheWeekdayRule(): void
    {
        $result = Resolver::create()->resolve('am Gründonnerstag', new Context(new \DateTimeImmutable(self::REFERENCE)));

        self::assertInstanceOf(Resolved::class, $result);
    }

    public function testSundaysOfEasterAndPentecostCarryNoHolidayNote(): void
    {
        $result = Resolver::create()->resolve('Ostersonntag 2028', new Context(null, State::NI));
        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame([], $result->notes);

        $result = Resolver::create()->resolve('Gründonnerstag 2028', new Context(null, State::NI));
        self::assertInstanceOf(Resolved::class, $result);
        self::assertNotSame([], $result->notes);
    }

    public function testWinterRunsIntoTheLeapDayOfTheNextYear(): void
    {
        $result = $this->clarify('im Winter 2027', null);

        self::assertSame('2028-02-29', $result->candidates[0]->period->end->format('Y-m-d'));
    }

    public function testSeasonWordsInsideOtherWordsDoNotFire(): void
    {
        foreach (['Sommerreifen', 'Winterdienst', 'Herbstlaub'] as $text) {
            self::assertInstanceOf(NoExpression::class, Resolver::create()->resolve($text, new Context(new \DateTimeImmutable(self::REFERENCE))), $text);
        }
    }

    public function testRelativeWeekNames(): void
    {
        $context = new Context(new \DateTimeImmutable(self::REFERENCE));
        foreach (['diese Woche' => '2026-09-14', 'kommende Woche' => '2026-09-21', 'übernächste Woche' => '2026-09-28'] as $text => $monday) {
            $result = Resolver::create()->resolve($text, $context);
            self::assertInstanceOf(Resolved::class, $result);
            self::assertSame($monday, $result->period->start->format('Y-m-d'));
        }
        self::assertSame(ClarificationReason::MissingReferenceDate, $this->clarify('diese Woche', null)->reason);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function yearCrossingSpans(): iterable
    {
        yield 'winter running in january' => ['im Winter', '2027-01-15', '2026-12-01', '2027-02-28'];
        yield 'winter after it ended' => ['im Winter', '2027-03-01', '2027-12-01', '2028-02-29'];
        yield 'winter on its last day' => ['im Winter', '2027-02-28', '2026-12-01', '2027-02-28'];
    }

    #[DataProvider('yearCrossingSpans')]
    public function testSpansCrossingNewYearAreKeptWhileRunning(string $text, string $reference, string $start, string $end): void
    {
        $period = $this->clarify($text, $reference)->candidates[0]->period;

        self::assertSame($start, $period->start->format('Y-m-d'));
        self::assertSame($end, $period->end->format('Y-m-d'));
    }

    public function testBetweenTheYearsOnNewYearsDayStillCountsTheRunningSpan(): void
    {
        $candidates = $this->clarify('zwischen den Jahren', '2027-01-01')->candidates;

        self::assertSame('2026-12-27', $candidates[0]->period->start->format('Y-m-d'));
        self::assertSame('2026-12-25', $candidates[1]->period->start->format('Y-m-d'));
        self::assertSame('2027-01-01', $candidates[1]->period->end->format('Y-m-d'));
        self::assertSame('2027-12-25', $this->clarify('zwischen den Jahren', '2027-01-02')->candidates[1]->period->start->format('Y-m-d'));
    }

    public function testSingleDaysAcrossNewYearAreNotPulledBack(): void
    {
        $silvester = Resolver::create()->resolve('Silvester', new Context(new \DateTimeImmutable('2027-01-01')));
        $neujahr = Resolver::create()->resolve('Neujahr', new Context(new \DateTimeImmutable('2026-12-31')));

        self::assertInstanceOf(Resolved::class, $silvester);
        self::assertInstanceOf(Resolved::class, $neujahr);
        self::assertSame('2027-12-31', $silvester->period->start->format('Y-m-d'));
        self::assertSame('2027-01-01', $neujahr->period->start->format('Y-m-d'));
    }

    public function testDoubleSpaceDoesNotDefeatTheLookbehind(): void
    {
        $result = $this->clarify('vor  Weihnachten');

        self::assertSame(ClarificationReason::Vague, $result->reason);
        self::assertSame('vor  Weihnachten', $result->expression);
    }

    public function testPlainHolidaysAskWhichOnes(): void
    {
        foreach (['Ferien', 'in den Ferien'] as $text) {
            $result = $this->clarify($text);
            self::assertSame(ClarificationReason::Ambiguous, $result->reason);
            self::assertSame([], $result->candidates);
            self::assertSame(sprintf('Welche Ferien meinen Sie mit „%s“?', $text), $result->question);
        }
        foreach (['Ferienwohnung', 'Feriengäste'] as $text) {
            self::assertInstanceOf(NoExpression::class, Resolver::create()->resolve($text, new Context()));
        }
    }

    public function testLabourDayTakesAYear(): void
    {
        $result = Resolver::create()->resolve('Tag der Arbeit 2028', new Context());

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2028-05-01', $result->period->start->format('Y-m-d'));
    }
}
