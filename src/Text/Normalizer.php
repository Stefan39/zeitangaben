<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Text;

/**
 * Brings input into the form every rule pattern is written against:
 * lowercase ASCII for German letters, umlauts transliterated.
 */
final class Normalizer
{
    private const array TRANSLITERATIONS = [
        "a\u{0308}" => 'ae',
        "o\u{0308}" => 'oe',
        "u\u{0308}" => 'ue',
        "A\u{0308}" => 'ae',
        "O\u{0308}" => 'oe',
        "U\u{0308}" => 'ue',
        'ä' => 'ae',
        'ö' => 'oe',
        'ü' => 'ue',
        'Ä' => 'ae',
        'Ö' => 'oe',
        'Ü' => 'ue',
        'ß' => 'ss',
        'ẞ' => 'ss',
    ];

    public static function normalize(string $text): string
    {
        return strtolower(strtr($text, self::TRANSLITERATIONS));
    }
}
