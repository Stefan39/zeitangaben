<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests\Cli;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stejac\Zeitangaben\Cli\Application;

final class ApplicationTest extends TestCase
{
    /**
     * @param list<string> $args
     *
     * @return array{int, string, string}
     */
    private function invoke(array $args): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $code = (new Application())->run(['zeitangaben', ...$args], $stdout, $stderr);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }

    public function testHelpPrintsUsageAndExitsWithZero(): void
    {
        foreach (['--help', '-h'] as $flag) {
            [$code, $out, $err] = $this->invoke([$flag]);

            self::assertSame(0, $code);
            self::assertStringContainsString('Aufruf: zeitangaben', $out);
            self::assertStringContainsString('--state=NI', $out);
            self::assertSame('', $err);
        }
    }

    public function testPrintsResolvedPeriodInGerman(): void
    {
        [$code, $out] = $this->invoke(['in den Herbstferien', '--state=NI', '--date=2026-09-16']);

        self::assertSame(0, $code);
        self::assertStringContainsString('12.10.2026 – 24.10.2026', $out);
    }

    public function testStateOptionIsCaseInsensitive(): void
    {
        [$code, $out] = $this->invoke(['in den Herbstferien', '--state=ni', '--date=2026-09-16']);

        self::assertSame(0, $code);
        self::assertStringContainsString('12.10.2026 – 24.10.2026', $out);
    }

    public function testPrintsSingleDayWithoutRange(): void
    {
        [, $out] = $this->invoke(['morgen', '--date=2026-09-16']);

        self::assertSame("17.09.2026\n", $out);
    }

    public function testJoinsUnquotedWords(): void
    {
        [$code, $out] = $this->invoke(['in', 'den', 'Herbstferien', '--state=NI', '--date=2026-09-16']);

        self::assertSame(0, $code);
        self::assertStringContainsString('12.10.2026 – 24.10.2026', $out);
    }

    public function testJsonClarificationWithoutState(): void
    {
        [$code, $out] = $this->invoke(['in den Herbstferien', '--date=2026-09-16', '--json']);

        $data = json_decode($out, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(0, $code);
        self::assertIsArray($data);
        self::assertSame('clarification', $data['type']);
        self::assertSame('missing_state', $data['reason']);
    }

    public function testHumanClarificationListsCandidates(): void
    {
        [$code, $out] = $this->invoke(['über Ostern', '--state=NI', '--date=2026-09-16']);

        self::assertSame(0, $code);
        self::assertStringStartsWith('Rückfrage: ', $out);
        self::assertMatchesRegularExpression('~\n  - .+: \d{2}\.\d{2}\.\d{4} – \d{2}\.\d{2}\.\d{4}~', $out);
    }

    public function testHumanClarificationWithoutCandidates(): void
    {
        [$code, $out] = $this->invoke(['morgen']);

        self::assertSame(0, $code);
        self::assertStringStartsWith('Rückfrage: ', $out);
        self::assertStringNotContainsString('  - ', $out);
    }

    public function testNoExpression(): void
    {
        [$code, $out] = $this->invoke(['Hallo Welt', '--date=2026-09-16']);

        self::assertSame(0, $code);
        self::assertSame("Keine Zeitangabe gefunden.\n", $out);
    }

    public function testJsonNoExpression(): void
    {
        [$code, $out] = $this->invoke(['Hallo Welt', '--json']);

        self::assertSame(0, $code);
        self::assertSame('none', json_decode($out, true, flags: \JSON_THROW_ON_ERROR)['type']);
    }

    /**
     * @param list<string> $args
     */
    #[DataProvider('badArguments')]
    public function testBadArgumentsExitWithTwo(array $args, string $messagePart): void
    {
        [$code, $out, $err] = $this->invoke($args);

        self::assertSame(2, $code);
        self::assertSame('', $out);
        self::assertStringContainsString($messagePart, $err);
        self::assertStringContainsString('Aufruf:', $err);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function badArguments(): iterable
    {
        yield 'unknown state' => [['morgen', '--state=XX'], 'Bundesland'];
        yield 'word as date' => [['morgen', '--date=gestern'], 'Ungültiges Datum'];
        yield 'impossible date' => [['morgen', '--date=2026-02-31'], 'Ungültiges Datum'];
        yield 'wrong date format' => [['morgen', '--date=16.09.2026'], 'Ungültiges Datum'];
        yield 'no text' => [['--state=NI'], 'kein Text'];
        yield 'blank text' => [['  '], 'kein Text'];
        yield 'unknown option' => [['morgen', '--foo'], 'Unbekannte Option'];
    }
}
