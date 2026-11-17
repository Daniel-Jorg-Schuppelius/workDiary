<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurposeKeywordIndex.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Crypto;

use CommonToolkit\Helper\Data\StringHelper;

/**
 * Stichwörter des Verwendungszwecks als Merkmal der Kontierungshilfe
 * (Feature 207, MVP-1660).
 *
 * Jeden Monat derselbe Umsatz, nur Monat, Datum und Belegnummer wechseln:
 * „Miete Januar 2026 Büro Hauptstraße“ und „Miete Februar 2026 Büro
 * Hauptstraße“ sollen gleich aussehen. Deshalb fallen Ziffern, Satzzeichen,
 * Monatsnamen, SEPA-Kennungen und Füllwörter weg; der Rest wird klein, ohne
 * Umlaute, sortiert und ohne Doppelte.
 *
 * Gespeichert wird nur der geschlüsselte Abdruck ({@see BlindIndex}) — nie die
 * Stichwörter selbst; `security:rehash-blind-indexes` rechnet ihn bei einem
 * Schlüsselwechsel mit um. Der Verwendungszweck enthält Namen, Vertrags- und
 * Kundennummern; ein ungeschlüsselter Abdruck ließe sich über Wortlisten
 * zurückrechnen.
 */
final class PurposeKeywordIndex {
    /** Kürzere Wörter tragen keine Bedeutung (Artikel, Kürzel, Reste von Nummern). */
    private const int MIN_LENGTH = 3;

    /** Obergrenze: Ein langer Freitext soll nicht an einem Zusatzwort scheitern. */
    private const int MAX_WORDS = 12;

    /** Präfix des Abdrucks — trennt ihn von IBAN- und Mail-Abdrücken mit gleichem Schlüssel. */
    private const string DOMAIN = 'purpose-keywords:';

    /**
     * Wörter, die von Monat zu Monat wechseln oder nichts unterscheiden.
     *
     * @var list<string>
     */
    private const array STOPWORDS = [
        // Monate (deutsch, englisch, Kürzel)
        'januar', 'jaenner', 'februar', 'maerz', 'april', 'mai', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'dezember',
        'january', 'february', 'march', 'may', 'june', 'july', 'october', 'december',
        'jan', 'feb', 'mar', 'apr', 'jun', 'jul', 'aug', 'sep', 'sept', 'okt', 'oct', 'nov', 'dez', 'dec',
        'monat', 'month', 'quartal', 'quarter', 'jahr', 'year',
        // SEPA-Kennungen aus MT940 :86: und CAMT
        'eref', 'mref', 'cred', 'svwz', 'kref', 'abwa', 'abwe', 'iban', 'bic', 'purp', 'notprovided', 'datum', 'uhr',
        // Füllwörter
        'und', 'der', 'die', 'das', 'den', 'dem', 'des', 'fuer', 'vom', 'von', 'zum', 'zur', 'mit', 'bei', 'per', 'aus', 'auf',
        'the', 'for', 'and', 'from', 'with',
    ];

    /**
     * Normalisierte Stichwörter, sortiert und ohne Doppelte.
     *
     * @return list<string>
     */
    public static function keywords(?string $purpose): array {
        $text = StringHelper::toLower(StringHelper::toAscii($purpose));
        // Nur Buchstaben bleiben: Ziffern, Beträge, Daten und Satzzeichen trennen Wörter.
        $text = (string) preg_replace('/[^a-z]+/', ' ', $text);

        $words = [];
        foreach (StringHelper::words($text) as $word) {
            if (strlen($word) < self::MIN_LENGTH || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $words[$word] = true;
        }

        $words = array_keys($words);
        sort($words, SORT_STRING);

        return array_slice($words, 0, self::MAX_WORDS);
    }

    /** Geschlüsselter Abdruck der Stichwörter; null, wenn keine übrig bleiben. */
    public static function hash(?string $purpose): ?string {
        $keywords = self::keywords($purpose);

        return $keywords === [] ? null : BlindIndex::of(self::DOMAIN . implode(' ', $keywords));
    }
}
