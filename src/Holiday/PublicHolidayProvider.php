<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Holiday;

use Stejac\Zeitangaben\State;

interface PublicHolidayProvider
{
    /**
     * @param ?State $state null means holidays valid nationwide
     *
     * @return list<\DateTimeImmutable> statutory holidays of the month, ascending
     */
    public function inMonth(int $year, int $month, ?State $state): array;

    public function isStatutory(\DateTimeImmutable $day, State $state): bool;
}
