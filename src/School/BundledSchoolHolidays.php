<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\School;

use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\State;

final class BundledSchoolHolidays implements SchoolHolidayProvider
{
    /** @var list<SchoolHoliday>|null */
    private ?array $holidays = null;

    public function __construct(private readonly string $path = __DIR__.'/../../data/school-holidays-de.json')
    {
    }

    public function forState(State $state, SchoolHolidayType $type): array
    {
        $matching = array_filter(
            $this->load(),
            static fn (SchoolHoliday $h): bool => $h->state === $state && $h->type === $type,
        );
        usort($matching, static fn (SchoolHoliday $a, SchoolHoliday $b): int => $a->period->start <=> $b->period->start);

        return $matching;
    }

    /**
     * @return list<SchoolHoliday>
     */
    private function load(): array
    {
        if (null !== $this->holidays) {
            return $this->holidays;
        }

        $rows = $this->readRows();
        $holidays = [];
        foreach ($rows as $row) {
            $holidays[] = $this->hydrate($row);
        }

        return $this->holidays = $holidays;
    }

    /**
     * @return list<mixed>
     */
    private function readRows(): array
    {
        $json = is_file($this->path) ? file_get_contents($this->path) : false;
        if (false === $json) {
            throw new SchoolHolidayDataException(sprintf('School holiday data file "%s" is not readable.', $this->path));
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SchoolHolidayDataException('School holiday data is not valid JSON.', 0, $e);
        }

        if (!is_array($data) || !isset($data['holidays']) || !is_array($data['holidays'])) {
            throw new SchoolHolidayDataException('School holiday data has no "holidays" list.');
        }

        return array_values($data['holidays']);
    }

    private function hydrate(mixed $row): SchoolHoliday
    {
        if (!is_array($row)) {
            throw new SchoolHolidayDataException('School holiday row is not an object.');
        }
        foreach (['state', 'type', 'name', 'start', 'end'] as $key) {
            if (!isset($row[$key]) || !is_string($row[$key])) {
                throw new SchoolHolidayDataException(sprintf('School holiday row misses string field "%s".', $key));
            }
        }

        try {
            return new SchoolHoliday(
                State::from($row['state']),
                SchoolHolidayType::from($row['type']),
                $row['name'],
                Period::of($row['start'], $row['end']),
            );
        } catch (\ValueError|\InvalidArgumentException|\DateMalformedStringException $e) {
            throw new SchoolHolidayDataException('School holiday row has an invalid value.', 0, $e);
        }
    }
}
