<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\LongWeekend;
use Stejac\Zeitangaben\Calendar\Months;
use Stejac\Zeitangaben\Calendar\YearlyDate;
use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Holiday\PublicHolidayProvider;
use Stejac\Zeitangaben\Period;
use Stejac\Zeitangaben\Result;

/**
 * "verlängertes Wochenende", "am langen Wochenende im Oktober": lists the long weekends of the month;
 * two holidays that form the same weekend (25 and 26 December) count once.
 */
final class LongWeekendRule implements Rule
{
    public function __construct(private readonly PublicHolidayProvider $holidays)
    {
    }

    public function pattern(): string
    {
        return '\b(?:(?:am|ein|das)\s+)?(?:verlaengertes|verlaengerten|langes|langen)\s+wochenende\b'
            .'(?:\s+im\s+(?<month>'.Months::PATTERN.')\b)?';
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $expression = $match->expression;
        if (!isset($match->groups['month'])) {
            return new Clarification(
                ClarificationReason::Vague,
                sprintf('Welches Wochenende meinen Sie mit „%s“? Bitte nennen Sie das Datum.', $expression),
                $expression,
            );
        }

        $month = Months::number($match->groups['month']);

        return YearCandidates::clarify(
            $expression,
            $context,
            ClarificationReason::Vague,
            sprintf('Welches Wochenende meinen Sie mit „%s“? Bitte nennen Sie das Datum.', $expression),
            static fn (int $year): Period => new Period(YearlyDate::on($year, $month, 1), YearlyDate::on($year, $month, 1)->modify('last day of this month')),
            fn (int $year): array => $this->candidatesIn($year, $month, $context),
        );
    }

    /**
     * @return list<Candidate>
     */
    private function candidatesIn(int $year, int $month, Context $context): array
    {
        $candidates = [];
        foreach ($this->holidays->inMonth($year, $month, $context->state) as $holiday) {
            $weekend = LongWeekend::around($holiday);
            if (null !== $weekend && !isset($candidates[$weekend->start->format('Y-m-d')])) {
                $candidates[$weekend->start->format('Y-m-d')] = new Candidate($weekend, 'Langes Wochenende um den Feiertag am '.$holiday->format('d.m.Y'));
            }
        }

        return array_values($candidates);
    }
}
