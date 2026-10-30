<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests\Corpus;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\NoExpression;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Resolver;
use Stejac\Zeitangaben\State;

final class CorpusTest extends TestCase
{
    private const array ENABLED_GROUPS = ['explicit', 'school', 'feast'];

    private const array KNOWN_EXPECT_KEYS = ['type', 'start', 'end', 'note_contains', 'no_notes', 'reason', 'candidates'];

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function entries(): iterable
    {
        $json = file_get_contents(__DIR__.'/corpus.json');
        self::assertIsString($json);
        $entries = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($entries);

        foreach ($entries as $entry) {
            self::assertIsArray($entry);
            if (!in_array($entry['group'], self::ENABLED_GROUPS, true)) {
                continue;
            }
            yield (string) $entry['id'] => [$entry];
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('entries')]
    public function testCorpusEntry(array $entry): void
    {
        /** @var array<string, mixed> $expect */
        $expect = $entry['expect'];
        $unknown = array_diff(array_keys($expect), self::KNOWN_EXPECT_KEYS);
        self::assertSame([], array_values($unknown), 'Unknown expect keys');

        $result = Resolver::create()->resolve((string) $entry['text'], $this->contextFor($entry));

        match ($expect['type']) {
            'resolved' => $this->assertResolved($result, $expect),
            'clarification' => $this->assertClarification($result, $expect),
            'none' => self::assertInstanceOf(NoExpression::class, $result),
            default => self::fail('Unknown expected type'),
        };
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function contextFor(array $entry): Context
    {
        $reference = isset($entry['reference'])
            ? new \DateTimeImmutable((string) $entry['reference'], new \DateTimeZone('UTC'))
            : null;
        $state = isset($entry['state']) ? State::from((string) $entry['state']) : null;

        return new Context($reference, $state);
    }

    /**
     * @param array<string, mixed> $expect
     */
    private function assertResolved(object $result, array $expect): void
    {
        self::assertInstanceOf(Resolved::class, $result);
        self::assertSame($expect['start'], $result->period->start->format('Y-m-d'));
        self::assertSame($expect['end'], $result->period->end->format('Y-m-d'));
        if (isset($expect['note_contains'])) {
            self::assertStringContainsString(
                (string) $expect['note_contains'],
                implode("\n", $result->notes),
            );
        }
        if (true === ($expect['no_notes'] ?? false)) {
            self::assertSame([], $result->notes);
        }
    }

    /**
     * @param array<string, mixed> $expect
     */
    private function assertClarification(object $result, array $expect): void
    {
        self::assertInstanceOf(Clarification::class, $result);
        self::assertSame($expect['reason'], $result->reason->value);
        self::assertNotSame('', trim($result->question));

        $actual = array_map(
            static fn ($c): array => [$c->period->start->format('Y-m-d'), $c->period->end->format('Y-m-d')],
            $result->candidates,
        );
        self::assertSame($expect['candidates'] ?? [], $actual);
        foreach ($result->candidates as $candidate) {
            self::assertNotSame('', trim($candidate->label));
        }
    }
}
