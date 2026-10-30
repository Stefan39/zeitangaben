<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests\Text;

use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Text\Normalizer;

final class NormalizerTest extends TestCase
{
    public function testTransliteratesUmlautsAndLowercases(): void
    {
        self::assertSame('fruehjahr in grossstaedten', Normalizer::normalize('Frühjahr IN Großstädten'));
        self::assertSame('uebermorgen oeffnen', Normalizer::normalize('ÜBERMORGEN Öffnen'));
    }

    public function testDecomposedUmlautsBehaveLikeComposed(): void
    {
        self::assertSame('fruehjahr', Normalizer::normalize("Fru\u{0308}hjahr"));
    }

    public function testOffsetsLeadBackToTheOriginalSpelling(): void
    {
        $text = "Fru\u{0308}hjahr für Straße";
        $normalized = Normalizer::withOffsets($text);

        self::assertSame(Normalizer::normalize($text), $normalized->text);
        self::assertSame("Fru\u{0308}hjahr", $normalized->originalOf(0, 9));
        self::assertSame('für', $normalized->originalOf(10, 4));
        self::assertSame('Straße', $normalized->originalOf(15, 7));
        self::assertSame('', $normalized->originalOf(0, 0));
    }

    public function testWhitespaceRunsCollapseAndMapBackToTheWholeRun(): void
    {
        $normalized = Normalizer::withOffsets("vor \t Ostern");

        self::assertSame('vor ostern', $normalized->text);
        self::assertSame("vor \t Ostern", $normalized->originalOf(0, 10));
        self::assertSame(Normalizer::normalize("vor \t Ostern"), $normalized->text);
    }
}
