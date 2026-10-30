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
        yield 'name' => ['bayern', State::BY];
        yield 'abbreviation nrw' => ['nrw', State::NW];
        yield 'long name' => ['nordrhein-westfalen', State::NW];
        yield 'in sentence' => ['herbstferien in niedersachsen', State::NI];
        yield 'transliterated' => ['thueringen', State::TH];
        yield 'two-word name' => ['baden-wuerttemberg', State::BW];
        yield 'not a whole word' => ['bayernwerk', null];
        yield 'no state' => ['morgen', null];
    }

    #[DataProvider('texts')]
    public function testFromText(string $text, ?State $expected): void
    {
        self::assertSame($expected, State::fromText($text));
    }

    public function testEveryStateHasGermanName(): void
    {
        foreach (State::cases() as $state) {
            self::assertNotSame('', $state->germanName());
        }
        self::assertSame('Niedersachsen', State::NI->germanName());
    }
}
