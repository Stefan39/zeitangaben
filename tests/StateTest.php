<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\State;

final class StateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?State}>
     */
    public static function texts(): iterable
    {
        yield 'name' => ['in bayern', State::BY];
        yield 'abbreviation nrw' => ['aus nrw', State::NW];
        yield 'long name' => ['in nordrhein-westfalen', State::NW];
        yield 'in sentence' => ['herbstferien in niedersachsen', State::NI];
        yield 'transliterated' => ['fuer thueringen', State::TH];
        yield 'two-word name' => ['nach baden wuerttemberg', State::BW];
        yield 'with article' => ['aus dem saarland', State::SL];
        yield 'contracted article' => ['im saarland', State::SL];
        yield 'labelled' => ['bundesland: hessen', State::HE];
        yield 'not a whole word' => ['in bayernwerk', null];
        yield 'bare name' => ['bayern', null];
        yield 'surname' => ['frau berlin', null];
        yield 'club' => ['fc bayern', null];
        yield 'bank' => ['bw bank', null];
        yield 'longer alias wins' => ['in sachsen-anhalt', State::ST];
        yield 'no state' => ['morgen', null];
    }

    #[DataProvider('texts')]
    public function testFromText(string $text, ?State $expected): void
    {
        self::assertSame($expected, State::fromText($text));
    }

    public function testAllFromTextFindsEveryDifferentState(): void
    {
        self::assertSame([State::BE, State::BY], State::allFromText('wir wohnen in berlin, fahren aber nach bayern'));
        self::assertSame([State::HE, State::SN], State::allFromText('in hessen und sachsen'));
        self::assertSame([State::BY], State::allFromText('in bayern oder aus bayern'));
    }

    public function testLocativeUsesTheRightPreposition(): void
    {
        self::assertSame('in Bayern', State::BY->locative());
        self::assertSame('im Saarland', State::SL->locative());
    }

    public function testEveryStateHasGermanName(): void
    {
        foreach (State::cases() as $state) {
            self::assertNotSame('', $state->germanName());
        }
        self::assertSame('Niedersachsen', State::NI->germanName());
    }
}
