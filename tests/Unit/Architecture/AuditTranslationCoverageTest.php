<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditTranslationCoverageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Architektur-Gate für die Audit-Log-Übersetzungen (vgl. {@see TranslationParityTest}).
 *
 * `AuditLog::auditableTypeLabel()`/`EntityType::label()` und `eventLabel()`
 * fallen still auf den rohen Klassennamen bzw. Event-String zurück — fehlende
 * Katalog-Einträge zeigen dem Nutzer also englische Bezeichner
 * („CustomerBillingStatement", „asset.checkedIn") statt Übersetzungen, in
 * allen Sprachen. `lang:coverage`/`i18n:check` sind dafür blind, weil beide
 * Schlüssel dynamisch gebaut werden (`'entity-types.' . $short`).
 *
 * Dieses Gate erzwingt:
 *  1. jedes Model mit Auditable-Trait hat einen `entity-types`-Eintrag,
 *  2. jeder manuell geschriebene `auditable_type` (X::class-Literal sowie
 *     self::class-Schreiber inkl. WritesReportCsv-Nutzer) ebenso,
 *  3. jeder statisch auffindbare Event-String löst in `audit-events` auf,
 *  4. alle Sprachkataloge sind untereinander schlüsselgleich (Referenz de).
 */
class AuditTranslationCoverageTest extends TestCase {
    /**
     * Statisch mitgefangene Strings, die keine Audit-Events sind
     * (Array-Schlüssel in `audit()`-Aufrufen, deren Event als Variable kommt).
     *
     * @var list<string>
     */
    private const IGNORED_EVENT_STRINGS = [
        'actor_user_id',
        'by_user_id',
        'expires_at',
        'external_id',
        'event',
    ];

    /** @var list<string>|null */
    private static ?array $phpFiles = null;

    public function test_entity_types_cover_all_auditable_models(): void {
        $catalog = $this->catalog('de', 'entity-types');
        $missing = [];

        foreach ($this->appFiles() as $path) {
            $basename = basename($path, '.php');
            if ($basename === 'Auditable') {
                continue;
            }
            $src = (string) file_get_contents($path);
            // Klassenebenen-use (eingerückt) — Top-Level-Imports allein zählen
            // nicht (Services importieren das Trait teils nur für @see-Links).
            if (! preg_match('/^[ \t]+use\s+[A-Za-z0-9_\\\\ \t,{}]*\bAuditable\b[A-Za-z0-9_\\\\ \t,{}]*;/m', $src)) {
                continue;
            }
            if (! isset($catalog[$basename])) {
                $missing[] = $basename;
            }
        }

        sort($missing);
        $this->assertSame([], $missing, 'Auditable-Models ohne entity-types-Eintrag (lang/*/entity-types.php ergänzen): ' . implode(', ', $missing));
    }

    public function test_entity_types_cover_manual_auditable_type_writers(): void {
        $catalog = $this->catalog('de', 'entity-types');
        $missing = [];

        foreach ($this->appFiles() as $path) {
            $basename = basename($path, '.php');
            $src = (string) file_get_contents($path);

            $writesSelf = str_contains($src, "'auditable_type' => self::class")
                || str_contains($src, "'auditable_type' => static::class")
                || preg_match('/^[ \t]+use\s+[A-Za-z0-9_\\\\ \t,{}]*\bWritesReportCsv\b[A-Za-z0-9_\\\\ \t,{}]*;/m', $src);
            if ($writesSelf && ! in_array($basename, ['Auditable', 'WritesReportCsv'], true) && ! isset($catalog[$basename])) {
                $missing[] = $basename;
            }

            if (preg_match_all('/\'auditable_type\'\s*=>\s*([A-Za-z0-9_\\\\]+)::class/', $src, $m)) {
                foreach ($m[1] as $class) {
                    if (in_array($class, ['self', 'static'], true)) {
                        continue;
                    }
                    $short = ($pos = strrpos($class, '\\')) === false ? $class : substr($class, $pos + 1);
                    if (! isset($catalog[$short])) {
                        $missing[] = $short;
                    }
                }
            }
        }

        $missing = array_values(array_unique($missing));
        sort($missing);
        $this->assertSame([], $missing, 'auditable_type-Schreiber ohne entity-types-Eintrag: ' . implode(', ', $missing));
    }

