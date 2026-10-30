<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\School\SchoolHoliday;
use Stejac\Zeitangaben\School\SchoolHolidayProvider;
use Stejac\Zeitangaben\School\SchoolHolidayType;
use Stejac\Zeitangaben\State;

/**
 * Picks one entry from the school holiday snapshot; never extrapolates beyond it.
 */
final readonly class SchoolHolidayLookup
{
    public function __construct(private SchoolHolidayProvider $provider)
    {
    }

    public function startingIn(State $state, SchoolHolidayType $type, int $year): ?SchoolHoliday
    {
        foreach ($this->provider->forState($state, $type) as $holiday) {
            if ((int) $holiday->period->start->format('Y') === $year) {
                return $holiday;
            }
        }

        return null;
    }

    /**
     * The first entry that has not ended before the reference date.
     */
    public function upcoming(State $state, SchoolHolidayType $type, Context $context): ?SchoolHoliday
    {
        if (null === $context->referenceDate) {
            return null;
        }
        $today = $context->referenceDate->format('Y-m-d');
        foreach ($this->provider->forState($state, $type) as $holiday) {
            if ($holiday->period->end->format('Y-m-d') >= $today) {
                return $holiday;
            }
        }

        return null;
    }

    public function has(State $state, SchoolHolidayType $type): bool
    {
        return [] !== $this->provider->forState($state, $type);
    }
}
