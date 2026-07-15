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

    public function test_all_source_json_keys_exist_in_the_reference_catalog(): void {
        // Ein im Quellcode verwendeter JSON-Stil-Key, der in en.json fehlt,
        // fällt in ALLEN Sprachen auf den deutschen Quelltext zurück — die
        // Katalog-Parität (en ↔ fr/it/es) ist dafür blind. Genau so blieben
        // die Verleih-/Leasing-/Prüfmittel-Views unübersetzt (2026-07).
        $reference = array_fill_keys(Translations::jsonReferenceKeys(), true);
        $missing = array_values(array_filter(
            Translations::sourceJsonKeys(),
            static fn(string $k): bool => ! isset($reference[$k]),
        ));

        $this->assertSame([], $missing, 'Im Quellcode verwendete Keys fehlen in en.json (und damit überall).');
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

    public function test_every_scheduler_job_has_a_label_in_every_locale(): void {
        // JobDefinition::label() fällt via Trans::or auf den technischen Key
        // zurück — ein unbenanntes Job-Registry-Element zeigt in der
        // Scheduler-Übersicht sonst z. B. "claims.escalate" in ALLEN Sprachen.
        // Gleiches gilt für Kadenz-/Kritikalitäts-Labels der Enums.
        $keys = [];
        foreach (array_keys((array) config('scheduler.jobs', [])) as $jobKey) {
            $keys[] = 'scheduler.job.' . $jobKey;
        }
        foreach (\App\Scheduling\CadenceType::cases() as $cadence) {
            $keys[] = 'scheduler.cadence.' . $cadence->value;
        }
        foreach (\App\Scheduling\JobCriticality::cases() as $criticality) {
            $keys[] = 'scheduler.criticality.' . $criticality->value;
        }

        $offenders = [];
        foreach ($keys as $key) {
            foreach (Locales::enabledCodes() as $code) {
                if (! app('translator')->has($key, $code, false)) {
                    $offenders[] = "$code: $key";
                }
            }
        }

        $this->assertSame([], $offenders, 'Scheduler-Labels fehlen: ' . implode(', ', $offenders));
    }

    public function test_every_permission_has_a_real_label_in_every_locale(): void {
        // Permission::label() fällt auf den Slug zurück — ein fehlender oder
        // als Stub (Wert == Slug) angelegter Eintrag zeigt in der
        // Rechte-Matrix den rohen Code, in allen Sprachen.
        $offenders = [];
        foreach (Locales::enabledCodes() as $code) {
            $map = (array) trans('access.permission', [], $code);
            foreach (\App\Enums\User\Permission::cases() as $permission) {
                $label = $map[$permission->value] ?? null;
                if (! is_string($label) || $label === '' || $label === $permission->value) {
                    $offenders[] = "$code: {$permission->value}";
                }
            }
        }

        $this->assertSame([], $offenders, 'Permissions ohne Label: ' . implode(', ', array_slice($offenders, 0, 15)));
    }
}
