<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftDeleteUniqueUpsertRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use Illuminate\Database\Eloquent\SoftDeletes;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „SoftDeletes + Unique ⇒ withTrashed beim Upsert" (Vollscan
 * 2026-08-23, F15; Memory „SoftDelete blockiert Unique-Index"): firstOrCreate/
 * updateOrCreate/firstOrNew sehen den soft-gelöschten Datensatz nicht, der
 * Unique-Index aber schon — Ergebnis ist ein 1062 statt einer Wiederbelebung.
 *
 * Regel: Für Modelle mit SoftDeletes, deren Tabelle einen Unique-Index trägt,
 * enthält jeder Upsert-Aufruf (`Model::…firstOrCreate(` usw.) in derselben
 * Anweisung ein `withTrashed()` (+ restore()).
 */
class SoftDeleteUniqueUpsertRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad → Begründung */
    private const ALLOW_LIST = [];

    public function test_upserts_on_soft_deleted_unique_models_include_trashed_rows(): void {
        $tables = $this->schemaTables();
        $guarded = [];
        foreach ($this->modelClasses() as $class) {
            if (! isset(class_uses_recursive($class)[SoftDeletes::class])) {
                continue;
            }
            $table = $this->tableOfModel($class);
            if ($table !== '' && isset($tables[$table]) && $tables[$table]['unique'] !== []) {
                $guarded[class_basename($class)] = $class;
            }
        }
        $this->assertNotEmpty($guarded);

        $names = implode('|', array_map('preg_quote', array_keys($guarded)));
        $violations = [];

        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }
            $source = $this->stripComments((string) file_get_contents($file));

            // Anweisung ab `Model::` bzw. `Model::query()` bis zum Semikolon.
            if (preg_match_all('/\b(' . $names . ')::(?:query\(\))?((?:->[\w]+\([^;]*?\))*?->(?:firstOrCreate|updateOrCreate|firstOrNew)\()/s', $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === 0) {
                continue;
            }

            foreach ($matches as $match) {
                // Nur, wenn der Basename in dieser Datei auf ein bewachtes Modell
                // zeigt (es gibt gleichnamige Modelle in verschiedenen Namespaces).
                if ($this->resolveImport($source, $match[1][0]) !== $guarded[$match[1][0]]) {
                    continue;
                }
                $statement = substr($source, (int) $match[0][1], strpos($source, ';', (int) $match[0][1]) - (int) $match[0][1]);
                if (str_contains($statement, 'withTrashed(')) {
                    continue;
                }
                $violations[] = sprintf('%s:%d — %s', $relative, $this->lineOf($source, (int) $match[0][1]), trim((string) preg_replace('/\s+/', ' ', substr($statement, 0, 120))));
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Upsert auf SoftDelete-Modell mit Unique-Index ohne withTrashed() — soft-gelöschte Zeile blockiert den Index (1062).\n"
            . "Muster: Model::withTrashed()->firstOrCreate(…) und anschließend restore(), wenn trashed().\n\n" . implode("\n", $violations));
    }

    /** FQCN, auf die ein Klassen-Basename in dieser Datei zeigt (use-Statements inkl. Gruppen-Imports). */
    private function resolveImport(string $source, string $basename): string {
        if (preg_match('/^use\s+([\w\\\\]+\\\\' . preg_quote($basename, '/') . ');/m', $source, $m) === 1) {
            return $m[1];
        }
        if (preg_match_all('/^use\s+([\w\\\\]+)\\\\\{([^}]+)\};/m', $source, $groups, PREG_SET_ORDER) > 0) {
            foreach ($groups as $group) {
                foreach (array_map('trim', explode(',', $group[2])) as $member) {
                    if ($member === $basename || str_ends_with($member, '\\' . $basename)) {
                        return $group[1] . '\\' . $member;
                    }
                }
            }
        }
        if (preg_match('/^namespace\s+([\w\\\\]+);/m', $source, $ns) === 1) {
            return $ns[1] . '\\' . $basename;
        }

        return $basename;
    }
}
