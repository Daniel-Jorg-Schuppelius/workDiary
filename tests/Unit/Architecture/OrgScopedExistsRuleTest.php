<?php
/*
 * Created on   : Thu Jul 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgScopedExistsRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Architektur-Gate gegen Cross-Tenant-Injection (Whitebox-Review 2026-07):
 * Fremdschlüssel-Validierung auf mandantengebundene Tabellen darf kein
 * rohes `exists:tabelle,id` / `Rule::exists('tabelle', ...)` ohne
 * Org-Einschränkung nutzen — stattdessen ExistsInCurrentOrganization
 * oder `Rule::exists()->where(organization_id/Parent-Subquery)`.
 *
 * Erfasst werden nur String-Literale; `Rule::exists((new X)->getTable(),...)`
 * bleibt Review-Sache. app/Legacy ist ausgenommen (separate Alt-DB).
 */
class OrgScopedExistsRuleTest extends TestCase {
    /**
     * Bewusst rohe exists-Verwendungen: "<datei-suffix>:<tabelle>".
     * Erweiterungen bitte mit Begründung kommentieren.
     *
     * @var array<int, string>
     */
    private const ALLOW_LIST = [
        // (aktuell leer — alle Fundstellen des Audits 2026-07-09 sind umgestellt)
    ];

    /**
     * Bewusst installationsweite unique-Regeln: "<datei>:<tabelle>" => Grund.
     *
     * @var array<string, string>
     */
    private const UNIQUE_ALLOW_LIST = [
        'app/Http/Controllers/Platform/OrgMemberController.php:users' => 'Die E-Mail-Adresse ist die Anmeldekennung und installationsweit eindeutig.',
        'app/Http/Controllers/Platform/ProfileController.php:users' => 'Die E-Mail-Adresse ist die Anmeldekennung und installationsweit eindeutig.',
        'app/Http/Controllers/Auth/TenantRegistrationController.php:users' => 'Die E-Mail-Adresse ist die Anmeldekennung und installationsweit eindeutig.',
    ];

    /**
     * Tabellen ohne eigene organization_id, deren Mandantengrenze über das
     * Parent-Aggregat läuft — rohe exists sind auch hier verboten (Scoping
     * per Parent-Subquery, s. SaveArticleRequest/ProtocolController).
     *
     * @var array<int, string>
     */
    private const INDIRECTLY_SCOPED_TABLES = [
        'protocol_items',
        'procedure_template_versions',
    ];

