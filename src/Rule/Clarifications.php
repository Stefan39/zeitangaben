<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;
use Stejac\Zeitangaben\State;

/**
 * Builds the clarifications shared by several rules.
 */
final class Clarifications
{
    public static function missingReferenceDate(string $expression): Clarification
    {
        return new Clarification(
            ClarificationReason::MissingReferenceDate,
            sprintf('Auf welches Datum bezieht sich „%s“?', $expression),
            $expression,
        );
    }

    public static function invalidDate(string $expression): Clarification
    {
        return new Clarification(
            ClarificationReason::InvalidDate,
            sprintf('„%s“ ist kein gültiges Datum. Welches Datum meinen Sie?', $expression),
            $expression,
        );
    }

    public static function invalidWeek(string $expression): Clarification
    {
        return new Clarification(
            ClarificationReason::InvalidDate,
            sprintf('„%s“ ist keine gültige Kalenderwoche. Welche Kalenderwoche meinen Sie?', $expression),
            $expression,
        );
    }

    /**
     * @param list<string> $expressions as the user wrote them
     */
    public static function multipleExpressions(array $expressions): Clarification
    {
        $quoted = implode(', ', array_map(static fn (string $e): string => sprintf('„%s“', $e), $expressions));

        return new Clarification(
            ClarificationReason::MultipleExpressions,
            sprintf('Ihre Angabe enthält mehrere Zeitangaben: %s. Welche davon meinen Sie?', $quoted),
            implode('; ', $expressions),
        );
    }

    public static function vagueSpan(string $expression): Clarification
    {
        return new Clarification(
            ClarificationReason::Vague,
            sprintf('„%s“ ist kein genauer Zeitraum. Von welchem bis zu welchem Tag meinen Sie?', $expression),
            $expression,
        );
    }

    public static function missingState(string $expression): Clarification
    {
        return new Clarification(
            ClarificationReason::MissingState,
            sprintf('Für welches Bundesland gilt „%s“?', $expression),
            $expression,
        );
    }

    public static function outOfData(string $expression, State $state): Clarification
    {
        return new Clarification(
            ClarificationReason::OutOfData,
            sprintf('Zu „%s“ liegen für %s keine Ferientermine im Datenbestand vor. Welchen Zeitraum meinen Sie?', $expression, $state->germanName()),
            $expression,
        );
    }
}
