<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\School;

enum SchoolHolidayType: string
{
    case Winter = 'winter';
    case Easter = 'easter';
    case Pentecost = 'pentecost';
    case Summer = 'summer';
    case Autumn = 'autumn';
    case Christmas = 'christmas';
}
