<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawMorphClassLiteralRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „kein Klassenname als Morph-Wert" (MVP-860): Polymorphe
 * Typspalten tragen den Alias aus der Morph-Map. Ein `X::class`, `$x::class`
 * oder `get_class($x)` als Wert oder Vergleich einer `*_type`-Spalte schriebe
 * bzw. suchte den Klassennamen und fände nach dem Umschreiben nichts mehr.
 *
 * Richtig: `MorphMap::alias(X::class)`, `$x->getMorphClass()`,
 * `MorphMap::is($row->subject_type, X::class)`; für die hash-verketteten
 * Audit-Tabellen `MorphMap::stableKey(X::class)`. Enum-Casts
 * (`'status_type' => StatusType::class`) sind keine Morph-Werte und werden
 * über die Klassenart unterschieden.
 */
class RawMorphClassLiteralRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad-Präfix → Begründung */
    private const ALLOWLIST = [
        // Report-Exporte schreiben den Controller als Träger ins Audit-Log —
        // kein Modell, kein Morph-Ziel (AuditLogController lädt 'auditable'
        // deshalb nie eager).
        'app/Http/Controllers/Reporting/Concerns/WritesReportCsv.php' => 'self::class ist ein Controller, kein Modell.',
        'app/Http/Controllers/Article/ArticleExportController.php' => 'self::class ist ein Controller, kein Modell.',
        // Prüft selbst per Substring auf das alte Muster.
        'tests/Unit/Architecture/AuditTranslationCoverageTest.php' => 'Gate-Quelltext, keine Schreibstelle.',
        'tests/Unit/Architecture/RawMorphClassLiteralRuleTest.php' => 'Dieses Gate.',
    ];

    public function test_no_class_names_as_morph_values(): void {
        $violations = [];
        foreach (['app', 'database', 'tests'] as $directory) {
            foreach ($this->phpFiles($directory) as $file) {
                $relative = $this->relativePath($file);
                if ($this->isAllowListed($relative, self::ALLOWLIST)) {
                    continue;
                }
                $source = $this->stripComments((string) file_get_contents($file));
                foreach ($this->findings($source) as [$offset, $snippet]) {
                    $violations[] = sprintf('%s:%d — %s', $relative, $this->lineOf($source, $offset), $snippet);
                }
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "Klassenname als Morph-Wert (MVP-860, Morph-Map):\n" . implode("\n", $violations));
    }

    /** @return list<array{int, string}> */
    private function findings(string $source): array {
        $out = [];
        $imports = $this->imports($source);

        // 'x_type' => X::class | where('t.x_type', X::class) | where('x_type', '!=', X::class)
        // | 'x_type' => $y !== null ? X::class : null — nur wenn X ein Eloquent-Modell ist.
        if (preg_match_all('/\'[\w.]+_type\'\s*(?:=>|,)\s*(?:\'[!=<>]+\'\s*,\s*)?(?:\$\w+\s*[!=]==\s*null\s*\?\s*)?(\\\\?[A-Z][\w\\\\]*)::class/', $source, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
            foreach ($m as $hit) {
                if ($this->isModel($this->resolve($hit[1][0], $imports))) {
                    $out[] = [$hit[0][1], $hit[0][0]];
                }
            }
        }
        // $row->x_type === X::class
        if (preg_match_all('/\$[\w>\-\[\]\']+_type\s*[!=]==\s*(\\\\?[A-Z][\w\\\\]*)::class/', $source, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
            foreach ($m as $hit) {
                if ($this->isModel($this->resolve($hit[1][0], $imports))) {
                    $out[] = [$hit[0][1], $hit[0][0]];
                }
            }
        }
        // 'x_type' => $x::class | $x->y::class | get_class($x) | static::class  und  where('x_type', $x::class | get_class($x))
        if (preg_match_all('/(?:\'[\w.]+_type\'\s*=>\s*|where\(\'[\w.]+_type\',\s*)(?:\$[\w>\-\[\]\']+::class|get_class\(\$\w+\)|static::class)/', $source, $m, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($m[0] as [$snippet, $offset]) {
                $out[] = [$offset, $snippet];
            }
        }

        return $out;
    }

    /** @return array<string, string> Kurzname → FQCN aus use-Statements plus Namespace */
    private function imports(string $source): array {
        $map = [];
        if (preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $source, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as $u) {
                $map[$u[2] ?? substr((string) strrchr('\\' . $u[1], '\\'), 1)] = $u[1];
            }
        }
        if (preg_match_all('/^use\s+([\w\\\\]+)\\\\\{([^}]+)\};/m', $source, $g, PREG_SET_ORDER) > 0) {
            foreach ($g as $u) {
                foreach (explode(',', $u[2]) as $part) {
                    $part = trim($part);
                    if ($part !== '' && preg_match('/^([\w\\\\]+)(?:\s+as\s+(\w+))?$/', $part, $p) === 1) {
                        $fq = $u[1] . '\\' . $p[1];
                        $map[$p[2] ?? substr((string) strrchr('\\' . $fq, '\\'), 1)] = $fq;
                    }
                }
            }
        }
        $map[''] = preg_match('/^namespace\s+([\w\\\\]+);/m', $source, $ns) === 1 ? $ns[1] : '';

        return $map;
    }

    /** @param array<string, string> $imports */
    private function resolve(string $name, array $imports): string {
        if (str_starts_with($name, '\\')) {
            return substr($name, 1);
        }
        $first = explode('\\', $name)[0];
        if (isset($imports[$first])) {
            return $imports[$first] . substr($name, strlen($first));
        }

        return $imports[''] !== '' ? $imports[''] . '\\' . $name : $name;
    }

    private function isModel(string $class): bool {
        return class_exists($class) && is_subclass_of($class, Model::class);
    }
}
