<?php

declare(strict_types=1);

/**
 * Refreshes data/school-holidays-de.json from the OpenHolidays API.
 *
 * Usage: php tools/update-school-holidays.php
 */

const API_URL = 'https://openholidaysapi.org/SchoolHolidays';
const FROM = '2026-01-01';
const TO = '2031-12-31';
const WINDOW_DAYS = 1095;

const TYPE_BY_NAME = [
    'Winterferien' => 'winter',
    'Halbjahresferien' => 'winter',
    'Halbjahrespause' => 'winter',
    'Fastnachtsferien' => 'winter',
    'Frühjahrsferien' => 'winter',
    'Osterferien' => 'easter',
    'Pfingstferien' => 'pentecost',
    'Sommerferien' => 'summer',
    'Herbstferien' => 'autumn',
    'Weihnachtsferien' => 'christmas',
];

/**
 * @return list<array{from: string, to: string}>
 */
function windows(): array
{
    $windows = [];
    $cursor = new DateTimeImmutable(FROM);
    $limit = new DateTimeImmutable(TO);
    while ($cursor <= $limit) {
        $end = min($cursor->modify('+'.(WINDOW_DAYS - 1).' days'), $limit);
        $windows[] = ['from' => $cursor->format('Y-m-d'), 'to' => $end->format('Y-m-d')];
        $cursor = $end->modify('+1 day');
    }

    return $windows;
}

/**
 * @return list<array<string, mixed>>
 */
function fetch(string $from, string $to): array
{
    $url = API_URL.'?'.http_build_query([
        'countryIsoCode' => 'DE',
        'languageIsoCode' => 'DE',
        'validFrom' => $from,
        'validTo' => $to,
    ]);
    $context = stream_context_create(['http' => ['header' => "accept: application/json\r\n", 'timeout' => 30]]);
    $body = file_get_contents($url, false, $context);
    if (false === $body) {
        fwrite(STDERR, "Request failed: $url\n");
        exit(1);
    }

    $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Unexpected response shape: $url\n");
        exit(1);
    }

    return array_values($decoded);
}

/**
 * Hamburg has no Easter holidays; its March break is kept apart as "spring".
 */
function typeFor(string $name, string $state): string
{
    if ('Frühjahrsferien' === $name && 'HH' === $state) {
        return 'spring';
    }

    return TYPE_BY_NAME[$name];
}

/**
 * @param array<string, mixed> $entry
 */
function germanName(array $entry): string
{
    foreach ((array) ($entry['name'] ?? []) as $name) {
        if (is_array($name) && 'DE' === ($name['language'] ?? null)) {
            return (string) ($name['text'] ?? '');
        }
    }

    return '';
}

/**
 * @param array<string, mixed> $entry
 *
 * @return list<string>
 */
function states(array $entry): array
{
    $states = [];
    foreach ((array) ($entry['subdivisions'] ?? []) as $subdivision) {
        if (is_array($subdivision) && is_string($subdivision['code'] ?? null)) {
            $states[] = substr($subdivision['code'], 3);
        }
    }

    return $states;
}

$byId = [];
foreach (windows() as $window) {
    foreach (fetch($window['from'], $window['to']) as $entry) {
        $byId[(string) $entry['id']] = $entry;
    }
}

/**
 * @param array<string, mixed> $entry
 */
function isExcluded(array $entry): bool
{
    $groups = [];
    foreach ((array) ($entry['groups'] ?? []) as $group) {
        if (is_array($group) && is_string($group['code'] ?? null)) {
            $groups[] = $group['code'];
        }
    }
    $vocationalOnly = in_array('DE-MV-BBS', $groups, true) && !in_array('DE-MV-ABS', $groups, true);

    return $vocationalOnly || [] !== ($entry['comment'] ?? []);
}

/**
 * @param array<string, mixed> $entry
 */
function commentText(array $entry): string
{
    foreach ((array) ($entry['comment'] ?? []) as $comment) {
        if (is_array($comment) && 'DE' === ($comment['language'] ?? null)) {
            return (string) ($comment['text'] ?? '');
        }
    }

    return '';
}

$rows = [];
$droppedComments = [];
foreach ($byId as $entry) {
    $name = germanName($entry);
    if (!isset(TYPE_BY_NAME[$name])) {
        continue;
    }
    if (isExcluded($entry)) {
        if ('' !== commentText($entry)) {
            $droppedComments[commentText($entry)] = true;
        }
        continue;
    }
    foreach (states($entry) as $state) {
        $type = typeFor($name, $state);
        $rows[] = [
            'state' => $state,
            'type' => $type,
            'name' => $name,
            'start' => (string) $entry['startDate'],
            'end' => (string) $entry['endDate'],
        ];
    }
}

usort($rows, static fn (array $a, array $b): int => [$a['state'], $a['start'], $a['type']] <=> [$b['state'], $b['start'], $b['type']]);

$document = [
    'source' => 'OpenHolidays API (https://www.openholidaysapi.org)',
    'license' => 'ODbL-1.0',
    'retrieved' => date('Y-m-d'),
    'holidays' => $rows,
];

file_put_contents(
    __DIR__.'/../data/school-holidays-de.json',
    json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
);
foreach (array_keys($droppedComments) as $comment) {
    fwrite(STDOUT, "Dropped by comment: $comment\n");
}
fwrite(STDOUT, count($rows)." rows written\n");
