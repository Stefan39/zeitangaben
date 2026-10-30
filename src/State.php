<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben;

enum State: string
{
    case BB = 'BB';
    case BE = 'BE';
    case BW = 'BW';
    case BY = 'BY';
    case HB = 'HB';
    case HE = 'HE';
    case HH = 'HH';
    case MV = 'MV';
    case NI = 'NI';
    case NW = 'NW';
    case RP = 'RP';
    case SH = 'SH';
    case SL = 'SL';
    case SN = 'SN';
    case ST = 'ST';
    case TH = 'TH';

    /**
     * The first state mentioned in lowercase, umlaut-transliterated text.
     *
     * @internal
     */
    public static function fromText(string $normalized): ?self
    {
        return self::allFromText($normalized)[0] ?? null;
    }

    /**
     * All different states mentioned in lowercase, umlaut-transliterated text, in text order.
     * Hyphens count as part of a word, so "sachsen" does not match inside "sachsen-anhalt".
     *
     * @internal
     *
     * @return list<self>
     */
    public static function allFromText(string $normalized): array
    {
        preg_match_all('~'.self::mentionPattern().'~', $normalized, $mentions);

        return self::namedIn(implode(', ', $mentions['states']));
    }

    /**
     * All different states whose name stands anywhere in lowercase, umlaut-transliterated text, with or without
     * a word that makes it a state mention. A bare name proves nothing, but it does leave the state in doubt.
     *
     * @internal
     *
     * @return list<self>
     */
    public static function namedIn(string $normalized): array
    {
        preg_match_all('~(?<![a-z0-9-])(?:'.self::aliasPattern().')(?![a-z0-9-])~', $normalized, $names);
        $aliases = self::aliases();
        $states = [];
        foreach ($names[0] as $name) {
            $states[$aliases[$name]->value] = $aliases[$name];
        }

        return array_values($states);
    }

    /**
     * Regular expression body for a state mentioned the way a state is mentioned: "in Bayern", "aus dem
     * Saarland", "Bundesland Hessen". The bare name proves nothing: "Frau Berlin", "FC Bayern", "BW Bank".
     *
     * @internal
     */
    public static function mentionPattern(): string
    {
        $name = '(?:'.self::aliasPattern().')(?![a-z0-9-])';

        return '(?<![a-z0-9-])(?:in|im|aus|nach|fuer|von|vom|bundesland:?|land)\s+(?:(?:dem|das|den)\s+)?'
            .'(?<states>'.$name.'(?:(?:\s*[,/]\s*|\s+und\s+|\s+oder\s+)'.$name.')*)';
    }

    /**
     * The state with the preposition it takes in a sentence: "in Bayern", "im Saarland".
     */
    public function locative(): string
    {
        return (self::SL === $this ? 'im ' : 'in ').$this->germanName();
    }

    public function germanName(): string
    {
        return match ($this) {
            self::BB => 'Brandenburg',
            self::BE => 'Berlin',
            self::BW => 'Baden-Württemberg',
            self::BY => 'Bayern',
            self::HB => 'Bremen',
            self::HE => 'Hessen',
            self::HH => 'Hamburg',
            self::MV => 'Mecklenburg-Vorpommern',
            self::NI => 'Niedersachsen',
            self::NW => 'Nordrhein-Westfalen',
            self::RP => 'Rheinland-Pfalz',
            self::SH => 'Schleswig-Holstein',
            self::SL => 'Saarland',
            self::SN => 'Sachsen',
            self::ST => 'Sachsen-Anhalt',
            self::TH => 'Thüringen',
        };
    }

    private static function aliasPattern(): string
    {
        $aliases = array_keys(self::aliases());
        usort($aliases, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return implode('|', $aliases);
    }

    /**
     * @return array<string, self>
     */
    private static function aliases(): array
    {
        return [
            'brandenburg' => self::BB,
            'berlin' => self::BE,
            'baden-wuerttemberg' => self::BW,
            'baden wuerttemberg' => self::BW,
            'bw' => self::BW,
            'bayern' => self::BY,
            'bremen' => self::HB,
            'hessen' => self::HE,
            'hamburg' => self::HH,
            'mecklenburg-vorpommern' => self::MV,
            'mecklenburg vorpommern' => self::MV,
            'mv' => self::MV,
            'niedersachsen' => self::NI,
            'nds' => self::NI,
            'nordrhein-westfalen' => self::NW,
            'nordrhein westfalen' => self::NW,
            'nrw' => self::NW,
            'rheinland-pfalz' => self::RP,
            'rheinland pfalz' => self::RP,
            'rlp' => self::RP,
            'schleswig-holstein' => self::SH,
            'schleswig holstein' => self::SH,
            'saarland' => self::SL,
            'sachsen' => self::SN,
            'sachsen-anhalt' => self::ST,
            'sachsen anhalt' => self::ST,
            'thueringen' => self::TH,
        ];
    }
}
