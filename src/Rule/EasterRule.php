<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\Easter;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Result;
use Stejac\Zeitangaben\School\SchoolHolidayType;
use Stejac\Zeitangaben\Text\YearToken;

/**
 * "über Ostern": the bank holidays and, where the state is known, the Easter school holidays.
 */
final class EasterRule implements Rule
{
    public function __construct(private readonly SchoolHolidayLookup $lookup)
    {
    }

    public function pattern(): string
    {
        return YearToken::around('\b(?:ueber\s+)?ostern\b');
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $expression = $match->original;
        $feast = FeastPeriod::find(
            $expression,
            $match->year,
            $context,
            static fn (int $year): Period => new Period(
                Easter::offset($year, Easter::GOOD_FRIDAY),
                Easter::offset($year, Easter::EASTER_MONDAY),
            ),
        );
        if (!$feast instanceof Period) {
            return $feast;
        }

        $holidays = null === $context->state
            ? null
            : $this->lookup->startingIn($context->state, SchoolHolidayType::Easter, (int) $feast->start->format('Y'));

        return FeastPeriod::feastOrHolidays($expression, $feast, 'Karfreitag bis Ostermontag', $holidays, 'Osterferien');
    }
}
