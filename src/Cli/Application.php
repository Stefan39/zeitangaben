<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Cli;

use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\NoExpression;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Resolver;
use Stejac\Zeitangaben\Result;
use Stejac\Zeitangaben\State;

/**
 * Thin command line layer: parses arguments, calls the resolver, prints the result.
 * It contains no date logic of its own.
 */
final class Application
{
    private const string USAGE = 'Aufruf: zeitangaben "<text>" [--state=NI] [--date=JJJJ-MM-TT] [--json]';

    /**
     * @param list<string> $argv   the raw argument vector, program name first
     * @param resource     $stdout
     * @param resource     $stderr
     */
    public function run(array $argv, $stdout, $stderr): int
    {
        try {
            $input = $this->parse(array_slice($argv, 1));
        } catch (InvalidArgumentException $e) {
            fwrite($stderr, $e->getMessage()."\n".self::USAGE."\n");

            return 2;
        }

        $result = Resolver::create()->resolve($input->text, new Context($input->referenceDate, $input->state));
        fwrite($stdout, ($input->json ? $this->json($result) : $this->human($result))."\n");

        return 0;
    }

    /**
     * @param list<string> $args
     */
    private function parse(array $args): Input
    {
        $words = [];
        $state = null;
        $date = null;
        $json = false;
        foreach ($args as $arg) {
            if ('--json' === $arg) {
                $json = true;
            } elseif (str_starts_with($arg, '--state=')) {
                $state = $this->parseState(substr($arg, 8));
            } elseif (str_starts_with($arg, '--date=')) {
                $date = $this->parseDate(substr($arg, 7));
            } elseif (str_starts_with($arg, '--')) {
                throw new InvalidArgumentException(sprintf('Unbekannte Option: %s', $arg));
            } else {
                $words[] = $arg;
            }
        }

        $text = trim(implode(' ', $words));
        if ('' === $text) {
            throw new InvalidArgumentException('Es wurde kein Text übergeben.');
        }

        return new Input($text, $state, $date, $json);
    }

    private function parseState(string $value): State
    {
        return State::tryFrom(strtoupper($value))
            ?? throw new InvalidArgumentException(sprintf('Unbekanntes Bundesland: „%s“. Erlaubt ist ein Kürzel wie NI, BY oder NW.', $value));
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(sprintf('Ungültiges Datum: „%s“. Erwartet wird JJJJ-MM-TT, zum Beispiel 2026-09-16.', $value));
        }

        return $date;
    }

    private function json(Result $result): string
    {
        return json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }

    private function human(Result $result): string
    {
        if ($result instanceof Resolved) {
            return implode("\n", [$this->range($result->period), ...array_map(static fn (string $n): string => 'Hinweis: '.$n, $result->notes)]);
        }
        if ($result instanceof Clarification) {
            $lines = ['Rückfrage: '.$result->question];
            foreach ($result->candidates as $candidate) {
                $lines[] = sprintf('  - %s: %s', $candidate->label, $this->range($candidate->period));
            }

            return implode("\n", $lines);
        }

        return $result instanceof NoExpression ? 'Keine Zeitangabe gefunden.' : '';
    }

    private function range(Period $period): string
    {
        $start = $period->start->format('d.m.Y');
        $end = $period->end->format('d.m.Y');

        return $start === $end ? $start : $start.' – '.$end;
    }
}