    public function test_no_raw_exists_rule_targets_org_scoped_tables(): void {
        $appDir = (string) realpath(__DIR__ . '/../../../app');
        $orgTables = $this->organizationScopedTables();
        $violations = [];

        foreach ($this->phpFiles($appDir) as $file) {
            if (str_contains($file, DIRECTORY_SEPARATOR . 'Legacy' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $content = (string) file_get_contents($file);
            // Mehrzeilige Rule-Ketten für die ->where-Erkennung glätten.
            $flat = (string) preg_replace('/\s+/', ' ', $content);
            $relative = 'app' . str_replace([$appDir, DIRECTORY_SEPARATOR], ['', '/'], $file);

            // String-Form 'exists:tabelle,...' — hat nie einen Org-Constraint.
            if (preg_match_all("/'exists:([a-z_]+)[,']/", $content, $m)) {
                foreach ($m[1] as $table) {
                    if ($this->isViolation($relative, $table, $orgTables)) {
                        $violations[] = "$relative — 'exists:$table' (String-Regel)";
                    }
                }
            }

            // Rule::exists('tabelle', ...) ohne direkt folgendes ->where(...).
            if (preg_match_all("/Rule::exists\\(\\s*'([a-z_]+)'\\s*,\\s*'[a-z_]+'\\s*\\)(?! ?->where)/", $flat, $m)) {
                foreach ($m[1] as $table) {
                    if ($this->isViolation($relative, $table, $orgTables)) {
                        $violations[] = "$relative — Rule::exists('$table') ohne Org-Constraint";
                    }
                }
            }
        }

        $this->assertSame([], $violations, sprintf(
            "Rohe exists-Validierung auf mandantengebundene Tabellen gefunden (Cross-Tenant-Risiko).\n"
            . "ExistsInCurrentOrganization bzw. ->where(organization_id) verwenden oder begründet in die Allow-List eintragen:\n%s",
            implode("\n", $violations),
        ));
    }

    /**
     * Sicherheitsaudit 2026-10-04 (xi-7): `unique` fragt die Tabelle ohne
     * globalen Scope ab. Ohne Mandantenbezug verrät die Meldung, dass irgendein
     * Mandant den Wert führt, und sperrt ihn für alle anderen.
     */
    public function test_no_raw_unique_rule_targets_org_scoped_tables(): void {
        $appDir = (string) realpath(__DIR__ . '/../../../app');
        $orgTables = $this->organizationScopedTables();
        $violations = [];
        $seen = [];

        foreach ($this->phpFiles($appDir) as $file) {
            if (str_contains($file, DIRECTORY_SEPARATOR . 'Legacy' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $content = (string) file_get_contents($file);
            $flat = (string) preg_replace('/\s+/', ' ', $content);
            $relative = 'app' . str_replace([$appDir, DIRECTORY_SEPARATOR], ['', '/'], $file);

            $tables = [];
            if (preg_match_all("/'unique:([a-z_]+)/", $content, $m) > 0) {
                foreach ($m[1] as $table) {
                    $tables[] = [$table, "'unique:$table' (String-Regel)"];
                }
            }
            // Die Kette muss einen ->where(...) tragen: Organisation oder Eltern-Datensatz.
            // Betrachtet wird der Text bis zur nächsten Regel (->ignore() darf davor stehen).
            if (preg_match_all("/Rule::unique\\(\\s*'([a-z_]+)'/", $flat, $m, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($m[1] as [$table, $offset]) {
                    $chain = substr($flat, $offset, 400);
                    $next = strpos($chain, 'Rule::');
                    if (! str_contains($next === false ? $chain : substr($chain, 0, $next), '->where(')) {
                        $tables[] = [$table, "Rule::unique('$table') ohne Mandantenbezug"];
                    }
                }
            }

            foreach ($tables as [$table, $label]) {
                if (! in_array($table, $orgTables, true)) {
                    continue;
                }
                $key = $relative . ':' . $table;
                if (isset(self::UNIQUE_ALLOW_LIST[$key])) {
                    $seen[$key] = true;

                    continue;
                }
                $violations[] = "$key — $label";
            }
        }

        $this->assertSame([], $violations, "unique-Validierung auf mandantengebundene Tabellen ohne Mandantenbezug — Rule::unique(...)->where('organization_id', …) verwenden:\n" . implode("\n", $violations));
        $this->assertSame([], array_values(array_diff(array_keys(self::UNIQUE_ALLOW_LIST), array_keys($seen))), 'Veraltete Ausnahmen in UNIQUE_ALLOW_LIST.');
    }

    /** @param array<int, string> $orgTables */
    private function isViolation(string $relativeFile, string $table, array $orgTables): bool {
        if (! in_array($table, $orgTables, true)) {
            return false;
        }

        return ! in_array($relativeFile . ':' . $table, self::ALLOW_LIST, true);
    }

    /** @return array<int, string> */
    private function organizationScopedTables(): array {
        $tables = self::INDIRECTLY_SCOPED_TABLES;
        // users/classifications tragen organization_id ohne Trait (kein
        // Global Scope) — genau der Fall, den ExistsInCurrentOrganization schließt.
        $tables[] = 'users';
        $tables[] = 'classifications';

        $appDir = (string) realpath(__DIR__ . '/../../../app');
        $files = $this->phpFiles($appDir . '/Models');
        foreach (glob($appDir . '/Plugins/*/Models', GLOB_ONLYDIR) ?: [] as $pluginModels) {
            $files = [...$files, ...$this->phpFiles($pluginModels)];
        }
        foreach ($files as $file) {
            $class = 'App\\' . str_replace(
                [$appDir . DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR],
                ['', '', '\\'],
                $file,
            );
            if (! class_exists($class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if (! $reflection->isInstantiable() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }
            if (! in_array(BelongsToOrganization::class, $this->allTraits($reflection), true)) {
                continue;
            }
            /** @var Model $model */
            $model = $reflection->newInstanceWithoutConstructor();
            $tables[] = $model->getTable();
        }

        return array_values(array_unique($tables));
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return array<int, string>
     */
    private function allTraits(ReflectionClass $reflection): array {
        $traits = [];
        $current = $reflection;
        while ($current !== false) {
            $traits = array_merge($traits, array_keys($current->getTraits()));
            $current = $current->getParentClass();
        }

        return $traits;
    }

    /** @return array<int, string> */
    private function phpFiles(string $directory): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
