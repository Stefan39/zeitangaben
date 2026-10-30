<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\YearlyDate;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Result;
use Stejac\Zeitangaben\Text\YearToken;

/**
 * "zwischen den Jahren", "über Weihnachten", "die Woche vor Weihnachten". Not the school Christmas holidays.
 */
final class ChristmasRule implements Rule
{
    public function pattern(): string
    {
        return YearToken::around('\b(?:(?<between>zwischen\s+den\s+jahren)|(?<week>woche\s+vor\s+weihnachten)'
            .'|(?<over>ueber\s+weihnachten)|(?<bare>(?<!vor\s)(?<!nach\s)weihnachten))\b');
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $expression = $match->original;
        $question = sprintf('Welchen Zeitraum meinen Sie mit „%s“? Es kommen zwei Zeiträume in Frage.', $expression);
        $candidates = match (true) {
            isset($match->groups['between']) => fn (int $y): array => $this->betweenTheYears($y),
            isset($match->groups['week']) => fn (int $y): array => $this->weekBefore($y),
            default => fn (int $y): array => $this->overChristmas($y),
        };

        return YearCandidates::clarify(
            $expression,
            $context,
            ClarificationReason::Ambiguous,
            $question,
            static fn (int $year): Period => new Period(YearlyDate::on($year, 12, 14), YearlyDate::on($year + 1, 1, 1)),
            $candidates,
            $match->year,
        );
    }

    /**
     * @return list<Candidate>
     */
    private function betweenTheYears(int $year): array
    {
        return [
            new Candidate(Period::of(sprintf('%d-12-27', $year), sprintf('%d-12-30', $year)), 'Zwischen den Jahren (27. bis 30. Dezember)'),
            new Candidate(Period::of(sprintf('%d-12-25', $year), sprintf('%d-01-01', $year + 1)), 'Weihnachten bis Neujahr'),
        ];
    }

    /**
     * @return list<Candidate>
     */
    private function weekBefore(int $year): array
    {
        $eve = YearlyDate::on($year, 12, 24);
        $monday = $eve->modify('monday this week')->modify('-7 days');

        return [
            new Candidate(new Period($monday, $monday->modify('+6 days')), 'Kalenderwoche vor Weihnachten (Montag bis Sonntag)'),
            new Candidate(new Period($monday->modify('+7 days'), $eve), 'Montag der Weihnachtswoche bis Heiligabend'),
        ];
    }

    /**
     * @return list<Candidate>
     */
    private function overChristmas(int $year): array
    {
        return [
            new Candidate(Period::of(sprintf('%d-12-24', $year), sprintf('%d-12-26', $year)), 'Heiligabend bis zum 2. Weihnachtstag'),
            new Candidate(Period::of(sprintf('%d-12-24', $year), sprintf('%d-01-01', $year + 1)), 'Heiligabend bis Neujahr'),
        ];
    }
}
