<?php
/*
 * Created on   : Wed Sep 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DateStringBoundRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate gegen `Y-m-d`-String-Obergrenzen auf Datumsspalten
 * (CI-Dauerrot 08–09/2026).
 *
 * Eloquents `date`-Cast speichert auch in DATE-Spalten `Y-m-d 00:00:00`;
 * SQLite (die CI-Testsuite) vergleicht Strings. Damit schließen
 * `spalte <= 'Y-m-d'`, `spalte = 'Y-m-d'` und die BETWEEN-Obergrenze mit
 * `Y-m-d` den Grenztag stillschweigend aus — MariaDB vergleicht typisiert,
 * lokal bleibt alles grün. Der Sweep vom 2026-09-02 hat ~115 Stellen
 * umgestellt; dieses Gate hält den Bestand auf null.
 *
 * Regel: Obergrenzen über {@see \App\Support\Query\DateRange} führen —
 * `->whereBetween($spalte, DateRange::days($from, $to))` bzw.
 * `->where($spalte, '<', DateRange::dayAfter($to))`; Tagesgleichheit als
 * `DateRange::days($tag, $tag)`. Untergrenzen (`>=` mit `Y-m-d`) sind auf
 * beiden Engines korrekt und bleiben erlaubt.
 */
class DateStringBoundRuleTest extends TestCase {
    use ScansSourceTree;

    /**
     * Bewusst nicht erfasste Pfade: Präfix → Begründung.
     *
     * @var array<string, string>
     */
    private const ALLOW_LIST = [
        // Das Altsystem-Modul wird abgelöst, nicht migriert; seine Abfragen
        // laufen gegen die Legacy-Verbindung und nie gegen SQLite.
        'app/Legacy/' => 'Altsystem-Modul: wird abgelöst, nicht umgestellt.',
    ];

    /**
     * Verbotene Muster (je Zeile, Kommentare entfernt). `whereDate`-Zeilen
     * sind ausgenommen: `DATE(spalte)` normalisiert beide Seiten — dafür
     * gilt die (fallende) Baseline in {@see WhereDateRuleTest}.
     *
     * @var array<string, string> Regex → Kurzbeschreibung
     */
    private const FORBIDDEN = [
        '/->(?:or)?[wW]hereBetween\s*\(.*(?:->toDateString\(\)|->format\(\'Y-m-d\'\))\s*\]/' => 'BETWEEN-Obergrenze als Y-m-d-String',
        '/\'(?:<=|>)\'\s*,\s*.*(?:->toDateString\(\)|->format\(\'Y-m-d\'\))/' => "'<='/'>' gegen Y-m-d-String",
        '/->(?:or)?[wW]here\s*\(\s*\'[A-Za-z0-9_.]+\'\s*,\s*[^\',\n][^,\n]*(?:->toDateString\(\)|->format\(\'Y-m-d\'\))\s*\)/' => 'Gleichheit gegen Y-m-d-String',
    ];

    public function test_no_date_string_upper_bounds(): void {
        $violations = [];

        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            // Zeilenerhaltend von Kommentaren befreien — stripComments() würde
            // Blockkommentare samt Umbrüchen entfernen und die Zeilennummern
            // der Meldungen verschieben.
            $source = (string) preg_replace_callback(
                '~/\*.*?\*/~s',
                static fn (array $m): string => str_repeat("\n", substr_count($m[0], "\n")),
                (string) file_get_contents($file),
            );
            $source = (string) preg_replace('~^\s*(//|#(?!\[)).*$~m', '', $source);
            foreach (explode("\n", $source) as $index => $line) {
                if (stripos($line, 'whereDate') !== false) {
                    continue;
                }
                foreach (self::FORBIDDEN as $pattern => $label) {
                    if (preg_match($pattern, $line) === 1) {
                        $violations[] = sprintf('%s:%d — %s', $relative, $index + 1, $label);
                    }
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Y-m-d-String-Obergrenze auf einer Datumsspalte gefunden.\n"
            . "SQLite (CI) vergleicht Strings; der date-Cast speichert `Y-m-d 00:00:00` —\n"
            . "der Grenztag fällt dort stillschweigend aus der Menge. Stattdessen:\n"
            . "  Bereich        → ->whereBetween(\$spalte, DateRange::days(\$from, \$to))\n"
            . "  obere Grenze   → ->where(\$spalte, '<', DateRange::dayAfter(\$to))\n"
            . "  Tagesgleichheit→ ->whereBetween(\$spalte, DateRange::days(\$tag, \$tag))\n\n"
            . implode("\n", $violations));
    }
}