    public function test_audit_events_cover_all_written_event_strings(): void {
        $catalog = $this->catalog('de', 'audit-events');
        $missing = [];

        foreach ($this->collectEventStrings() as $event) {
            if ($this->resolve($catalog, $event) === null) {
                $missing[] = $event;
            }
        }

        sort($missing);
        $this->assertSame([], $missing, 'Audit-Events ohne Label in lang/*/audit-events.php: ' . implode(', ', $missing));
    }

    public function test_audit_catalogs_have_locale_parity(): void {
        $locales = array_values(array_filter(
            scandir($this->root() . '/lang') ?: [],
            fn (string $d): bool => is_file($this->root() . '/lang/' . $d . '/entity-types.php'),
        ));
        $this->assertContains('de', $locales);

        $offenders = [];
        foreach (['entity-types', 'audit-events'] as $file) {
            $reference = $this->flatten($this->catalog('de', $file));
            foreach ($locales as $locale) {
                if ($locale === 'de') {
                    continue;
                }
                $keys = $this->flatten($this->catalog($locale, $file));
                foreach (array_keys(array_diff_key($reference, $keys)) as $key) {
                    $offenders[] = "$locale/$file: $key fehlt";
                }
                foreach (array_keys(array_diff_key($keys, $reference)) as $key) {
                    $offenders[] = "$locale/$file: $key überzählig";
                }
            }
        }

        $this->assertSame([], $offenders, 'Katalog-Parität verletzt: ' . implode('; ', array_slice($offenders, 0, 15)));
    }

    /** @return list<string> */
    private function collectEventStrings(): array {
        $events = [];
        foreach ($this->appFiles() as $path) {
            $src = (string) file_get_contents($path);

            // audit('event', …) — Event als erstes String-Literal-Argument.
            if (preg_match_all('/(?:->|::)audit\s*\(\s*\'([a-z][a-zA-Z0-9_.]*)\'/', $src, $m)) {
                array_push($events, ...$m[1]);
            }

            // Helfer mit Event an späterer Position: erstes gepunktetes
            // String-Literal innerhalb der audit(…)-Argumente.
            if (preg_match_all('/\baudit\s*\(((?:[^()]|\([^()]*\))*)\)/s', $src, $m)) {
                foreach ($m[1] as $args) {
                    if (preg_match('/\'([a-z][a-zA-Z0-9_]*(?:\.[a-zA-Z0-9_]+)+)\'/', $args, $mm)) {
                        $events[] = $mm[1];
                    }
                }
            }

            // Direkte Creates: AuditLog::create/AuditLog::query()->create.
            if (str_contains($src, 'AuditLog') && preg_match_all('/\'event\'\s*=>\s*\'([^\']+)\'/', $src, $m)) {
                array_push($events, ...$m[1]);
            }
        }

        $events = array_values(array_unique(array_filter(
            $events,
            // Konkatenation-Präfixe ('role.' . $event) und bekannte
            // Array-Schlüssel-Fänge aussortieren.
            fn (string $e): bool => ! str_ends_with($e, '.') && ! in_array($e, self::IGNORED_EVENT_STRINGS, true),
        )));
        sort($events);

        return $events;
    }

    /** @return array<string, mixed> */
    private function catalog(string $locale, string $file): array {
        return require $this->root() . '/lang/' . $locale . '/' . $file . '.php';
    }

    /** @param array<string, mixed> $catalog */
    private function resolve(array $catalog, string $key): ?string {
        $node = $catalog;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        return is_string($node) ? $node : null;
    }

    /**
     * @param array<string, mixed> $catalog
     * @return array<string, true> flache Schlüsselmenge in Punktnotation
     */
    private function flatten(array $catalog, string $prefix = ''): array {
        $keys = [];
        foreach ($catalog as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $keys += $this->flatten($value, $full);
            } else {
                $keys[$full] = true;
            }
        }

        return $keys;
    }

    /** @return list<string> */
    private function appFiles(): array {
        if (self::$phpFiles !== null) {
            return self::$phpFiles;
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root() . '/app', RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return self::$phpFiles = $files;
    }

    private function root(): string {
        return (string) realpath(__DIR__ . '/../../..');
    }
}
