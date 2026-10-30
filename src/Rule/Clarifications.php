<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\ClarificationReason;

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
}
