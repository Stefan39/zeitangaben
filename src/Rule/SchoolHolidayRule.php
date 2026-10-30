<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Candidate;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\Context;
use Stejac\Zeitangaben\Resolved;
use Stejac\Zeitangaben\Result;
use Stejac\Zeitangaben\School\SchoolHoliday;
use Stejac\Zeitangaben\School\SchoolHolidayType;
use Stejac\Zeitangaben\State;

/**
 * "Herbstferien", "Sommerferien 2027" and the like, answered from the school holiday snapshot.
 */
final class SchoolHolidayRule implements Rule
{
    public function __construct(private readonly SchoolHolidayLookup $lookup)
    {
    }

    public function pattern(): string
    {
        return '\b(?:(?<autumn>herbstferien)|(?<summer>sommerferien)|(?<easter>osterschulferien|osterferien)'
            .'|(?<pentecost>pfingstferien)|(?<christmas>weihnachtsferien)'
            .'|(?<winter>winterferien|halbjahresferien|fastnachtsferien|faschingsferien)|(?<spring>fruehjahrsferien))\b'
            .FeastPeriod::YEAR_PATTERN;
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $expression = $match->expression;
        $state = $context->state;
        if (null === $state) {
            return Clarifications::missingState($expression);
        }
        $year = FeastPeriod::yearOf($match);
        if (null === $year && null === $context->referenceDate) {
            return Clarifications::missingReferenceDate($expression);
        }

        $type = $this->typeOf($match, $state);
        if (SchoolHolidayType::Easter === $type && !$this->lookup->has($state, $type) && $this->lookup->has($state, SchoolHolidayType::Spring)) {
            return $this->noEaster($expression, $state, $year, $context);
        }

        $holiday = $this->find($state, $type, $year, $context);

        return null === $holiday
            ? Clarifications::outOfData($expression, $state)
            : new Resolved($holiday->period, $expression);
    }

    private function find(State $state, SchoolHolidayType $type, ?int $year, Context $context): ?SchoolHoliday
    {
        return null === $year
            ? $this->lookup->upcoming($state, $type, $context)
            : $this->lookup->startingIn($state, $type, $year);
    }

    private function typeOf(RuleMatch $match, State $state): SchoolHolidayType
    {
        return match (true) {
            isset($match->groups['autumn']) => SchoolHolidayType::Autumn,
            isset($match->groups['summer']) => SchoolHolidayType::Summer,
            isset($match->groups['easter']) => SchoolHolidayType::Easter,
            isset($match->groups['pentecost']) => SchoolHolidayType::Pentecost,
            isset($match->groups['christmas']) => SchoolHolidayType::Christmas,
            isset($match->groups['spring']) && $this->lookup->has($state, SchoolHolidayType::Spring) => SchoolHolidayType::Spring,
            default => SchoolHolidayType::Winter,
        };
    }

    /**
     * Hamburg has no Osterferien, only Frühjahrsferien: offer those instead of inventing a date.
     */
    private function noEaster(string $expression, State $state, ?int $year, Context $context): Result
    {
        $spring = $this->find($state, SchoolHolidayType::Spring, $year, $context);
        if (null === $spring) {
            return Clarifications::outOfData($expression, $state);
        }

        return new Clarification(
            ClarificationReason::Ambiguous,
            sprintf('In %s gibt es keine Osterferien, sondern Frühjahrsferien. Meinen Sie diese?', $state->germanName()),
            $expression,
            [new Candidate($spring->period, 'Frühjahrsferien')],
        );
    }
}
