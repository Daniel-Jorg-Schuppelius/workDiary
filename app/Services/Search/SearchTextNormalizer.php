<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchTextNormalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use CommonToolkit\Helper\Data\StringHelper;

/**
 * Zerlegt Freitext in Suchwörter (Feature 153, MVP-770) — für Index UND Anfrage
 * identisch, sonst finden sich beide nicht.
 *
 * Texte werden ASCII-gefaltet („Postfächer" → „postfaecher") und je Wortgruppe
 * an Trennzeichen sowie Buchstabe/Ziffer-Grenzen zerlegt: „SRV-EX01" ergibt die
 * Teile srv, ex, 01 und im Index zusätzlich die zusammengeschriebene Form
 * srvex01. Im Index trägt jedes Wort das Präfix `w` — MariaDBs Volltextindex
 * ignoriert sonst Wörter unter drei Zeichen („AD") und seine Stoppwörter („it").
 */
final class SearchTextNormalizer {
    public const TOKEN_PREFIX = 'w';

    public const MAX_TOKEN_LENGTH = 40;

    private const SEPARATORS = '._\-@\/:+#';

    /**
     * Wortgruppen des Textes, je Gruppe die Teilwörter in Reihenfolge.
     *
     * @return list<list<string>>
     */
    public function chunks(?string $text): array {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $folded = strtolower(StringHelper::toAscii($text));
        preg_match_all('/[a-z0-9]+(?:[' . self::SEPARATORS . ']+[a-z0-9]+)*/', $folded, $matches);

        $chunks = [];
        foreach ($matches[0] as $chunk) {
            $parts = [];
            foreach (preg_split('/[' . self::SEPARATORS . ']+/', $chunk) ?: [] as $piece) {
                foreach (preg_split('/(?<=[a-z])(?=[0-9])|(?<=[0-9])(?=[a-z])/', $piece) ?: [] as $part) {
                    if ($part !== '' && strlen($part) <= self::MAX_TOKEN_LENGTH) {
                        $parts[] = $part;
                    }
                }
            }
            if ($parts !== []) {
                $chunks[] = $parts;
            }
        }

        return $chunks;
    }

    /**
     * Index-Wörter in Textreihenfolge: Teilwörter ab zwei Zeichen, bei
     * zusammengesetzten Gruppen danach die zusammengeschriebene Form.
     *
     * @return list<string>
     */
    public function tokens(?string $text): array {
        $tokens = [];
        foreach ($this->chunks($text) as $parts) {
            foreach ($parts as $part) {
                if (strlen($part) >= 2) {
                    $tokens[] = $part;
                }
            }
            if (count($parts) > 1) {
                $joined = implode('', $parts);
                if (strlen($joined) <= self::MAX_TOKEN_LENGTH) {
                    $tokens[] = $joined;
                }
            }
        }

        return $tokens;
    }

    /**
     * Kodierter Suchtext: jedes Wort mit Präfix, von Leerzeichen eingerahmt —
     * der Rahmen macht „Wortanfang" auch für LIKE eindeutig.
     *
     * @param  list<string>  $tokens
     */
    public function encode(array $tokens, ?int $maxLength = null): string {
        if ($tokens === []) {
            return '';
        }

        $encoded = ' ' . implode(' ', array_map(static fn(string $t): string => self::TOKEN_PREFIX . $t, $tokens)) . ' ';
        if ($maxLength !== null && strlen($encoded) > $maxLength) {
            $cut = strrpos(substr($encoded, 0, $maxLength), ' ');
            $encoded = substr($encoded, 0, $cut === false ? $maxLength : $cut) . ' ';
        }

        return $encoded;
    }

    /**
     * Wörter eines kodierten Suchtexts (ohne Präfix) — für den Neuaufbau des
     * Wortverzeichnisses.
     *
     * @return list<string>
     */
    public function decode(string $encoded): array {
        $tokens = [];
        foreach (explode(' ', trim($encoded)) as $word) {
            if (strlen($word) > 1 && $word[0] === self::TOKEN_PREFIX) {
                $tokens[] = substr($word, 1);
            }
        }

        return $tokens;
    }

    /** Vergleichsform eines Begriffs: Teilwörter mit Leerzeichen („SMTP-Relay" → „smtp relay"). */
    public function key(string $term): string {
        $words = [];
        foreach ($this->chunks($term) as $parts) {
            foreach ($parts as $part) {
                $words[] = $part;
            }
        }

        return implode(' ', $words);
    }
}
