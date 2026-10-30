<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Holiday;

use Stejac\Zeitangaben\State;
use Yasumi\Holiday;
use Yasumi\ProviderInterface;
use Yasumi\Yasumi;

/**
 * Adapts the regional German providers of Yasumi.
 */
final class YasumiPublicHolidays implements PublicHolidayProvider
{
    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    public function inMonth(int $year, int $month, ?State $state): array
    {
        $days = [];
        foreach ($this->provider($year, $state)->getHolidays() as $holiday) {
            if (Holiday::TYPE_OFFICIAL === $holiday->getType() && (int) $holiday->format('n') === $month) {
                $days[] = new \DateTimeImmutable($holiday->format('Y-m-d'), new \DateTimeZone('UTC'));
            }
        }
        sort($days);

        return $days;
    }

    public function isStatutory(\DateTimeImmutable $day, State $state): bool
    {
        $month = (int) $day->format('n');
        $target = $day->format('Y-m-d');
        foreach ($this->inMonth((int) $day->format('Y'), $month, $state) as $holiday) {
            if ($holiday->format('Y-m-d') === $target) {
                return true;
            }
        }

        return false;
    }

    private function provider(int $year, ?State $state): ProviderInterface
    {
        $name = 'Germany'.(null === $state ? '' : '/'.self::regionOf($state));

        return $this->providers[$name.'|'.$year] ??= Yasumi::create($name, $year);
    }

    private static function regionOf(State $state): string
    {
        return match ($state) {
            State::BB => 'Brandenburg',
            State::BE => 'Berlin',
            State::BW => 'BadenWurttemberg',
            State::BY => 'Bavaria',
            State::HB => 'Bremen',
            State::HE => 'Hesse',
            State::HH => 'Hamburg',
            State::MV => 'MecklenburgWesternPomerania',
            State::NI => 'LowerSaxony',
            State::NW => 'NorthRhineWestphalia',
            State::RP => 'RhinelandPalatinate',
            State::SH => 'SchleswigHolstein',
            State::SL => 'Saarland',
            State::SN => 'Saxony',
            State::ST => 'SaxonyAnhalt',
            State::TH => 'Thuringia',
        };
    }
}
