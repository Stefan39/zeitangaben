<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\Easter;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Result;
use Stejac\Zeitangaben\School\SchoolHolidayType;

/**
 * "Pfingsten": the long weekend and, where the state is known and has them, the Pentecost school holidays.
 */
final class PentecostRule implements Rule
{
    public function __construct(private readonly SchoolHolidayLookup $lookup)
    {
    }

    public function pattern(): string
    {
        return '\bpfingst(?:en|wochenende)\b'.FeastPeriod::YEAR_PATTERN;
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $expression = $match->expression;
        $feast = FeastPeriod::find(
            $expression,
            FeastPeriod::yearOf($match),
            $context,
            static fn (int $year): Period => new Period(
                Easter::offset($year, Easter::PENTECOST_SATURDAY),
                Easter::offset($year, Easter::WHIT_MONDAY),
            ),
        );
        if (!$feast instanceof Period) {
            return $feast;
        }

        $holidays = null === $context->state
            ? null
            : $this->lookup->startingIn($context->state, SchoolHolidayType::Pentecost, (int) $feast->start->format('Y'));

        return FeastPeriod::feastOrHolidays($expression, $feast, 'Pfingstsamstag bis Pfingstmontag', $holidays, 'Pfingstferien');
    }
}
