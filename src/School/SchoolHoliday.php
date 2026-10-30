<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\School;

use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\State;

final readonly class SchoolHoliday
{
    public function __construct(
        public State $state,
        public SchoolHolidayType $type,
        public string $name,
        public Period $period,
    ) {
    }
}
