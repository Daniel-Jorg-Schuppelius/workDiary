<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PasswordComparisonRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate zu S-50 (Sicherheitsscan 2026-08-23).
 *
 * Ein Passwort gehört nie in eine WHERE-Bedingung. Im Legacy-Pfad lief der
 * Vergleich als `where('userpw', $password)` gegen eine Tabelle mit
 * `latin1_german2_ci` — case-insensitiv und PAD SPACE. Die Datenbank hielt
 * damit „Geheim1", „GEHEIM1" und „geheim1  " für dasselbe Passwort; für
 * Credential-Stuffing schrumpft der Suchraum drastisch.
 *
 * Die Collation ist nur der sichtbare Teil. Ein Passwort im SQL landet
 * außerdem in Query-Logs und im Slow-Query-Log, und der Vergleich ist nicht
 * zeitkonstant. Byte-genau vergleicht `hash_equals()` in PHP.
 *
 * Kein Verhaltenstest möglich: die Legacy-Verbindung ist in Tests bewusst
 * leer, der Pfad steigt vorher aus. Deshalb diese Regel.
 */
class PasswordComparisonRuleTest extends TestCase {
    use ScansSourceTree;

    /** Spalten, deren Wert ein Klartext-Passwort ist. */
    private const PASSWORD_COLUMNS = ['userpw', 'password', 'passwort', 'passwd'];

    public function test_kein_passwortvergleich_in_der_where_bedingung(): void {
        $violations = [];

        foreach ($this->filesUnder('app', '/\.php$/') as $path) {
            $source = (string) file_get_contents($path);

            foreach (self::PASSWORD_COLUMNS as $column) {
                // ->where('userpw', …) bzw. ->where("userpw", …)
                if (preg_match('/->(?:or)?[wW]here\s*\(\s*[\'"]' . preg_quote($column, '/') . '[\'"]\s*,/', $source) === 1) {
                    $violations[] = $this->relative($path) . " (Spalte {$column})";
                }
            }
        }

        $this->assertSame([], $violations, sprintf(
            "Passwortvergleich in der WHERE-Bedingung:\n  %s\n"
            . 'Stattdessen nur über den Benutzernamen laden und mit hash_equals() in PHP vergleichen.',
            implode("\n  ", $violations),
        ));
    }

    private function relative(string $path): string {
        return str_replace($this->repoRoot() . '/', '', $path);
    }
}
