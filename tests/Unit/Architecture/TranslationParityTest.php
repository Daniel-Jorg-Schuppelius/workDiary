<?php
/*
 * Created on   : Thu Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TranslationParityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use App\Support\{Locales, Translations};
use Tests\TestCase;

/**
 * Architektur-Gate für Übersetzungs-Parität (vgl. {@see TenantTraitCoverageTest}):
 *  - Jede JSON-Sprachdatei hat exakt denselben Key-Bestand (Referenz = en + Extras).
 *  - Jede auswählbare Sprache besitzt jede Namespace-Datei (echte Übersetzung oder
 *    en-Fallback-Stub) — fehlende Dateien würden stumm auf Deutsch/Schlüssel zeigen.
 *
 * Lücken werden mit `php artisan lang:sync --locale=<code> --fill` behoben.
 */
class TranslationParityTest extends TestCase {
    public function test_all_json_locales_cover_the_canonical_keys(): void {
        // Jede Sprache muss mindestens alle en.json-Keys (Fallback-Locale)
        // abdecken — sonst zeigt die UI den rohen Schlüssel. Zusätzliche,
        // sprachspezifische Keys sind erlaubt (z. B. noch nicht überall
        // propagierte Enum-Keys) und werden hier bewusst nicht beanstandet.
        $reference = Translations::jsonReferenceKeys();

        foreach (Translations::jsonLocales() as $code) {
            $have = array_fill_keys(array_keys(Translations::loadJson($code)), true);
            $missing = array_values(array_filter($reference, static fn(string $k): bool => ! isset($have[$k])));

            $this->assertSame([], $missing, "lang/$code.json fehlen Keys (lang:sync --locale=$code --fill).");
        }
    }

    public function test_every_enabled_locale_has_all_namespace_files(): void {
        $missing = [];
        foreach (Translations::namespaceFiles() as $file) {
            foreach (Locales::enabledCodes() as $code) {
                if (! is_file(Translations::langPath($code) . '/' . $file)) {
                    $missing[] = "$code/$file";
                }
            }
        }

        $this->assertSame([], $missing, 'Fehlende Namespace-Dateien: ' . implode(', ', $missing));
    }

    public function test_non_default_namespaces_cover_all_english_keys(): void {
        $offenders = [];
        foreach (Translations::namespaceFiles() as $file) {
            $reference = Translations::phpKeys('en', $file);
            foreach (Locales::enabledCodes() as $code) {
                if ($code === 'en' || $code === 'de') {
                    continue;
                }
                $have = array_fill_keys(Translations::phpKeys($code, $file), true);
                foreach ($reference as $key) {
                    if (! isset($have[$key])) {
                        $offenders[] = "$code/$file:$key";
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'Namespace-Keys fehlen: ' . implode(', ', array_slice($offenders, 0, 10)));
    }
}
