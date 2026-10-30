<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Text;

/**
 * The one definition of how a year is written next to an expression.
 *
 * Four digits count only inside the window 2000 to 2099. Two digits count only from 00 to 69 and are read as
 * 20xx: directly after a numeric date, behind an apostrophe, or at the very end of the input. A number followed
 * by a unit is a quantity, not a year; such numbers are masked before any rule runs. Any other word after a
 * year changes nothing: dropping a stated year would be a silently wrong answer.
 *
 * @internal
 */
final class YearToken
{
    private const string RELATIVE = 'uebernaechstes|uebernaechsten|naechstes|naechsten|kommendes|kommenden|letztes|letzten|voriges|vorigen|dieses|diesem';

    private const string RELATIVE_NOUN = 'jahr(?:es)?\b';

    private const array RELATIVE_OFFSETS = [
        'uebernaechstes' => 2,
        'uebernaechsten' => 2,
        'naechstes' => 1,
        'naechsten' => 1,
        'kommendes' => 1,
        'kommenden' => 1,
        'letztes' => -1,
        'letzten' => -1,
        'voriges' => -1,
        'vorigen' => -1,
        'dieses' => 0,
        'diesem' => 0,
    ];

    private const string LONG = '(?:\s+im\s+jahr)?\s+(?<year>20\d\d)(?!\d|[.,]\d)'
        .'|\s*/\s*(?<slash>20\d\d)(?!\d)'
        .'|\s*(?:\'|’)(?<apostrophe>[0-6]\d)(?![\w.])'
        .'|\s+(?:(?:im|in)\s+)?(?<relative>'.self::RELATIVE.')\s+'.self::RELATIVE_NOUN;

    private const string SHORT = '|(?:\s+im\s+jahr)?\s+(?<short>[0-6]\d)(?=\s*$)';

    /** Trailing year for months, feasts and holidays: all forms, including two digits at the end. */
    public const string TRAILING = '(?:'.self::LONG.self::SHORT.')?';

    /** Trailing year for expressions that end in a number themselves, such as "KW 42": no two-digit form. */
    public const string TRAILING_LONG = '(?:'.self::LONG.')?';

    public const string LEADING = '(?:(?<![\w.,/-])(?:(?<leadyear>20\d\d)\s+|(?:(?:im|in)\s+)?(?<leadrelative>'.self::RELATIVE.')\s+'.self::RELATIVE_NOUN.'\s+))?';

    /** A year attached to a numeric date token, as in "12.06.2028", "12.06. 2028" or "12.06.28". */
    public const string ATTACHED_NUMERIC = '(?:\s?20\d\d|\d\d)?';

    /** A year attached to a date token with a month name, as in "12. Juni 2028". */
    public const string ATTACHED_NAMED = '(?:\s+20\d\d)?';

    /** Words that describe a quantity when they follow a number. */
    private const string UNIT = '~^\s*(?:euro|eur|€|%|prozent|personen|person|gaeste|gast|leute|kinder|erwachsene|km|m|qm|uhr|tage|naechte|wochen|monate|stunden|zimmer|stueck|stk)(?![a-z])~';

    private const string CAPITALISED_WORD = '~^\s+([a-z])~';

    /**
     * Wraps a rule pattern with the optional leading and trailing year.
     */
    public static function around(string $pattern, string $trailing = self::TRAILING): string
    {
        return self::LEADING.'(?:'.$pattern.')'.$trailing;
    }

    /**
     * Blanks every four-digit number in the window that is a quantity rather than a year.
     * Offsets stay valid, so the text still lines up with the original input.
     */
    public static function mask(string $text): string
    {
        preg_match_all('~(?<![\w.,/-])20\d\d(?!\d)~', $text, $numbers, PREG_OFFSET_CAPTURE);
        foreach ($numbers[0] as [, $offset]) {
            if (1 === preg_match(self::UNIT, substr($text, $offset + 4))) {
                $text = substr_replace($text, '    ', $offset, 4);
            }
        }

        return $text;
    }

    /**
     * Whether the word after the given offset shows that the number before it counts something.
     */
    public static function isFollowedByNoun(NormalizedText $source, string $text, int $end): bool
    {
        return 1 === preg_match(self::UNIT, substr($text, $end)) || self::isFollowedByCapital($source, $text, $end);
    }

    /**
     * @param array<int|string, string> $groups
     */
    public static function needsReference(array $groups): bool
    {
        return isset($groups['relative']) || isset($groups['leadrelative']);
    }

    /**
     * @param array<int|string, string> $groups
     */
    public static function of(array $groups, ?\DateTimeImmutable $reference): ?int
    {
        $digits = $groups['year'] ?? $groups['slash'] ?? $groups['leadyear'] ?? $groups['short'] ?? $groups['apostrophe'] ?? null;
        if (null !== $digits) {
            return self::fromDigits($digits);
        }

        $relative = $groups['relative'] ?? $groups['leadrelative'] ?? null;

        return null === $relative || null === $reference
            ? null
            : (int) $reference->format('Y') + self::RELATIVE_OFFSETS[$relative];
    }

    /**
     * Whether the date token ends in a two-digit year outside 00 to 69, as in "12.06.85".
     */
    public static function endsInOldShortYear(string $dateToken): bool
    {
        return 1 === preg_match('~\.[7-9]\d$~', $dateToken);
    }

    public static function fromDigits(string $digits): int
    {
        return 2000 + (int) $digits % 100;
    }

    private static function isFollowedByCapital(NormalizedText $source, string $text, int $end): bool
    {
        if (1 !== preg_match(self::CAPITALISED_WORD, substr($text, $end), $word, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        return 1 === preg_match('/^\p{Lu}/u', $source->originalOf($end + $word[1][1], 1));
    }
}
