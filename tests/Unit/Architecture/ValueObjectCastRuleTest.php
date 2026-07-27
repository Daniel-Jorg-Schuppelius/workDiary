<?php
/*
 * Created on   : Sun Jul 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValueObjectCastRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Architektur-Gate für die Value-Object-Casts (`CommonToolkit\ValueObjects\*`).
 *
 * Ein VO-Attribut ist ein Objekt, kein Skalar: `number_format($invoice->total)`
 * wirft dann, und `$article->gtin === '4006381333931'` ist immer false — beides
 * still, weil PHP nicht meckert (dieselbe Falle wie bei Enum-Casts).
 *
 * Gescannt werden nur Attributnamen, die projektweit ausschließlich VO-gecastet
 * sind; Namen wie `amount`, die anderswo noch `decimal:2` sind, bleiben außen vor.
 */
class ValueObjectCastRuleTest extends TestCase {
    /**
     * Bewusste Ausnahmen: "<pfad-suffix>:<attribut>".
     * Erweiterungen bitte mit Begründung kommentieren.
     *
     * @var array<int, string>
     */
    private const ALLOW_LIST = [
    ];

    /** Alle Cast-Klassen aus App\Casts, die in Models referenziert werden, müssen existieren. */
    public function test_referenced_casts_exist(): void {
        $missing = [];

        foreach ($this->modelFiles() as $file) {
            $content = (string) file_get_contents($file);
            if (preg_match_all('/([A-Za-z]+Cast)::class/', $content, $m)) {
                foreach (array_unique($m[1]) as $cast) {
                    $fqcn = 'App\\Casts\\' . $cast;
                    if (!class_exists($fqcn)) {
                        $missing[] = basename($file) . ' → ' . $fqcn;
                    }
                }
            }
        }

        $this->assertSame([], $missing, "Nicht auflösbare Cast-Klassen:\n" . implode("\n", $missing));
    }

    /** Zahlenformatierung gehört auf das VO (`->format()`), nicht auf number_format(). */
    public function test_no_number_format_on_value_object_attributes(): void {
        $attributes = $this->exclusivelyValueObjectAttributes();
        if ($attributes === []) {
            $this->markTestSkipped('Noch keine VO-Casts verdrahtet.');
        }

        // Der Lookahead lässt `number_format($x->tax_rate->getNumericValue(), …)` durch:
        // formatiert wird dort der Skalar, nicht das Value Object.
        $pattern = '/number_format\(\s*\$[A-Za-z_][A-Za-z0-9_]*(?:->[A-Za-z_][A-Za-z0-9_]*)*->(' . implode('|', $attributes) . ')\b(?!\s*(?:\?->|->))/';

        $this->assertSame([], $this->scan($pattern), 'number_format() auf VO-Attribut — stattdessen ->format() des Value Objects nutzen.');
    }

    /** VO === 'string' ist immer false — auf ->getValue()/->equals() vergleichen. */
    public function test_no_string_comparison_on_value_object_attributes(): void {
        $attributes = $this->exclusivelyValueObjectAttributes();
        if ($attributes === []) {
            $this->markTestSkipped('Noch keine VO-Casts verdrahtet.');
        }

        $pattern = '/->(' . implode('|', $attributes) . ')\s*[!=]==?\s*[\'"]/';

        $this->assertSame([], $this->scan($pattern), 'Stringvergleich gegen VO-Attribut — ->getValue() oder ->equals() nutzen.');
    }

    /**
     * Attributnamen, die in jedem Model mit VO-Cast belegt sind und nirgends
     * mehr als Skalar-Cast auftauchen.
     *
     * @return array<int, string>
     */
    private function exclusivelyValueObjectAttributes(): array {
        $viaValueObject = [];
        $viaScalar = [];

        foreach ($this->modelFiles() as $file) {
            $content = (string) file_get_contents($file);

            if (preg_match_all('/[\'"]([a-z_0-9]+)[\'"]\s*=>\s*[A-Za-z]+Cast::class/', $content, $m)) {
                foreach ($m[1] as $attribute) {
                    $viaValueObject[$attribute] = true;
                }
            }
            if (preg_match_all('/[\'"]([a-z_0-9]+)[\'"]\s*=>\s*[\'"](?:decimal:[0-9]+|integer|int|float|double|string)[\'"]/', $content, $m)) {
                foreach ($m[1] as $attribute) {
                    $viaScalar[$attribute] = true;
                }
            }
        }

        $exclusive = array_keys(array_diff_key($viaValueObject, $viaScalar));
        sort($exclusive);

        return array_map('preg_quote', $exclusive);
    }

    /**
     * @return array<int, string> Fundstellen als "pfad:zeile"
     */
    private function scan(string $pattern): array {
        $root = (string) realpath(__DIR__ . '/../../..');
        $violations = [];

        foreach ([$root . '/app', $root . '/resources/views'] as $dir) {
            foreach ($this->phpFiles($dir) as $file) {
                if (str_contains($file, DIRECTORY_SEPARATOR . 'Legacy' . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $relative = ltrim(str_replace([$root, DIRECTORY_SEPARATOR], ['', '/'], $file), '/');
                foreach (file($file) ?: [] as $index => $line) {
                    if (preg_match($pattern, $line, $m) && !$this->isAllowed($relative, $m[1])) {
                        $violations[] = $relative . ':' . ($index + 1);
                    }
                }
            }
        }

        return $violations;
    }

    private function isAllowed(string $relative, string $attribute): bool {
        foreach (self::ALLOW_LIST as $entry) {
            [$suffix, $allowedAttribute] = explode(':', $entry, 2);
            if ($allowedAttribute === $attribute && str_ends_with($relative, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function modelFiles(): array {
        return $this->phpFiles((string) realpath(__DIR__ . '/../../../app/Models'));
    }

    /**
     * @return array<int, string>
     */
    private function phpFiles(string $directory): array {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && in_array($file->getExtension(), ['php'], true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
