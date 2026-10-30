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
}
