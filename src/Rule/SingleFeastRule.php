<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\Easter;
use Stejac\Zeitangaben\Calendar\YearlyDate;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Holiday\PublicHolidayProvider;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Result;
use Stejac\Zeitangaben\Text\YearToken;

/**
 * Himmelfahrt, Mariä Himmelfahrt, Fronleichnam, Allerheiligen and the single days around Easter and Pentecost, with a note where the day is no statutory holiday.
 */
final class SingleFeastRule implements Rule
{
    private const array EASTER_DAYS = [
        'gruendonnerstag' => Easter::MAUNDY_THURSDAY,
        'karfreitag' => Easter::GOOD_FRIDAY,
        'ostersonntag' => Easter::EASTER_SUNDAY,
        'ostermontag' => Easter::EASTER_MONDAY,
        'pfingstsonntag' => Easter::WHIT_SUNDAY,
        'pfingstmontag' => Easter::WHIT_MONDAY,
    ];

    public function __construct(private readonly PublicHolidayProvider $holidays)
    {
    }

    public function pattern(): string
    {
        return YearToken::around('\b(?:(?<mary>mariae\s+himmelfahrt)|(?<weekend>himmelfahrtswochenende)|(?<ascension>(?:christi\s+)?himmelfahrt)'
            .'|(?<corpus>fronleichnam)|(?<saints>allerheiligen)|(?<easterday>karfreitag|gruendonnerstag|ostersonntag|ostermontag|pfingstsonntag|pfingstmontag))\b');
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $expression = $match->original;
        $period = FeastPeriod::find(
            $expression,
            $match->year,
            $context,
            fn (int $year): Period => $this->periodIn($match, $year),
        );
        if (!$period instanceof Period) {
            return $period;
        }

        return new Resolved($period, $expression, $this->notes($match, $period, $context));
    }

    private function periodIn(RuleMatch $match, int $year): Period
    {
        return match (true) {
            isset($match->groups['mary']) => Period::day(YearlyDate::on($year, 8, 15)),
            isset($match->groups['saints']) => Period::day(YearlyDate::on($year, 11, 1)),
            isset($match->groups['corpus']) => Period::day(Easter::offset($year, Easter::CORPUS_CHRISTI)),
            isset($match->groups['easterday']) => Period::day(Easter::offset($year, self::EASTER_DAYS[$match->groups['easterday']])),
            isset($match->groups['weekend']) => new Period(
                Easter::offset($year, Easter::ASCENSION),
                Easter::offset($year, Easter::ASCENSION + 3),
            ),
            default => Period::day(Easter::offset($year, Easter::ASCENSION)),
        };
    }

    /**
     * @return list<string>
     */
    private function notes(RuleMatch $match, Period $period, Context $context): array
    {
        $state = $context->state;
        if (null === $state || in_array($match->groups['easterday'] ?? '', ['ostersonntag', 'pfingstsonntag'], true) || $this->holidays->isStatutory($period->start, $state)) {
            return [];
        }

        return [sprintf('In %s kein gesetzlicher Feiertag.', $state->germanName())];
    }
}
