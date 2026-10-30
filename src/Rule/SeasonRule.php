<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\YearlyDate;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Result;

/**
 * "im Frühjahr", "im Sommer", "im Herbst", "im Winter": the meteorological seasons.
 * "Frühjahrsferien" is a school holiday and not matched here.
 */
final class SeasonRule implements Rule
{
    private const array LABELS = [
        'spring' => 'Frühjahr (März bis Mai)',
        'summer' => 'Sommer (Juni bis August)',
        'autumn' => 'Herbst (September bis November)',
        'winter' => 'Winter (Dezember bis Februar)',
    ];

    public function pattern(): string
    {
        return '\b(?:(?:im\s+)?(?<spring>fruehjahr|fruehling)|im\s+(?:(?<summer>sommer)|(?<autumn>herbst)|(?<winter>winter)))\b'
            .FeastPeriod::YEAR_PATTERN;
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $season = $this->seasonOf($match);
        $span = static fn (int $year): Period => self::spanOf($season, $year);

        return YearCandidates::clarify(
            $match->original,
            $context,
            ClarificationReason::Vague,
            sprintf('„%s“ ist kein genauer Zeitraum. Meinen Sie die ganze Jahreszeit (%s), oder einen bestimmten Abschnitt davon?', $match->original, self::LABELS[$season]),
            $span,
            static fn (int $year): array => [new Candidate($span($year), self::LABELS[$season])],
            FeastPeriod::yearOf($match),
        );
    }

    private function seasonOf(RuleMatch $match): string
    {
        foreach (array_keys(self::LABELS) as $season) {
            if (isset($match->groups[$season])) {
                return $season;
            }
        }

        return 'spring';
    }

    private static function spanOf(string $season, int $year): Period
    {
        return match ($season) {
            'summer' => new Period(YearlyDate::on($year, 6, 1), YearlyDate::on($year, 8, 31)),
            'autumn' => new Period(YearlyDate::on($year, 9, 1), YearlyDate::on($year, 11, 30)),
            'winter' => new Period(YearlyDate::on($year, 12, 1), YearlyDate::on($year + 1, 3, 1)->modify('-1 day')),
            default => new Period(YearlyDate::on($year, 3, 1), YearlyDate::on($year, 5, 31)),
        };
    }
}
