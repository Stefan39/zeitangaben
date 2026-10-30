<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Rule;

use Stejac\Zeitangaben\State;

/**
 * What the text says besides the claimed span and the state mention. A list of bad context can never be
 * complete, so this one is the other way round: only words known to change nothing are harmless.
 *
 * @internal
 */
final readonly class RestOfText
{
    /**
     * Closed list. Add only verbs and function words of this kind: never negators, numbers or nouns that
     * could name something else.
     */
    public const array HARMLESS_WORDS = [
        'ich', 'wir', 'sie', 'man', 'uns', 'mir', 'mich',
        'bitte', 'gern', 'gerne', 'dann', 'da', 'dort', 'also', 'ja', 'und', 'oder',
        'hallo', 'moin', 'servus', 'danke',
        'im', 'in', 'am', 'an', 'zu', 'zum', 'zur', 'vom', 'von', 'ab', 'fuer', 'gegen',
        'den', 'dem', 'der', 'die', 'das', 'des', 'ein', 'eine', 'einen', 'einer',
        'fahren', 'kommen', 'komme', 'kommt', 'anreisen', 'anreise', 'abreisen', 'abreise',
        'buchen', 'buche', 'reisen', 'reise', 'urlaub', 'termin', 'zeitraum', 'zeit',
        'koennen', 'koennten', 'koennte', 'kann', 'moechten', 'moechte', 'wuerden', 'wuerde',
        'wollen', 'will', 'sollen', 'soll', 'ist', 'sind', 'sein', 'waere', 'waeren', 'haben', 'hatten',
        'frei', 'frueh', 'abend', 'vormittag', 'nachmittag', 'mittag',
    ];

    /** Fills a greeting. Not whitespace, so a number in front of a greeting is not the end of the input. */
    public const string GREETING_MARK = "\x01";

    private const string NON_WORDS = '~[\s.,;:!?()\x01-]+~';

    private string $text;

    /**
     * @param string $text normalised text with greetings marked and nothing masked, so every number still shows;
     *                     the span goes wherever it stands, since a repeated expression counts once
     */
    public function __construct(string $text, RuleMatch $match)
    {
        $withoutSpan = preg_replace('~(?<![a-z0-9])'.preg_quote($match->expression, '~').'(?![a-z0-9])~', ' ', $text) ?? $text;
        $this->text = preg_replace('~'.State::mentionPattern().'~', ' ', $withoutSpan) ?? $withoutSpan;
    }

    /**
     * @return list<State> the states named here without a word that makes them a state mention
     */
    public function states(): array
    {
        return State::namedIn($this->text);
    }

    public function isHarmless(): bool
    {
        $words = preg_split(self::NON_WORDS, $this->text, -1, PREG_SPLIT_NO_EMPTY);

        return [] === array_diff(false === $words ? [$this->text] : $words, self::HARMLESS_WORDS);
    }
}
