<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\NoExpression;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Resolver;
use Stejac\Zeitangaben\State;

final class FeastRulesTest extends TestCase
{
    private function context(?State $state = null): Context
    {
        return new Context(new \DateTimeImmutable('2026-09-16', new \DateTimeZone('UTC')), $state);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function lookalikes(): iterable
    {
        yield 'ostermann' => ['Herr Ostermann'];
        yield 'ostereier' => ['Ostereier suchen'];
        yield 'pfingstrose' => ['Pfingstrosen'];
    }

    #[DataProvider('lookalikes')]
    public function testWordsContainingAFeastNameDoNotFire(string $text): void
    {
        self::assertInstanceOf(NoExpression::class, Resolver::create()->resolve($text, $this->context()));
    }

    public function testOsterferienDoNotTriggerTheEasterRule(): void
    {
        $result = Resolver::create()->resolve('in den Osterferien', $this->context(State::NI));

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2027-03-22', $result->period->start->format('Y-m-d'));
    }

    public function testChristiHimmelfahrtIsTheSameAsHimmelfahrt(): void
    {
        $result = Resolver::create()->resolve('an Christi Himmelfahrt', $this->context());

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2027-05-06', $result->period->start->format('Y-m-d'));
    }

    public function testFeastWithYearUsesThatYear(): void
    {
        $result = Resolver::create()->resolve('Himmelfahrt 2028', new Context());

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2028-05-25', $result->period->start->format('Y-m-d'));
    }

    public function testEasterWithYearNeedsNoReferenceDate(): void
    {
        $result = Resolver::create()->resolve('Ostern 2028', new Context());

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame([['2028-04-14', '2028-04-17']], $this->ranges($result));
    }

    public function testEasterHolidaysOutsideTheSnapshotLeaveOnlyTheFeast(): void
    {
        $result = Resolver::create()->resolve('Ostern 2040', new Context(null, State::NI));

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::Ambiguous, $result->reason);
        self::assertCount(1, $result->candidates);
    }

    public function testBridgeDayInAYearWithMayDayOnAWednesdayHasOneCandidate(): void
    {
        $result = Resolver::create()->resolve('Brückentag im Mai 2030', new Context());

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame([['2030-05-31', '2030-05-31']], $this->ranges($result));
    }

