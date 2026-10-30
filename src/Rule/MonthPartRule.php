<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\Months;
use Stejac\Zeitangaben\Calendar\YearlyDate;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Result;

/**
 * "Anfang|Mitte|Ende Juli": a stretch of days that is deliberately not exact.
 */
final class MonthPartRule implements Rule
{
    private const array LABELS = ['anfang' => 'Anfang', 'mitte' => 'Mitte', 'ende' => 'Ende'];

    public function pattern(): string
    {
        return '\b(?<part>anfang|mitte|ende)\s+(?<month>'.Months::PATTERN.')\b';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $part = $match->groups['part'];
        $month = Months::number($match->groups['month']);

        return YearCandidates::clarify(
            $match->expression,
            $context,
            ClarificationReason::Vague,
            sprintf('„%s“ ist kein genauer Zeitraum. Von welchem bis zu welchem Tag meinen Sie?', $match->expression),
            fn (int $year): Period => $this->spanOf($part, $year, $month),
            fn (int $year): array => [$this->candidate($part, $year, $month)],
        );
    }

    private function spanOf(string $part, int $year, int $month): Period
    {
        $first = YearlyDate::on($year, $month, 1);
        $last = (int) $first->format('t');
        [$from, $to] = match ($part) {
            'anfang' => [1, 10],
            'mitte' => [10, 20],
            default => [22, $last],
        };

        return new Period(YearlyDate::on($year, $month, $from), YearlyDate::on($year, $month, $to));
    }

    private function candidate(string $part, int $year, int $month): Candidate
    {
        $span = $this->spanOf($part, $year, $month);

        return new Candidate($span, sprintf('%s, %s bis %s', self::LABELS[$part], $span->start->format('d.m.'), $span->end->format('d.m.Y')));
    }
}
