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

    /**
     * Same result as normalize(), plus the byte offsets that lead back into the original text.
     */
    public static function withOffsets(string $text): NormalizedText
    {
        $keys = array_keys(self::TRANSLITERATIONS);
        usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $normalized = '';
        $starts = [];
        $ends = [];
        $length = strlen($text);
        for ($position = 0; $position < $length;) {
            $source = self::sourceAt($text, $position, $keys);
            $replacement = strtolower(self::TRANSLITERATIONS[$source] ?? $source);
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
     * @param list<string> $keys transliterated sequences, longest first
     */
    private static function sourceAt(string $text, int $position, array $keys): string
    {
        foreach ($keys as $key) {
            if (0 === substr_compare($text, $key, $position, strlen($key))) {
                return $key;
            }
        }

        return $text[$position];
    }
}
