<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Text;

/**
 * Brings input into the form every rule pattern is written against:
 * lowercase ASCII for German letters, umlauts transliterated.
 */
final class Normalizer
{
    /** Longer (decomposed) sequences come first so they win over their single-letter parts. */
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
        return strtolower(preg_replace('/\s+/', ' ', strtr($text, self::TRANSLITERATIONS)) ?? '');
    }

    /**
     * Same result as normalize(), plus the byte offsets that lead back into the original text.
     */
    public static function withOffsets(string $text): NormalizedText
    {
        $normalized = '';
        $starts = [];
        $ends = [];
        $length = strlen($text);
        for ($position = 0; $position < $length;) {
            $source = self::sourceAt($text, $position);
            $replacement = '' === trim($source) ? ' ' : strtolower(self::TRANSLITERATIONS[$source] ?? $source);
            for ($i = 0, $n = strlen($replacement); $i < $n; ++$i) {
                $starts[] = $position;
                $ends[] = $position + strlen($source);
            }
            $normalized .= $replacement;
            $position += strlen($source);
        }

        return new NormalizedText($text, $normalized, $starts, $ends);
    }

    /**
     * The unit of the original text at a position: a transliterated sequence, a whitespace run or one byte.
     */
    private static function sourceAt(string $text, int $position): string
    {
        foreach (array_keys(self::TRANSLITERATIONS) as $key) {
            if (0 === substr_compare($text, $key, $position, strlen($key))) {
                return $key;
            }
        }
        if (1 === preg_match('/\G\s+/', $text, $run, 0, $position)) {
            return $run[0];
        }

        return $text[$position];
    }
}
