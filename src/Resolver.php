<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben;

use Stejac\Zeitangaben\Holiday\PublicHolidayProvider;
use Stejac\Zeitangaben\Holiday\YasumiPublicHolidays;
use Stejac\Zeitangaben\Rule\BridgeDayRule;
use Stejac\Zeitangaben\Rule\CalendarWeekRule;
use Stejac\Zeitangaben\Rule\ChristmasRule;
use Stejac\Zeitangaben\Rule\Clarifications;
use Stejac\Zeitangaben\Rule\ContextGuard;
use Stejac\Zeitangaben\Rule\EasterRule;
use Stejac\Zeitangaben\Rule\ExplicitDateRule;
use Stejac\Zeitangaben\Rule\FixedDayRule;
use Stejac\Zeitangaben\Rule\InvalidRulePatternException;
use Stejac\Zeitangaben\Rule\LastHolidayWeekRule;
use Stejac\Zeitangaben\Rule\LongWeekendRule;
use Stejac\Zeitangaben\Rule\MonthPartRule;
use Stejac\Zeitangaben\Rule\MonthRule;
use Stejac\Zeitangaben\Rule\PentecostRule;
use Stejac\Zeitangaben\Rule\RelativeDayRule;
use Stejac\Zeitangaben\Rule\RelativeToFeastRule;
use Stejac\Zeitangaben\Rule\RelativeWeekNameRule;
use Stejac\Zeitangaben\Rule\RelativeWeekRule;
use Stejac\Zeitangaben\Rule\Rule;
use Stejac\Zeitangaben\Rule\RuleMatch;
use Stejac\Zeitangaben\Rule\SchoolHolidayLookup;
use Stejac\Zeitangaben\Rule\SchoolHolidayRule;
use Stejac\Zeitangaben\Rule\SeasonRule;
use Stejac\Zeitangaben\Rule\SeasonTermRule;
use Stejac\Zeitangaben\Rule\SingleFeastRule;
use Stejac\Zeitangaben\Rule\ThisWeekendRule;
use Stejac\Zeitangaben\Rule\TurnOfYearRule;
use Stejac\Zeitangaben\Rule\WeekdayRule;
use Stejac\Zeitangaben\Rule\WeekendRule;
use Stejac\Zeitangaben\School\BundledSchoolHolidays;
use Stejac\Zeitangaben\School\SchoolHolidayProvider;
use Stejac\Zeitangaben\Text\NormalizedText;
use Stejac\Zeitangaben\Text\Normalizer;
use Stejac\Zeitangaben\Text\YearToken;

final class Resolver
{
    private const string GREETINGS = '~\bguten\s+(?:morgen|tag|abend)\b'
        .'|(?:^\s*|(?<=[.!?,;]\s))morgen\s+zusammen\b(?=\s*(?:[,.!?;:]|$))'
        .'|\b(?:frohe|schoene|gesegnete)\s+(?:ostern|pfingsten|weihnachten)\b'
        .'|\bschoenes\s+wochenende\b~';

    /** @var list<Rule> most specific first */
    private readonly array $rules;

    public function __construct(
        public readonly SchoolHolidayProvider $schoolHolidays,
        public readonly PublicHolidayProvider $publicHolidays,
    ) {
        $lookup = new SchoolHolidayLookup($schoolHolidays);
        $this->rules = [
            new ExplicitDateRule(),
            new CalendarWeekRule(),
            new LastHolidayWeekRule(),
            new SeasonTermRule(),
            new ChristmasRule(),
            new RelativeToFeastRule(),
            new SchoolHolidayRule($lookup),
            new BridgeDayRule(),
            new SingleFeastRule($publicHolidays),
            new EasterRule($lookup),
            new PentecostRule($lookup),
            new FixedDayRule(),
            new TurnOfYearRule(),
            new ThisWeekendRule(),
            new LongWeekendRule($publicHolidays),
            new WeekendRule(),
            new MonthPartRule(),
            new SeasonRule(),
            new RelativeWeekNameRule(),
            new RelativeWeekRule(),
            new RelativeDayRule(),
            new MonthRule(),
            new WeekdayRule(),
        ];
    }

