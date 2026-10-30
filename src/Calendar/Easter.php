<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Calendar;

/**
 * Easter Sunday of the Gregorian calendar (Meeus/Jones/Butcher) and the movable feasts counted from it.
 */
final class Easter
{
    public const int MAUNDY_THURSDAY = -3;
    public const int GOOD_FRIDAY = -2;
    public const int EASTER_SUNDAY = 0;
    public const int EASTER_MONDAY = 1;
    public const int ASCENSION = 39;
    public const int PENTECOST_SATURDAY = 48;
    public const int WHIT_SUNDAY = 49;
    public const int WHIT_MONDAY = 50;
    public const int CORPUS_CHRISTI = 60;

    public static function sunday(int $year): \DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = ($h + $l - 7 * $m + 114) % 31 + 1;

        return YearlyDate::on($year, $month, $day);
    }

    /**
     * @param int $days offset from Easter Sunday; negative before it
     */
    public static function offset(int $year, int $days): \DateTimeImmutable
    {
        return self::sunday($year)->modify(sprintf('%+d days', $days));
    }
}
