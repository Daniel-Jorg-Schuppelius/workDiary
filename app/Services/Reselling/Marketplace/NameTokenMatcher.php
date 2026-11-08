<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NameTokenMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

/**
 * Firmennamen tokenbasiert vergleichen: Rechtsform und Füllwörter zählen
 * nicht, die Reihenfolge auch nicht. „Auerswald" trifft „Auerswald GmbH & Co.
 * KG", „Wendt Fliesen" trifft „Fliesen Wendt", „Berg und Tal" trifft
 * „Berg&Tal Elektroinstallationen GmbH" — alle Kern-Tokens des kürzeren
 * Namens stecken im längeren, und mindestens einer davon ist länger als drei
 * Zeichen (sonst träfe „GSR" jede Firma mit diesem Kürzel).
 */
final class NameTokenMatcher {
    private const array LEGAL_FORMS = [
        'gmbh', 'co', 'kg', 'e', 'k', 'v', 'ug', 'ohg', 'mbb', 'ag', 'gbr', 'ltd', 'inc', 'se',
        'haftungsbeschraenkt', 'partnerschaftsgesellschaft',
    ];

    private const array GENERIC = [
        ...self::LEGAL_FORMS,
        'und', 'and', 'gesellschaft',
        'service', 'services', 'berlin', 'haus', 'herr', 'frau', 'fam', 'familie',
        'dienstleistungen', 'dienstleistung', 'gruppe', 'group', 'company', 'die', 'der', 'das', 'von', 'mit',
    ];

    /**
     * @return list<string>
     */
    public static function significantTokens(string $name): array {
        $tokens = [];
        foreach (explode(' ', MarketplaceCompany::normalizeName($name)) as $token) {
            if ($token === '' || in_array($token, self::GENERIC, true)) {
                continue;
            }
            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Namensbestandteile ohne Rechtsform („Hof 24 GmbH“ → hof, 24).
     *
     * @return list<string>
     */
    public static function withoutLegalForm(string $name): array {
        return array_values(array_filter(
            explode(' ', MarketplaceCompany::normalizeName($name)),
            static fn (string $token): bool => $token !== '' && ! in_array($token, self::LEGAL_FORMS, true),
        ));
    }

    public static function matches(string $a, string $b): bool {
        return self::tokensMatch(self::significantTokens($a), self::significantTokens($b));
    }

    /**
     * Nennt der Text den Namen (Konsolidierungs-Audit 2026-11-02, kon-01)?
     * Alle Kern-Tokens des Namens stehen als Wörter im Text, mindestens eines
     * mit ≥ 4 Zeichen („Lizenzen für Muster“ nennt „Muster GmbH“) — oder die
     * leerzeichenfreie Form des Namens (mit und ohne Rechtsform) bzw. eines
     * Alias (Firma, Matchcode) steht als Wort oder Wortfolge im Text („M365
     * Hof24“ und „Hof 24“ nennen „Hof 24 GmbH“, „STBK“ den Matchcode). Kein
     * Teilwort: „Mustermann“ nennt „Muster GmbH“ nicht.
     *
     * @param  list<string|null>  $aliases  weitere Schreibweisen (Firma laut Anbieter, Matchcode)
     */
    public static function mentionedIn(string $name, string $text, array $aliases = []): bool {
        $words = explode(' ', MarketplaceCompany::normalizeName($text));
        $tokens = self::significantTokens($name);
        if ($tokens !== [] && array_diff($tokens, $words) === []) {
            foreach ($tokens as $token) {
                if (mb_strlen($token) >= 4) {
                    return true;
                }
            }
        }
        $keys = [];
        foreach ([$name, implode(' ', self::withoutLegalForm($name)), ...$aliases] as $candidate) {
            $key = str_replace(' ', '', MarketplaceCompany::normalizeName((string) $candidate));
            if (mb_strlen($key) >= 4) {
                $keys[$key] = true;
            }
        }
        foreach (array_keys($keys) as $key) {
            if (self::wordRunEquals($words, (string) $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ist der Schlüssel ein Wort des Textes oder eine Folge aufeinanderfolgender
     * Wörter ohne Leerzeichen („hof24“ = „hof“ + „24“)?
     *
     * @param  list<string>  $words
     */
    private static function wordRunEquals(array $words, string $key): bool {
        $length = strlen($key);
        $count = count($words);
        for ($start = 0; $start < $count; $start++) {
            $run = '';
            for ($i = $start; $i < $count && strlen($run) < $length; $i++) {
                $run .= $words[$i];
            }
            if ($run === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Wie {@see matches()} auf schon zerlegten Namen — für viele Vergleiche gegen dieselben Texte.
     *
     * @param  list<string>  $tokensA
     * @param  list<string>  $tokensB
     */
    public static function tokensMatch(array $tokensA, array $tokensB): bool {
        if ($tokensA === [] || $tokensB === []) {
            return false;
        }
        [$short, $long] = count($tokensA) <= count($tokensB) ? [$tokensA, $tokensB] : [$tokensB, $tokensA];
        if (array_diff($short, $long) !== []) {
            return false;
        }
        foreach ($short as $token) {
            if (mb_strlen($token) >= 4) {
                return true;
            }
        }

        return false;
    }
}
