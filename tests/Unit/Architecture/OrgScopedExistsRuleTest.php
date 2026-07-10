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

        $modelsDir = (string) realpath(__DIR__ . '/../../../app/Models');
        foreach ($this->phpFiles($modelsDir) as $file) {
            $class = 'App\\Models\\' . str_replace(
                [$modelsDir . DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR],
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
