<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinancialFormatsGuardRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „financial-formats nur hinter dem Guard" (Vollscan
 * 2026-08-23, C15; AGENTS.md §9.1): Das Paket ist ein Optionalpaket (nie in
 * der committeten composer.lock — Memory „composer-require-Lock-Falle").
 * Jede Datei, die `CommonToolkit\FinancialFormats`-Symbole nutzt, muss den
 * Guard (`FinancialFormatsSupport::ensureAvailable()`/`isAvailable()`)
 * enthalten — Top-Level-`use`-Imports sind als dokumentierte Ausnahme ok
 * (PHP lädt Klassen lazy), Instanziierung ohne Guard ist es nicht.
 */
class FinancialFormatsGuardRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_financial_formats_usage_sits_behind_the_guard(): void {
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if ($relative === 'app/Services/Finance/FinancialFormatsSupport.php') {
                continue; // der Guard selbst
            }
            $source = (string) file_get_contents($file);
            if (! str_contains($source, 'CommonToolkit\\FinancialFormats\\')) {
                continue;
            }
            if (str_contains($source, 'FinancialFormatsSupport::ensureAvailable(')
                || str_contains($source, 'FinancialFormatsSupport::isAvailable(')) {
                continue;
            }
            $violations[] = $relative;
        }

        sort($violations);
        $this->assertSame([], $violations, "FinancialFormats-Symbole ohne FinancialFormatsSupport-Guard — ohne installiertes Optionalpaket\n"
            . "ist das ein Fatal zur Laufzeit. ensureAvailable() an den Eintrittspunkt der Klasse setzen.\n\n" . implode("\n", $violations));
    }
}
