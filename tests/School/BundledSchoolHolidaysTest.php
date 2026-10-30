<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests\School;

use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\School\BundledSchoolHolidays;
use Stejac\Zeitangaben\School\SchoolHoliday;
use Stejac\Zeitangaben\School\SchoolHolidayDataException;
use Stejac\Zeitangaben\School\SchoolHolidayType;
use Stejac\Zeitangaben\State;

final class BundledSchoolHolidaysTest extends TestCase
{
    public function testNiedersachsenAutumn2026(): void
    {
        $this->assertHasEntry(State::NI, SchoolHolidayType::Autumn, '2026-10-12', '2026-10-24');
    }

    public function testBayernPentecost2027(): void
    {
        $this->assertHasEntry(State::BY, SchoolHolidayType::Pentecost, '2027-05-18', '2027-05-28');
    }

    public function testNrwSummer2027(): void
    {
        $this->assertHasEntry(State::NW, SchoolHolidayType::Summer, '2027-07-19', '2027-08-31');
    }

    public function testHamburgSpring2027(): void
    {
        $this->assertHasEntry(State::HH, SchoolHolidayType::Spring, '2027-03-01', '2027-03-12');
    }

    public function testAutumn2030IsMissingForNiedersachsen(): void
    {
        $years = $this->startYears(State::NI, SchoolHolidayType::Autumn);

        self::assertNotContains(2030, $years);
    }

    public function testEveryStateHasAllMainTypesThrough2029(): void
    {
        $missing = [];
        foreach (State::cases() as $state) {
            $types = [
                State::HH === $state ? SchoolHolidayType::Spring : SchoolHolidayType::Easter,
                SchoolHolidayType::Summer,
                SchoolHolidayType::Autumn,
                SchoolHolidayType::Christmas,
            ];
            foreach ($types as $type) {
                $years = $this->startYears($state, $type);
                foreach ([2026, 2027, 2028, 2029] as $year) {
                    if (!in_array($year, $years, true)) {
                        $missing[] = sprintf('%s %s %d', $state->value, $type->value, $year);
                    }
                }
            }
        }

        self::assertSame([], $missing);
    }

    public function testResultsAreSortedByStart(): void
    {
        $entries = (new BundledSchoolHolidays())->forState(State::BY, SchoolHolidayType::Summer);

        $starts = array_map(static fn (SchoolHoliday $h): string => $h->period->start->format('Y-m-d'), $entries);
        $sorted = $starts;
        sort($sorted);
        self::assertSame($sorted, $starts);
        self::assertNotSame([], $starts);
    }

    public function testEntryCarriesStateTypeAndName(): void
    {
        $entry = (new BundledSchoolHolidays())->forState(State::NI, SchoolHolidayType::Autumn)[0];

        self::assertSame(State::NI, $entry->state);
        self::assertSame(SchoolHolidayType::Autumn, $entry->type);
        self::assertSame('Herbstferien', $entry->name);
    }

    public function testInvalidJsonThrowsDomainException(): void
    {
        $this->expectException(SchoolHolidayDataException::class);

        (new BundledSchoolHolidays($this->tempFile('{not json')))->forState(State::NI, SchoolHolidayType::Autumn);
    }

    public function testMalformedRowThrowsDomainException(): void
    {
        $this->expectException(SchoolHolidayDataException::class);

        $path = $this->tempFile('{"holidays":[{"state":"XX"}]}');
        (new BundledSchoolHolidays($path))->forState(State::NI, SchoolHolidayType::Autumn);
    }

    public function testMissingHolidaysKeyThrowsDomainException(): void
    {
        $this->expectException(SchoolHolidayDataException::class);

        (new BundledSchoolHolidays($this->tempFile('{"other":[]}')))->forState(State::NI, SchoolHolidayType::Autumn);
    }

    public function testMissingFileThrowsDomainException(): void
    {
        $this->expectException(SchoolHolidayDataException::class);

        (new BundledSchoolHolidays('/nonexistent/holidays.json'))->forState(State::NI, SchoolHolidayType::Autumn);
    }

    public function testConstructorDoesNotLoadFile(): void
    {
        $provider = new BundledSchoolHolidays('/nonexistent/holidays.json');

        self::assertInstanceOf(BundledSchoolHolidays::class, $provider);
    }

    private function assertHasEntry(State $state, SchoolHolidayType $type, string $start, string $end): void
    {
        $found = array_filter(
            (new BundledSchoolHolidays())->forState($state, $type),
            static fn (SchoolHoliday $h): bool => $h->period->start->format('Y-m-d') === $start
                && $h->period->end->format('Y-m-d') === $end,
        );

        self::assertNotSame([], $found, sprintf('%s %s %s..%s not found', $state->value, $type->value, $start, $end));
    }

    /**
     * @return list<int>
     */
    private function startYears(State $state, SchoolHolidayType $type): array
    {
        return array_map(
            static fn (SchoolHoliday $h): int => (int) $h->period->start->format('Y'),
            (new BundledSchoolHolidays())->forState($state, $type),
        );
    }

    private function tempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zeit');
        self::assertIsString($path);
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }
}
