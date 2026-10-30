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
use Stejac\Zeitangaben\Text\Normalizer;
use Stejac\Zeitangaben\Text\YearToken;

/**
 * "Herbstferien", "Sommerferien 2027" and the like, answered from the school holiday snapshot.
 *
 * @internal
 */
final class SchoolHolidayRule implements Rule
{
    /** The source calls these Fastnachtsferien; both words name the same break. */
    private const array NAME_SYNONYMS = ['faschingsferien' => 'fastnachtsferien'];

    public function __construct(private readonly SchoolHolidayLookup $lookup)
    {
    }

    public function pattern(): string
    {
        return YearToken::around('\b(?:(?<autumn>herbstferien)|(?<summer>sommerferien)|(?<easter>osterschulferien|osterferien)'
            .'|(?<pentecost>pfingstferien)|(?<christmas>weihnachtsferien)'
            .'|(?<winter>winterferien)|(?<named>halbjahresferien|fastnachtsferien|faschingsferien|fruehjahrsferien))\b');
    }

    public function evaluate(RuleMatch $match, Context $context): Result
    {
        $expression = $match->original;
        $state = $context->state;
        $year = $match->year;
        if (null === $state || (null === $year && null === $context->referenceDate)) {
            return null === $state
                ? Clarifications::missingState($expression)
                : Clarifications::missingReferenceDate($expression);
        }

        $type = $this->typeOf($match, $state);

        return $this->unavailable($expression, $state, $type, $year, $context)
            ?? $this->answer($match, $this->find($state, $type, $year, $context), $state);
    }

    private function answer(RuleMatch $match, ?SchoolHoliday $holiday, State $state): Result
    {
        if (null === $holiday) {
            return Clarifications::outOfData($match->original, $state);
        }

        return $this->isOtherName($match, $holiday)
            ? $this->underOtherName($match->original, $holiday)
            : new Resolved($holiday->period, $match->original, state: $state);
    }

    /**
     * Whether the user named a specific break, but the state's break of that season is called something else.
     */
    private function isOtherName(RuleMatch $match, SchoolHoliday $holiday): bool
    {
        if (!isset($match->groups['named'])) {
            return false;
        }
        $wanted = self::NAME_SYNONYMS[$match->groups['named']] ?? $match->groups['named'];

        return Normalizer::normalize($holiday->name) !== $wanted;
    }

    private function underOtherName(string $expression, SchoolHoliday $holiday): Clarification
    {
        return new Clarification(
            ClarificationReason::Ambiguous,
            sprintf('%s heißen diese Ferien „%s“. Meinen Sie bei „%s“ diese?', ucfirst($holiday->state->locative()), $holiday->name, $expression),
            $expression,
            [new Candidate($holiday->period, $holiday->name)],
        );
    }

    /**
     * Tells "the state has no such holidays" apart from "no data for that year".
     */
    private function unavailable(string $expression, State $state, SchoolHolidayType $type, ?int $year, Context $context): ?Clarification
    {
        if ($this->lookup->has($state, $type)) {
            return null;
        }
        if (SchoolHolidayType::Easter === $type && $this->lookup->has($state, SchoolHolidayType::Spring)) {
            return $this->noEaster($expression, $state, $year, $context);
        }

        return new Clarification(
            ClarificationReason::NotInState,
            sprintf('%s hat keine %s. Welchen Zeitraum meinen Sie?', $state->germanName(), ucfirst($expression)),
            $expression,
        );
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
            'fruehjahrsferien' === ($match->groups['named'] ?? '') && $this->lookup->has($state, SchoolHolidayType::Spring) => SchoolHolidayType::Spring,
            default => SchoolHolidayType::Winter,
        };
    }

    /**
     * Hamburg has no Osterferien, only Frühjahrsferien: offer those instead of inventing a date.
     */
    private function noEaster(string $expression, State $state, ?int $year, Context $context): Clarification
    {
        $spring = $this->find($state, SchoolHolidayType::Spring, $year, $context);
        if (null === $spring) {
            return Clarifications::outOfData($expression, $state);
        }

        return new Clarification(
            ClarificationReason::Ambiguous,
            sprintf('%s gibt es keine Osterferien, sondern Frühjahrsferien. Meinen Sie diese?', ucfirst($state->locative())),
            $expression,
            [new Candidate($spring->period, 'Frühjahrsferien')],
        );
    }
}
