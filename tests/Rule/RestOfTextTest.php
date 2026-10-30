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
use Stejac\Zeitangaben\Rule\RestOfText;
use Stejac\Zeitangaben\State;

final class RestOfTextTest extends TestCase
{
    private function resolve(string $text, ?State $state = null): Result
    {
        return Resolver::create()->resolve($text, new Context(new \DateTimeImmutable('2026-10-30'), $state));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function harmlessWords(): iterable
    {
        foreach (RestOfText::HARMLESS_WORDS as $word) {
            yield $word => [$word];
        }
    }

    #[DataProvider('harmlessWords')]
    public function testEveryHarmlessWordStaysResolved(string $word): void
    {
        $result = $this->resolve('Bitte im Juli '.$word.', danke.');

        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame('2027-07-01', $result->period->start->format('Y-m-d'));
    }

    public function testTheListHoldsNoNegatorsNumbersOrDuplicates(): void
    {
        $words = RestOfText::HARMLESS_WORDS;

        self::assertSame([], array_values(array_filter(
            $words,
            static fn (string $word): bool => 1 === preg_match('~^(?:nicht|nie|nein|kein|ausser|ohne)|\d~', $word),
        )));
        self::assertSame($words, array_values(array_unique($words)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function negated(): iterable
    {
        yield 'before' => ['Nicht im Juli'];
        yield 'after' => ['im Juli nicht'];
        yield 'after a comma' => ['im Juli, nicht'];
        yield 'far after' => ['im Juli kommen wir nicht'];
        yield 'other clause' => ['Das geht nicht. Im Juli gerne'];
        yield 'never' => ['nie im Juli'];
        yield 'none' => ['Keine Zeit im Juli'];
        yield 'except' => ['außer im Juli'];
        yield 'outside' => ['außerhalb im Juli'];
        yield 'without' => ['ohne den 12.06.'];
    }

    #[DataProvider('negated')]
    public function testANegatorAnywhereAsks(string $text): void
    {
        self::assertInstanceOf(Clarification::class, $this->resolve($text));
    }

    public function testQuestionNamesThePeriodAndTheOnlyCandidate(): void
    {
        $result = $this->resolve('Vielleicht im Juli');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::UnclearContext, $result->reason);
        self::assertSame('Meinen Sie „im Juli“, also 01.07.2027 bis 31.07.2027?', $result->question);
        self::assertSame('im Juli', $result->expression);
        self::assertCount(1, $result->candidates);
        self::assertSame('im Juli', $result->candidates[0]->label);
        self::assertSame(['start' => '2027-07-01', 'end' => '2027-07-31'], $result->candidates[0]->period->toArray());
    }

    public function testQuestionNamesASingleDayOnce(): void
    {
        $result = $this->resolve('Vielleicht morgen');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame('Meinen Sie „morgen“, also 31.10.2026?', $result->question);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedRest(): iterable
    {
        yield 'greeting' => ['Guten Morgen, im Juli gerne'];
        yield 'feast greeting' => ['Frohe Ostern, wir kommen im Juli'];
        yield 'state mention' => ['Wir kommen im Juli in Bayern an'];
        yield 'several punctuation marks' => ['Hallo! (im Juli): ja; gerne - danke?'];
        yield 'year in the span' => ['Wir kommen im Juli 2027'];
    }

    #[DataProvider('acceptedRest')]
    public function testGreetingsMentionsAndPunctuationAreNoRest(string $text): void
    {
        self::assertInstanceOf(Resolved::class, $this->resolve($text));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownRest(): iterable
    {
        yield 'number' => ['im Juli 3'];
        yield 'number word' => ['im Juli drei'];
        yield 'noun' => ['im Juli Hotel'];
        yield 'other mark' => ['im Juli & gerne'];
        yield 'quoted expression' => ['„im Juli“'];
        yield 'long dash' => ['im Juli – gerne'];
    }

    #[DataProvider('unknownRest')]
    public function testEverythingElseAsks(string $text): void
    {
        $result = $this->resolve($text);

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::UnclearContext, $result->reason);
    }

    public function testOtherResultTypesPassThrough(): void
    {
        $ambiguous = $this->resolve('Vielleicht über Ostern', State::NI);
        self::assertInstanceOf(Clarification::class, $ambiguous);
        self::assertSame(ClarificationReason::Ambiguous, $ambiguous->reason);

        self::assertInstanceOf(NoExpression::class, $this->resolve('Vielleicht Hotel'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function bareStates(): iterable
    {
        yield 'next to the expression' => ['Herbstferien Bayern', 'Bayern'];
        yield 'club' => ['FC Bayern spielt in den Herbstferien', 'Bayern'];
        yield 'name' => ['Frau Berlin kommt in den Herbstferien', 'Berlin'];
        yield 'abbreviation' => ['Herbstferien nrw', 'Nordrhein-Westfalen'];
        yield 'with a space' => ['Herbstferien Sachsen Anhalt', 'Sachsen-Anhalt'];
    }

    #[DataProvider('bareStates')]
    public function testBareStateAsksForTheStateAndNamesIt(string $text, string $name): void
    {
        foreach ([State::NI, null] as $state) {
            $result = $this->resolve($text, $state);

            self::assertInstanceOf(Clarification::class, $result);
            self::assertSame(ClarificationReason::MissingState, $result->reason);
            self::assertSame([], $result->candidates);
            self::assertStringContainsString($name, $result->question);
        }
    }

    public function testBareStateBesideAMentionStillAsks(): void
    {
        $result = $this->resolve('Herbstferien in Hessen, Grüße aus Bayern Frau Berlin');

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::MissingState, $result->reason);
    }

    public function testBareStateIsOnlyAnUnknownWordWhenTheResultDoesNotDependOnIt(): void
    {
        $result = $this->resolve('Silvester Bayern', State::NI);

        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame(ClarificationReason::UnclearContext, $result->reason);
        self::assertSame('2026-12-31', $result->candidates[0]->period->start->format('Y-m-d'));
    }
}
