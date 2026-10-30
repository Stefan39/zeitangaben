<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben;

use Stejac\Zeitangaben\Rule\CalendarWeekRule;
use Stejac\Zeitangaben\Rule\ExplicitDateRule;
use Stejac\Zeitangaben\Rule\FixedDayRule;
use Stejac\Zeitangaben\Rule\InvalidRulePatternException;
use Stejac\Zeitangaben\Rule\MonthRule;
use Stejac\Zeitangaben\Rule\RelativeDayRule;
use Stejac\Zeitangaben\Rule\Rule;
use Stejac\Zeitangaben\Rule\RuleMatch;
use Stejac\Zeitangaben\Rule\ThisWeekendRule;
use Stejac\Zeitangaben\School\BundledSchoolHolidays;
use Stejac\Zeitangaben\School\SchoolHolidayProvider;
use Stejac\Zeitangaben\Text\Normalizer;

final class Resolver
{
    /** @var list<Rule> most specific first */
    private readonly array $rules;

    public function __construct(public readonly SchoolHolidayProvider $schoolHolidays)
    {
        $this->rules = [
            new ExplicitDateRule(),
            new CalendarWeekRule(),
            new FixedDayRule(),
            new ThisWeekendRule(),
            new RelativeDayRule(),
            new MonthRule(),
        ];
    }

    public static function create(): self
    {
        return new self(new BundledSchoolHolidays());
    }

    public function resolve(string $text, Context $context = new Context()): Result
    {
        $normalized = $this->stripGreetings(Normalizer::normalize($text));
        $state = State::fromText($normalized);
        if (null !== $state) {
            $context = $context->withState($state);
        }

        $found = $this->findExpressions($normalized);
        if ([] === $found) {
            return new NoExpression();
        }
        if (count($found) > 1) {
            return $this->multipleExpressions($found);
        }

        [$rule, $match] = reset($found);

        return $rule->evaluate($match, $context);
    }

    private function stripGreetings(string $normalized): string
    {
        return preg_replace_callback(
            '~\bguten\s+(?:morgen|tag|abend)\b~',
            static fn (array $m): string => str_repeat(' ', strlen($m[0])),
            $normalized,
        ) ?? $normalized;
    }

    /**
     * Runs the rules in order; every match is blanked so later rules cannot claim it again.
     *
     * @return array<string, array{Rule, RuleMatch}> keyed by rule class and expression, in text order
     */
    private function findExpressions(string $text): array
    {
        $found = [];
        foreach ($this->rules as $rule) {
            foreach ($this->matchesOf($rule, $text) as $match) {
                $text = substr_replace($text, str_repeat(' ', strlen($match->expression)), $match->offset, strlen($match->expression));
                $found[$rule::class.'|'.$match->expression] ??= [$rule, $match];
            }
        }
        uasort($found, static fn (array $a, array $b): int => $a[1]->offset <=> $b[1]->offset);

        return $found;
    }

    /**
     * @return list<RuleMatch>
     */
    private function matchesOf(Rule $rule, string $text): array
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
            $matches[] = new RuleMatch($expression, $groups, $offset);
        }

        return $matches;
    }

    /**
     * @param array<string, array{Rule, RuleMatch}> $found
     */
    private function multipleExpressions(array $found): Clarification
    {
        $expressions = array_values(array_map(static fn (array $f): string => $f[1]->expression, $found));
        $quoted = implode(', ', array_map(static fn (string $e): string => sprintf('„%s“', $e), $expressions));

        return new Clarification(
            ClarificationReason::MultipleExpressions,
            sprintf('Ihre Angabe enthält mehrere Zeitangaben: %s. Welche davon meinen Sie?', $quoted),
            implode('; ', $expressions),
        );
    }
}
