<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingWriterRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\{MeasuresEffect, ScansSourceTree};

/**
 * Gate MVP-1620 „Einstellung ohne Schreibstelle“: Jeder Schlüssel, den der
 * Code über `Setting::get()` oder aus `organizations.settings` liest, hat
 * eine Schreibstelle — Eintrag in `config/settings-registry.php` (generische
 * Einstellungsseite, `service_managed` für Fachdienste), `Setting::set()`,
 * ein Formularfeld `settings[…]` oder einen Dienst, der den Pfad in die
 * Einstellungen schreibt. Sonst gilt immer die Vorgabe aus `config/`
 * (Scan 2026-11-10: Bewertungsverfahren, Freigabezwang, Rechtsraum).
 *
 * Gelesener und geschriebener Pfad passen, wenn einer Präfix des anderen ist
 * (ein Formular schreibt die Gruppe, ein Dienst liest das Blatt).
 */
final class SettingWriterRuleTest extends TestCase {
    use MeasuresEffect;
    use ScansSourceTree;

    /** Variablen, die eine Organisation tragen. */
    private const string ORGANIZATION = '\$(?:org|organization|organisation|tenant|currentOrganization|this->organization|this->currentOrganization\(\))\w*(?:\??->\w+)*';

    public function test_every_read_setting_has_a_writer(): void {
        /** @var array<string, string> $baseline */
        $baseline = require __DIR__ . '/baselines/setting-writers.php';
        $new = array_values(array_diff($this->offenders(), array_keys($baseline)));

        $this->assertSame([], $new, "Gelesene Einstellung ohne Schreibstelle:\n  " . implode("\n  ", $new)
            . "\n\nIn config/settings-registry.php eintragen (Labels ×5), ins Fachformular aufnehmen oder den Lesezugriff entfernen."
            . "\nNur eine begründete Ausnahme gehört mit Grund in baselines/setting-writers.php.");
    }

    public function test_baseline_has_no_stale_entries(): void {
        /** @var array<string, string> $baseline */
        $baseline = require __DIR__ . '/baselines/setting-writers.php';
        $stale = array_values(array_diff(array_keys($baseline), $this->offenders()));

        $this->assertSame([], $stale, "Diese Einstellungen haben inzwischen eine Schreibstelle oder werden nicht mehr gelesen — aus der Baseline streichen:\n  " . implode("\n  ", $stale));
        $this->assertSame([], $this->baselineEntriesWithoutReason($baseline), 'Baseline-Eintrag ohne konkreten Grund.');
    }

    public function test_the_scan_sees_reads_and_writes(): void {
        [$reads, $writes] = $this->readsAndWrites();
        $this->assertGreaterThan(100, count($reads));
        $this->assertGreaterThan(200, count($writes));
    }

    /** @return list<string> gelesene Schlüssel ohne Schreibstelle */
    private function offenders(): array {
        [$reads, $writes] = $this->readsAndWrites();
        $found = [];
        foreach (array_keys($reads) as $key) {
            $written = false;
            foreach (array_keys($writes) as $write) {
                if ($write === $key || str_starts_with($key, $write . '.') || str_starts_with($write, $key . '.')) {
                    $written = true;

                    break;
                }
            }
            if (! $written) {
                $found[] = $key;
            }
        }
        sort($found);

        return $found;
    }

