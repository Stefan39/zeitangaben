<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\School;

use Stejac\Zeitangaben\State;

interface SchoolHolidayProvider
{
    /**
     * @return list<SchoolHoliday> sorted by start
     */
    public function forState(State $state, SchoolHolidayType $type): array;
}
