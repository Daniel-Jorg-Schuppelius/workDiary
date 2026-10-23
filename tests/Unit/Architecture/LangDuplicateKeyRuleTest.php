<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LangDuplicateKeyRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Architektur-Gate: keine doppelten Schlüssel in den PHP-Sprachdateien.
 *
 * PHP lässt den späteren Schlüssel still gewinnen. Eine zweite Gruppe
 * `'credentials' => [` in procurement.php löschte so die Texte der ersten,
 * und die Bestellansicht zeigte den rohen Schlüssel (Befund 2026-10-23).
 * Gezählt wird je Ebene, verschachtelte Gruppen getrennt.
 */
class LangDuplicateKeyRuleTest extends TestCase {
    public function test_language_files_have_no_duplicate_keys(): void {
        $root = (string) realpath(__DIR__ . '/../../..');
        $files = array_merge(
            glob($root . '/lang/*/*.php') ?: [],
            glob($root . '/app/Plugins/*/Resources/lang/*/*.php') ?: [],
        );
        $this->assertNotSame([], $files);

        $duplicates = [];
        foreach ($files as $file) {
            foreach ($this->duplicateKeys((string) file_get_contents($file)) as $finding) {
                $duplicates[] = substr($file, strlen($root) + 1) . ': ' . $finding;
            }
        }

        $this->assertSame([], $duplicates, "Doppelte Schlüssel in Sprachdateien — der spätere überschreibt den früheren:\n" . implode("\n", $duplicates));
    }

    /** @return list<string> */
    private function duplicateKeys(string $source): array {
        $levels = [[]];
        $lastString = null;
        $findings = [];

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $lastString = [$token[1], $token[2]];
                } elseif ($token[0] === T_DOUBLE_ARROW && $lastString !== null) {
                    [$key, $line] = $lastString;
                    $depth = count($levels) - 1;
                    if (isset($levels[$depth][$key])) {
                        $findings[] = "{$key} (Zeile {$levels[$depth][$key]} und {$line})";
                    }
                    $levels[$depth][$key] = $line;
                }

                continue;
            }

            if ($token === '[') {
                $levels[] = [];
            } elseif ($token === ']') {
                array_pop($levels);
            }
            if ($token === '[' || $token === ']' || $token === ',') {
                $lastString = null;
            }
        }

        return $findings;
    }
}
