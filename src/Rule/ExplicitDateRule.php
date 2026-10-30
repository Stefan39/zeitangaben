<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\Months;
use Stejac\Zeitangaben\Calendar\NextOccurrence;
use Stejac\Zeitangaben\Calendar\YearlyDate;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Result;
use Stejac\Zeitangaben\Text\YearToken;

/**
 * "12.06.2027", "12.06.", "12. Juni [2027]", ISO "2027-06-12" and ranges such as "vom 12. bis 19. Oktober",
 * "zwischen dem 12. und 19. Oktober" or "12.-19.10.".
 *
 * @internal
 */
final class ExplicitDateRule implements Rule
{
    private const array WEEKDAYS = [
        'montag' => 1,
        'dienstag' => 2,
        'mittwoch' => 3,
        'donnerstag' => 4,
        'freitag' => 5,
        'samstag' => 6,
        'sonnabend' => 6,
        'sonntag' => 7,
    ];

    private const string WEEKDAY = '(?:\b(?<%s>montag|dienstag|mittwoch|donnerstag|freitag|samstag|sonnabend|sonntag)\s*,?\s*(?:(?:den|der)\s+)?)?';

    private const string DATE_TOKEN = '(?<![\d.])\d{1,2}\.\s?(?:\d{1,2}\.%s|(?:%s)\b%s)(?!\d|\.\d)';

    private const string BARE_DAY = '(?<![\d.])\d{1,2}\.(?!\d)';

    private const string ISO = '(?<![\w.-])(?<iso>20\d\d-\d{2}-\d{2})(?![\w-])';

    private const string RANGE_START = '(?:\b(?:vom|von|ab)\s+|\b(?<between>zwischen)\s+(?:dem\s+)?)?';

    private const string RANGE_SEPARATOR = '(?(<between>)\s+und\s+(?:dem\s+)?|(?:\s+bis\s+(?:(?:zum|einschliesslich)\s+)?|\s*[-–—]\s*))';

    public function pattern(): string
    {
        $token = sprintf(self::DATE_TOKEN, YearToken::ATTACHED_NUMERIC, Months::PATTERN, YearToken::ATTACHED_NAMED);

        return self::ISO
            .'|(?:'.self::RANGE_START.sprintf(self::WEEKDAY, 'fromday').'(?<from>'.$token.'|'.self::BARE_DAY.')'
            .self::RANGE_SEPARATOR.sprintf(self::WEEKDAY, 'today').'(?<to>'.$token.')'
            .'|'.sprintf(self::WEEKDAY, 'dateday').'(?<date>'.$token.'))'.YearToken::TRAILING;
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $groups = $match->groups;
        if (isset($groups['from'], $groups['to'])) {
            $result = $this->evaluateRange($match, $context);

            return $this->checkWeekdays($result, $match->original, $groups['fromday'] ?? null, $groups['today'] ?? null);
        }

        $date = self::parse($groups['date'] ?? $groups['iso']);
        $result = YearlyDate::resolve($match->original, $date['month'], $date['day'], $date['year'] ?? $match->year, $context);

        return $this->checkWeekdays($result, $match->original, $groups['dateday'] ?? null, null);
    }

    private function evaluateRange(RuleMatch $match, Context $context): Result
    {
        $expression = $match->original;
        $to = self::parse($match->groups['to']);
        $to['year'] ??= $match->year;
        $from = self::parseStart($match->groups['from'], $to);

        if (!self::isValid($from) || !self::isValid($to) || !self::isPossibleOrder($from, $to)) {
            return Clarifications::invalidDate($expression);
        }
        if (null === $from['year'] && null === $to['year']) {
            return $this->upcomingRange($expression, $from, $to, $context);
        }

        return $this->fixedRange($expression, $from, $to);
    }

    /**
     * A later end than start is always possible when a year is given on both sides. Without that, an end before
     * the start only makes sense across New Year: from November or December into January or February.
     *
     * @param array{day: int, month: int, year: ?int} $from
     * @param array{day: int, month: int, year: ?int} $to
     */
    private static function isPossibleOrder(array $from, array $to): bool
    {
        if (null !== $from['year'] && null !== $to['year']) {
            return true;
        }

        return !self::endsEarlierInYear($from, $to) || ($from['month'] >= 11 && $to['month'] <= 2);
    }