    public function testBridgeDayWithoutReferenceDateAsksForIt(): void
    {
        $result = Resolver::create()->resolve('Brückentag im Mai', new Context());

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::MissingReferenceDate, $result->reason);
    }

    public function testMarianAscensionBeatsPlainAscension(): void
    {
        $result = Resolver::create()->resolve('Mariä Himmelfahrt', $this->context());

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2027-08-15', $result->period->start->format('Y-m-d'));
    }

    public function testNoteNamesTheStateAndStatesTheFactOnce(): void
    {
        $result = Resolver::create()->resolve('an Allerheiligen', $this->context(State::NI));

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame(['In Niedersachsen kein gesetzlicher Feiertag.'], $result->notes);
    }

    public function testWinterferienIsTheUmbrellaTermForAnyWinterEntry(): void
    {
        $resolver = Resolver::create();
        $winter = $resolver->resolve('Winterferien', $this->context(State::BY));
        $named = $resolver->resolve('Frühjahrsferien', $this->context(State::BY));

        self::assertInstanceOf(Resolved::class, $winter);
        self::assertInstanceOf(Resolved::class, $named);
        self::assertEquals($winter->period, $named->period);
        self::assertSame(State::BY, $winter->state);
    }

    public function testASpecificBreakUnderAnotherNameIsOfferedWithItsRealName(): void
    {
        $result = Resolver::create()->resolve('Faschingsferien', $this->context(State::BY));

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::Ambiguous, $result->reason);
        self::assertSame('Frühjahrsferien', $result->candidates[0]->label);
        self::assertStringContainsString('In Bayern', $result->question);
    }

    public function testFaschingAndFastnachtNameTheSameBreak(): void
    {
        $resolver = Resolver::create();
        $fasching = $resolver->resolve('Faschingsferien', $this->context(State::SL));
        $fastnacht = $resolver->resolve('Fastnachtsferien', $this->context(State::SL));

        self::assertInstanceOf(Resolved::class, $fasching);
        self::assertInstanceOf(Resolved::class, $fastnacht);
        self::assertEquals($fastnacht->period, $fasching->period);
    }

    public function testASpecificBreakIsNotInAStateWithoutWinterHolidays(): void
    {
        $result = Resolver::create()->resolve('Halbjahresferien', $this->context(State::BW));

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::NotInState, $result->reason);
    }

    public function testGapInTheDataIsOutOfDataWithoutAYearAndWithOne(): void
    {
        $context = new Context(new \DateTimeImmutable('2027-06-01'), State::NW);
        $withoutYear = Resolver::create()->resolve('Pfingstferien', $context);
        $withYear = Resolver::create()->resolve('Pfingstferien 2029', $context);

        self::assertInstanceOf(Clarification::class, $withoutYear);
        self::assertSame(ClarificationReason::OutOfData, $withoutYear->reason);
        self::assertInstanceOf(Resolved::class, $withYear);
        self::assertSame(State::NW, $withYear->state);
    }

    public function testTwoStatesAreNamedInTheQuestion(): void
    {
        $result = Resolver::create()->resolve('Herbstferien in Bayern und Hessen', $this->context());

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::MissingState, $result->reason);
        self::assertStringContainsString('Bayern und Hessen', $result->question);
    }

    public function testTwoStatesDoNotMatterWhenTheExpressionDoesNotDependOnThem(): void
    {
        self::assertInstanceOf(Resolved::class, Resolver::create()->resolve('Von Bremen nach Bayern morgen', $this->context()));
    }

    public function testSaarlandQuestionUsesTheRightPreposition(): void
    {
        $result = Resolver::create()->resolve('Halbjahresferien', $this->context(State::SL));

        self::assertInstanceOf(Clarification::class, $result);
        self::assertStringContainsString('Im Saarland', $result->question);
    }

    public function testPartlyStatutoryFeastUsesTheLocative(): void
    {
        $result = Resolver::create()->resolve('Fronleichnam', $this->context(State::TH));

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame(['In Thüringen nur in einem Teil der Gemeinden gesetzlicher Feiertag.'], $result->notes);
    }

    public function testHamburgOffersSpringHolidaysOnlyAndInventsNoEasterHolidays(): void
    {
        $result = Resolver::create()->resolve('Osterferien in Hamburg', $this->context());

        self::assertInstanceOf(Clarification::class, $result);
        self::assertStringContainsString('Hamburg', $result->question);
        self::assertSame('Frühjahrsferien', $result->candidates[0]->label);
    }

    public function testHamburgEasterHolidaysBeyondTheSnapshotAreOutOfData(): void
    {
        $result = Resolver::create()->resolve('Osterferien 2040', new Context(null, State::HH));

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::OutOfData, $result->reason);
    }

    public function testTypeThatTheStateDoesNotHaveIsNotAGapInTheData(): void
    {
        $result = Resolver::create()->resolve('Pfingstferien 2027', new Context(null, State::HE));

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::NotInState, $result->reason);
        self::assertStringContainsString('Hessen hat keine', $result->question);
        self::assertSame([], $result->candidates);
    }

    public function testSchoolHolidayYearSelectsEntryStartingInThatYear(): void
    {
        $result = Resolver::create()->resolve('Weihnachtsferien 2026', new Context(null, State::NI));

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2026-12-23', $result->period->start->format('Y-m-d'));
    }

    /**
     * @return list<array{string, string}>
     */
    private function ranges(Clarification $result): array
    {
        return array_map(
            static fn (Candidate $c): array => [$c->period->start->format('Y-m-d'), $c->period->end->format('Y-m-d')],
            $result->candidates,
        );
    }
}
