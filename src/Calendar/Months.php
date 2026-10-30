<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Calendar;

use Stejac\Zeitangaben\InvalidPeriodException;

/**
 * German month names in normalised (umlaut-transliterated, lowercase) form.
 *
 * @internal
 */
final class Months
{
    public const string PATTERN = 'januar|februar|maerz|april|mai|juni|juli|august|september|oktober|november|dezember';

    private const array NUMBERS = [
        'januar' => 1,
        'februar' => 2,
        'maerz' => 3,
        'april' => 4,
        'mai' => 5,
        'juni' => 6,
        'juli' => 7,
        'august' => 8,
        'september' => 9,
        'oktober' => 10,
        'november' => 11,
        'dezember' => 12,
    ];

    public static function number(string $name): int
    {
        return self::NUMBERS[$name] ?? throw new InvalidPeriodException(sprintf('Unknown month "%s".', $name));
    }
}