    /** @return array{0: array<string, true>, 1: array<string, true>} gelesene und geschriebene Pfade */
    private function readsAndWrites(): array {
        $corpus = $this->codeCorpus(['app', 'routes', 'config']);
        $views = $this->codeCorpus(['resources/views'], '/\.blade\.php$/');
        $constants = [];
        foreach ($corpus as $source) {
            if (preg_match_all('/const\s+(?:string\s+)?(\w+)\s*=\s*[\'"]([a-z0-9_]+(?:\.[a-z0-9_]+)*)[\'"]/', $source, $m, PREG_SET_ORDER) > 0) {
                foreach ($m as [, $name, $value]) {
                    $constants[$name][] = $value;
                }
            }
        }
        $reads = [];
        $writes = [];
        $org = self::ORGANIZATION;
        foreach ($corpus + $views as $relative => $source) {
            if (str_contains($relative, '/Database/Migrations/')) {
                continue;
            }
            // Lesestellen
            if (preg_match_all('/Setting::get\(\s*[\'"]([^\'"]+)[\'"]/', $source, $m) > 0) {
                foreach ($m[1] as $key) {
                    $reads[rtrim((string) preg_replace('/\$\w+.*$|\{.*$/', '', $key), '.')] = true;
                }
            }
            if (preg_match_all('/Setting::get\(\s*(?:\\\\?[\w\\\\]+|self|static)::(\w+)/', $source, $m) > 0) {
                foreach ($m[1] as $constant) {
                    foreach ($constants[$constant] ?? [] as $key) {
                        $reads[$key] = true;
                    }
                }
            }
            if (preg_match_all('/(?:data_get|Arr::get)\(\s*\(?' . $org . '\??->settings(?:\s*\?\?\s*\[\])?\)?\s*,\s*[\'"]([a-z0-9_.]+)[\'"]/', $source, $m) > 0) {
                foreach ($m[1] as $key) {
                    $reads[rtrim($key, '.')] = true;
                }
            }
            if (preg_match_all('/' . $org . '\??->settings((?:\[[\'"][a-z0-9_]+[\'"]\])+)/', $source, $m) > 0) {
                foreach ($m[1] as $path) {
                    preg_match_all('/[\'"]([a-z0-9_]+)[\'"]/', $path, $parts);
                    $reads[implode('.', $parts[1])] = true;
                }
            }
            // Schreibstellen
            if (preg_match_all('/Setting::set\(\s*[\'"]([^\'"]+)[\'"]/', $source, $m) > 0) {
                $writes += array_fill_keys($m[1], true);
            }
            if (preg_match_all('/data_set\(\s*\$\w*settings\w*(?:->\w+)*\s*,\s*[\'"]([a-z0-9_.]+)[\'"]|data_set\(\s*\$\w+(?:->\w+)*\s*,\s*[\'"]settings\.([a-z0-9_.]+)[\'"]/i', $source, $m, PREG_SET_ORDER) > 0) {
                foreach ($m as $hit) {
                    $writes[rtrim(($hit[1] ?? '') !== '' ? $hit[1] : (string) ($hit[2] ?? ''), '.')] = true;
                }
            }
            if (preg_match_all('/(?:\$\w*settings|->settings|\[[\'"]settings[\'"]\])((?:\[[\'"][a-z0-9_]+[\'"]\])+)\s*=(?!=|>)/i', $source, $m) > 0) {
                foreach ($m[1] as $path) {
                    preg_match_all('/[\'"]([a-z0-9_]+)[\'"]/', $path, $parts);
                    $writes[implode('.', $parts[1])] = true;
                }
            }
            if (preg_match_all('/name="settings((?:\[[a-z0-9_]+\])+)/', $source, $m) > 0) {
                foreach ($m[1] as $path) {
                    preg_match_all('/\[([a-z0-9_]+)\]/', $path, $parts);
                    $writes[implode('.', $parts[1])] = true;
                }
            }
            if (preg_match_all('/[\'"]settings\.([a-z0-9_.]+)[\'"]\s*=>/', $source, $m) > 0) {
                $writes += array_fill_keys($m[1], true);
            }
            // Dateien, die organizations.settings schreiben: ihre Regel-Schlüssel und Array-Zuweisungen
            if (! str_ends_with($relative, '.blade.php') && preg_match('/->settings\s*=|[\'"]settings[\'"]\s*=>|->update\(\[\s*[\'"]settings/', $source) === 1) {
                if (preg_match_all('/[\'"]([a-z0-9_]+(?:\.[a-z0-9_*]+)+)[\'"]\s*=>\s*[\[\'"]/', $source, $m) > 0) {
                    foreach ($m[1] as $key) {
                        $writes[rtrim(str_replace('.*', '', $key), '.')] = true;
                    }
                }
                if (preg_match_all('/\$\w+((?:\[[\'"][a-z0-9_]+[\'"]\]){2,})\s*=(?!=|>)/', $source, $m) > 0) {
                    foreach ($m[1] as $path) {
                        preg_match_all('/[\'"]([a-z0-9_]+)[\'"]/', $path, $parts);
                        $writes[implode('.', $parts[1])] = true;
                    }
                }
            }
        }
        foreach (array_keys((array) require $this->repoRoot() . '/config/settings-registry.php') as $key) {
            $writes[(string) $key] = true;
        }
        unset($reads[''], $writes['']);

        return [$reads, $writes];
    }
}