    /**
     * @param array{day: int, month: int, year: ?int} $from
     * @param array{day: int, month: int, year: ?int} $to
     */
    private static function endsEarlierInYear(array $from, array $to): bool
    {
        return [$to['month'], $to['day']] < [$from['month'], $from['day']];
    }

    /**
     * @param array{day: int, month: int, year: ?int} $from
     * @param array{day: int, month: int, year: ?int} $to
     */
    private function upcomingRange(string $expression, array $from, array $to, Context $context): Result
    {
        if (null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($expression);
        }

        $period = NextOccurrence::of($context->referenceDate, static function (int $year) use ($from, $to): ?Period {
            $endYear = self::endsEarlierInYear($from, $to) ? $year + 1 : $year;
            if (!checkdate($from['month'], $from['day'], $year) || !checkdate($to['month'], $to['day'], $endYear)) {
                return null;
            }

            return new Period(
                YearlyDate::on($year, $from['month'], $from['day']),
                YearlyDate::on($endYear, $to['month'], $to['day']),
            );
        });

        return null === $period ? Clarifications::invalidDate($expression) : new Resolved($period, $expression);
    }

    /**
     * One year given on either side applies to that side; the other side follows from the order of the dates.
     *
     * @param array{day: int, month: int, year: ?int} $from
     * @param array{day: int, month: int, year: ?int} $to
     */
    private function fixedRange(string $expression, array $from, array $to): Result
    {
        $crossing = self::endsEarlierInYear($from, $to) ? 1 : 0;
        $startYear = $from['year'] ?? (int) $to['year'] - $crossing;
        $endYear = $to['year'] ?? $startYear + $crossing;
        if (!checkdate($from['month'], $from['day'], $startYear) || !checkdate($to['month'], $to['day'], $endYear)) {
            return Clarifications::invalidDate($expression);
        }

        $start = YearlyDate::on($startYear, $from['month'], $from['day']);
        $end = YearlyDate::on($endYear, $to['month'], $to['day']);

        return $end < $start
            ? Clarifications::invalidDate($expression)
            : new Resolved(new Period($start, $end), $expression);
    }

    /**
     * A weekday that contradicts the date next to it means one of the two is wrong.
     */
    private function checkWeekdays(Result $result, string $expression, ?string $startDay, ?string $endDay): Result
    {
        if (!$result instanceof Resolved) {
            return $result;
        }

        return self::weekdayConflict($expression, $result->period->start, $startDay)
            ?? self::weekdayConflict($expression, $result->period->end, $endDay)
            ?? $result;
    }

    private static function weekdayConflict(string $expression, \DateTimeImmutable $date, ?string $weekday): ?Result
    {
        return null === $weekday || self::WEEKDAYS[$weekday] === (int) $date->format('N')
            ? null
            : Clarifications::weekdayMismatch($expression, $date, $weekday);
    }

    /**
     * @param array{day: int, month: int, year: ?int} $date
     */
    private static function isValid(array $date): bool
    {
        return checkdate($date['month'], $date['day'], $date['year'] ?? 2000);
    }

    /**
     * @param array{day: int, month: int, year: ?int} $end
     *
     * @return array{day: int, month: int, year: ?int}
     */
    private static function parseStart(string $text, array $end): array
    {
        if (1 === preg_match('~^(\d{1,2})\.$~', $text, $m)) {
            return ['day' => (int) $m[1], 'month' => $end['month'], 'year' => $end['year']];
        }

        return self::parse($text);
    }

    /**
     * @return array{day: int, month: int, year: ?int}
     */
    private static function parse(string $text): array
    {
        if (1 === preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $text, $iso)) {
            return ['day' => (int) $iso[3], 'month' => (int) $iso[2], 'year' => (int) $iso[1]];
        }

        $pattern = '~^(?<day>\d{1,2})\.\s?(?:(?<num>\d{1,2})\.|(?<name>'.Months::PATTERN.'))\s?(?<year>\d{2}|\d{4})?$~';
        if (1 !== preg_match($pattern, $text, $m)) {
            throw new \LogicException(sprintf('Date token "%s" does not match the rule pattern.', $text));
        }

        return [
            'day' => (int) $m['day'],
            'month' => '' !== ($m['num'] ?? '') ? (int) $m['num'] : Months::number($m['name'] ?? ''),
            'year' => isset($m['year']) ? YearToken::fromDigits($m['year']) : null,
        ];
    }
}
