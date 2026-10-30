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

/**
 * "12.06.2027", "12.06.", "12. Juni [2027]" and ranges such as "vom 12. bis 19. Oktober".
 */
final class ExplicitDateRule implements Rule
{
    private const string WEEKDAY = '(?:\b(?:montag|dienstag|mittwoch|donnerstag|freitag|samstag|sonntag)\s*,?\s*(?:den\s+)?)?';

    private const string DATE_TOKEN = '(?<![\d.])\d{1,2}\.\s?(?:\d{1,2}\.|(?:%s)\b)(?:\s?\d{4}(?!\d))?';

    public function pattern(): string
    {
        $token = sprintf(self::DATE_TOKEN, Months::PATTERN);

        return '\b(?:vom|von)\s+'.self::WEEKDAY.'(?<from>'.$token.'|(?<![\d.])\d{1,2}\.)\s+bis\s+'
            .self::WEEKDAY.'(?<to>'.$token.')'
            .'|'.self::WEEKDAY.'(?<date>'.$token.')';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $expression = $match->expression;
        if (isset($match->groups['date'])) {
            $date = self::parse($match->groups['date']);

            return YearlyDate::resolve($expression, $date['month'], $date['day'], $date['year'], $context);
        }

        return $this->evaluateRange($expression, $match->groups['from'], $match->groups['to'], $context);
    }

    private function evaluateRange(string $expression, string $fromText, string $toText, Context $context): Result
    {
        $to = self::parse($toText);
        $from = self::parseStart($fromText, $to);
        $knownYear = $to['year'] ?? $from['year'];

        if (!self::isValid($from, $knownYear) || !self::isValid($to, $knownYear)) {
            return Clarifications::invalidDate($expression);
        }
        if (null !== $knownYear) {
            return $this->fixedRange($expression, $from, $to, $knownYear);
        }

        return $this->upcomingRange($expression, $from, $to, $context);
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
            $endYear = [$to['month'], $to['day']] < [$from['month'], $from['day']] ? $year + 1 : $year;
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
     * @param array{day: int, month: int, year: ?int} $from
     * @param array{day: int, month: int, year: ?int} $to
     */
    private function fixedRange(string $expression, array $from, array $to, int $knownYear): Result
    {
        $start = YearlyDate::on($from['year'] ?? $knownYear, $from['month'], $from['day']);
        $end = YearlyDate::on($to['year'] ?? $knownYear, $to['month'], $to['day']);
        if ($end < $start) {
            return Clarifications::invalidDate($expression);
        }

        return new Resolved(new Period($start, $end), $expression);
    }

    /**
     * @param array{day: int, month: int, year: ?int} $date
     */
    private static function isValid(array $date, ?int $year): bool
    {
        return checkdate($date['month'], $date['day'], $date['year'] ?? $year ?? 2000);
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
        $pattern = '~^(?<day>\d{1,2})\.\s?(?:(?<num>\d{1,2})\.|(?<name>'.Months::PATTERN.'))(?:\s?(?<year>\d{4}))?$~';
        if (1 !== preg_match($pattern, $text, $m)) {
            throw new \LogicException(sprintf('Date token "%s" does not match the rule pattern.', $text));
        }

        return [
            'day' => (int) $m['day'],
            'month' => isset($m['num']) && '' !== $m['num'] ? (int) $m['num'] : Months::number($m['name'] ?? ''),
            'year' => isset($m['year']) ? (int) $m['year'] : null,
        ];
    }
}
