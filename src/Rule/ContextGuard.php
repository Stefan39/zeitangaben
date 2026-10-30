<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\Calendar\Months;
use Stejac\Zeitangaben\Clarification;
use Stejac\Zeitangaben\State;
use Stejac\Zeitangaben\Text\NormalizedText;
use Stejac\Zeitangaben\Text\YearToken;

/**
 * Looks at the text around a claimed span: a word that looks like a time expression may be part of a name,
 * a web address or a longer phrase the rules did not understand. All word lists live here.
 *
 * @internal
 */
final readonly class ContextGuard
{
    /** The word before makes a span that starts with a letter a name or a noun phrase: "Frau Morgen", "gestern Morgen". */
    private const array NAME_WORDS = [
        'herr', 'frau', 'dr', 'prof', 'familie', 'fam', 'firma', 'hotel', 'pension',
        'gestern', 'jeden', 'jede', 'fruehen', 'spaeten', 'schoenen',
    ];

    /** Only "morgen" turns into a noun after this word: "diesen Morgen", but "diesen Montag" still names a day. */
    private const string MORNING_WORD = 'diesen';

    /** The word before makes a span that starts with a digit a reference number: "Paragraph 3.4.". */
    private const array NUMBER_WORDS = [
        'paragraph', 'paragraf', 'version', 'nr', 'nummer', 'seite', 'kapitel', 'ip',
        'abschnitt', 'artikel', 'absatz', 'tabelle', 'abbildung', 'punkt',
    ];

    /** These words may stand before a span with an abbreviation dot. */
    private const array ABBREVIATIONS = ['dr', 'prof', 'fam', 'nr'];

    /** Span openers that carry the meaning themselves, so "Hotel im Juli" is a hotel in July, not a name. */
    private const array PREPOSITIONS = ['im', 'am', 'ab', 'in', 'vom', 'von', 'ueber', 'uebers', 'zwischen', 'ein', 'das', 'nach', 'vor'];

    /** Skipped when counting the words before a span. */
    private const array FILLERS = ['den', 'dem', 'der', 'die', 'das', 'des', 'zum', 'zur', 'im', 'in', 'am'];

    /** The span is only part of what is meant, so it must not be answered as if it stood alone. */
    private const array QUALIFIERS = [
        'nach', 'vor', 'ab', 'bis', 'seit', 'nicht', 'kein', 'keine', 'keinen', 'ausser', 'ohne', 'statt', 'anstatt',
        'anfang', 'mitte', 'ende', 'erste', 'ersten', 'erstes', 'erster', 'zweite', 'zweiten', 'dritte',
        'letzte', 'letzten', 'letztes', 'jedes',
        'um', 'gegen', 'ca', 'circa', 'zirka', 'etwa', 'ungefaehr', 'rund', 'spaetestens', 'fruehestens',
        'tag', 'tage', 'nacht', 'naechte', 'woche', 'wochen', 'monat', 'monate', 'stunden', 'wochenende', 'brueckentag',
    ];

    /** Counts only directly before the span: "drei im Mai" is vague, "4 Personen im Juli" is not. */
    private const string NUMBER = '~^(?:\d+|zwei|drei|vier|fuenf|sechs|sieben|acht|neun|zehn|elf|zwoelf)$~';

    /** Not a qualifier even with "vor" before it: "vor allem im Juli". */
    private const string IDIOM_END = 'allem';

    private const string QUALIFIER_WINDOW_STOP = '~[,;:!?()]|(?<!\d)(?<!\bca)\.~';

    private const int QUALIFIER_WINDOW = 3;

    private const string CONNECTOR = '(?:bis|oder|und|/|-|–|,)';

    private const string TOKEN = '(?<token>(?:'.Months::PATTERN.')\b|(?<![\d.])\d{1,2}\.(?!\d)|(?<![\d.])\d++(?![:.,]\d))';

    private const string CONNECTOR_FILLER = '(?:(?:im|am|den|dem|zum)\s+)?';

    /**
     * @param string $text the normalised text with greetings and quantities blanked, before any rule claimed a span
     */
    public function __construct(private NormalizedText $source, private string $text)
    {
    }

    /**
     * Whether the span is no time expression at all because of what stands right next to it.
     */
    public function discards(RuleMatch $match): bool
    {
        return $this->isTechnical($match) || $this->followsNameWord($match);
    }

    /**
     * What the span means is not what it says alone: another token is joined to it, or a qualifier stands before it.
     *
     * @param string $unclaimed the text after all rules ran, with every claimed span blanked
     */
    public function clarificationFor(RuleMatch $match, string $unclaimed): ?Clarification
    {
        $joined = $this->joinedExpressions($match, $unclaimed);
        if ([] !== $joined) {
            return Clarifications::multipleExpressions($joined);
        }

        $start = $this->qualifierStart($match);

        return null === $start
            ? null
            : Clarifications::vagueSpan($this->source->originalOf($start, $match->end() - $start));
    }

    private function isTechnical(RuleMatch $match): bool
    {
        $before = substr($this->text, 0, $match->offset);
        $after = substr($this->text, $match->end());
        $joinedBefore = 1 === preg_match('~\w[@._-]$~', $before)
            && 1 !== preg_match('~(?:\b(?:'.Months::PATTERN.')|\d)-$~', $before);
        $joinedAfter = 1 === preg_match('~^[@._-]\w~', $after)
            && 1 !== preg_match('~^-(?:(?:'.Months::PATTERN.')\b|\d)~', $after);

        return $joinedBefore || $joinedAfter;
    }

    private function followsNameWord(RuleMatch $match): bool
    {
        $before = substr($this->text, 0, $match->offset);
        if (1 === preg_match('~§\s*$~', $before)) {
            return true;
        }
        if (1 !== preg_match('~(?<![\w.-])([a-z]+)(\.?)\s+$~', $before, $word)) {
            return false;
        }

        return ('' === $word[2] || in_array($word[1], self::ABBREVIATIONS, true))
            && $this->nameWordApplies($word[1], $match);
    }

    private function nameWordApplies(string $word, RuleMatch $match): bool
    {
        if (ctype_digit($match->expression[0])) {
            return in_array($word, self::NUMBER_WORDS, true);
        }
        $firstWord = strtok($match->expression, ' ');

        return !in_array($firstWord, self::PREPOSITIONS, true)
            && (in_array($word, self::NAME_WORDS, true) || (self::MORNING_WORD === $word && 'morgen' === $match->expression));
    }

    /**
     * @return list<string> the span together with an unclaimed token joined to it, in text order
     */
    private function joinedExpressions(RuleMatch $match, string $unclaimed): array
    {
        $before = $this->tokenBefore(substr($unclaimed, 0, $match->offset));
        $after = $this->tokenAfter($match, $unclaimed);
        if (null === $before && null === $after) {
            return [];
        }

        return array_values(array_filter([$before, $match->original, $after], static fn (?string $part): bool => null !== $part));
    }

    private function tokenBefore(string $before): ?string
    {
        $pattern = '~'.self::TOKEN.'\s*(?<connector>'.self::CONNECTOR.')\s*'.self::CONNECTOR_FILLER.'$~';
        if (1 !== preg_match($pattern, $before, $found, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $isBareNumber = ctype_digit($found['token'][0]);
        $isComma = ',' === $found['connector'][0];

        return $isBareNumber && $isComma
            ? null
            : $this->source->originalOf($found['token'][1], strlen($found['token'][0]));
    }

    private function tokenAfter(RuleMatch $match, string $unclaimed): ?string
    {
        $pattern = '~^\s*'.self::CONNECTOR.'\s*'.self::CONNECTOR_FILLER.self::TOKEN.'~';
        if (1 !== preg_match($pattern, substr($unclaimed, $match->end()), $found, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $offset = $match->end() + $found['token'][1];
        $length = strlen($found['token'][0]);

        return ctype_digit($found['token'][0][0]) && YearToken::isFollowedByNoun($this->source, $this->text, $offset + $length)
            ? null
            : $this->source->originalOf($offset, $length);
    }

    /**
     * The offset of the outermost qualifier in the words before the span, or null.
     */
    private function qualifierStart(RuleMatch $match): ?int
    {
        $start = null;
        $counted = 0;
        foreach ($this->wordsBefore($match->offset) as [$word, $offset]) {
            if (in_array($word, self::FILLERS, true)) {
                continue;
            }
            if (self::QUALIFIER_WINDOW === $counted || $this->blocksQualifier($word, $offset)) {
                break;
            }
            if (in_array($word, self::QUALIFIERS, true) || (0 === $counted && 1 === preg_match(self::NUMBER, $word))) {
                $start = $offset;
            }
            ++$counted;
        }

        return $start;
    }

    /**
     * @return list<array{string, int}> the words of the current clause before the offset, nearest first
     */
    private function wordsBefore(int $offset): array
    {
        $text = preg_replace_callback(
            '~'.State::mentionPattern().'~',
            static fn (array $mention): string => str_repeat(',', strlen($mention[0])),
            substr($this->text, 0, $offset),
        ) ?? '';

        $stops = [];
        preg_match_all(self::QUALIFIER_WINDOW_STOP, $text, $stops, PREG_OFFSET_CAPTURE);
        $last = end($stops[0]);
        $words = [];
        preg_match_all('~[a-z0-9]+~', $text, $words, PREG_OFFSET_CAPTURE, false === $last ? 0 : $last[1] + 1);

        return array_reverse($words[0]);
    }

    /**
     * A capitalised noun between qualifier and span ends the search: "nach Usedom im Juli", "Tag der Anreise ist morgen".
     */
    private function blocksQualifier(string $word, int $offset): bool
    {
        if (in_array($word, self::QUALIFIERS, true)) {
            return false;
        }

        return self::IDIOM_END === $word || 1 === preg_match('/^\p{Lu}/u', $this->source->originalOf($offset, 1));
    }
}