    public static function create(): self
    {
        return new self(new BundledSchoolHolidays(), new YasumiPublicHolidays());
    }

    public function resolve(string $text, Context $context = new Context()): Result
    {
        $source = Normalizer::withOffsets($text);
        $normalized = YearToken::mask($source, $this->stripGreetings($source->text));
        $states = State::allFromText($normalized);
        $context = self::contextWith($states, $context);

        $guard = new ContextGuard($source, $normalized);
        [$found, $unclaimed] = $this->findExpressions($normalized, $source, $guard);
        if ([] === $found) {
            return new NoExpression();
        }
        if (count($found) > 1) {
            return Clarifications::multipleExpressions(array_values(array_map(static fn (array $f): string => $f[1]->original, $found)));
        }

        [$rule, $match] = reset($found);

        return $guard->clarificationFor($match, $unclaimed) ?? self::namingStates($this->evaluate($rule, $match, $context), $states);
    }

    /**
     * A state named in the text wins over the context; two named states leave it open.
     *
     * @param list<State> $states
     */
    private static function contextWith(array $states, Context $context): Context
    {
        return match (count($states)) {
            0 => $context,
            1 => $context->withState($states[0]),
            default => new Context($context->referenceDate),
        };
    }

    /**
     * @param list<State> $states
     */
    private static function namingStates(Result $result, array $states): Result
    {
        return count($states) > 1 && $result instanceof Clarification && ClarificationReason::MissingState === $result->reason
            ? Clarifications::severalStates($result->expression, $states)
            : $result;
    }

    private function evaluate(Rule $rule, RuleMatch $match, Context $context): Result
    {
        if (null === $context->referenceDate && YearToken::needsReference($match->groups)) {
            return Clarifications::missingReferenceDate($match->original);
        }

        return $rule->evaluate($match->withYear(YearToken::of($match->groups, $context->referenceDate)), $context);
    }

    private function stripGreetings(string $normalized): string
    {
        return preg_replace_callback(
            self::GREETINGS,
            static fn (array $m): string => str_repeat(' ', strlen($m[0])),
            $normalized,
        ) ?? $normalized;
    }

    /**
     * Runs the rules in order; every match is blanked so later rules cannot claim it again.
     * A match the guard discards is blanked too: that text is a name, not a time expression.
     *
     * @return array{array<string, array{Rule, RuleMatch}>, string} the matches keyed by rule class and expression
     *                                                              in text order, and the text left unclaimed
     */
    private function findExpressions(string $text, NormalizedText $source, ContextGuard $guard): array
    {
        $found = [];
        foreach ($this->rules as $rule) {
            foreach ($this->matchesOf($rule, $text, $source) as $match) {
                $text = substr_replace($text, str_repeat(' ', strlen($match->expression)), $match->offset, strlen($match->expression));
                if (!$guard->discards($match)) {
                    $found[$rule::class.'|'.$match->expression] ??= [$rule, $match];
                }
            }
        }
        uasort($found, static fn (array $a, array $b): int => $a[1]->offset <=> $b[1]->offset);

        return [$found, $text];
    }

    /**
     * @return list<RuleMatch>
     */
    private function matchesOf(Rule $rule, string $text, NormalizedText $source): array
    {
        if (false === preg_match_all('~'.$rule->pattern().'~', $text, $sets, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            throw new InvalidRulePatternException(sprintf('Pattern of %s is not a valid regular expression.', $rule::class));
        }

        $matches = [];
        foreach ($sets as $set) {
            $groups = [];
            foreach ($set as $key => [$value]) {
                if ('' !== $value) {
                    $groups[$key] = $value;
                }
            }
            [$expression, $offset] = $set[0];
            $matches[] = new RuleMatch($expression, $source->originalOf($offset, strlen($expression)), $groups, $offset);
        }

        return $matches;
    }
}
