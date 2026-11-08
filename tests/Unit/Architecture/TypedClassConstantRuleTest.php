<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TypedClassConstantRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PhpParser\{Node, NodeFinder, Parser, ParserFactory};
use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Klassenkonstanten tragen ihren Typ (PHP 8.3): `private const string X = …`.
 * Eingeführt am 2026-11-08 für den gesamten Bestand; ein Wert, der nicht zum
 * Typ passt, fällt so schon beim Laden der Klasse auf.
 */
final class TypedClassConstantRuleTest extends TestCase {
    use ScansSourceTree;

    private const array DIRECTORIES = ['app', 'database', 'scripts', 'tests'];

    public function test_class_constants_declare_a_type(): void {
        $parser = (new ParserFactory)->createForHostVersion();
        $violations = [];
        foreach (self::DIRECTORIES as $directory) {
            foreach ($this->phpFiles($directory) as $file) {
                foreach ($this->untypedLines($parser, (string) file_get_contents($file)) as $line) {
                    $violations[] = sprintf('%s:%d', $this->relativePath($file), $line);
                }
            }
        }

        $this->assertSame([], $violations, "Klassenkonstante ohne Typ — den Typ des Werts angeben (`const string X = …`):\n" . implode("\n", $violations));
    }

    public function test_the_rule_tells_typed_from_untyped_constants(): void {
        $parser = (new ParserFactory)->createForHostVersion();

        $this->assertSame([1], $this->untypedLines($parser, '<?php class A { const X = 1; }'));
        $this->assertSame([], $this->untypedLines($parser, '<?php class A { private const ?string X = null; } const GLOBAL_FLAG = true;'));
    }

    /** @return list<int> */
    private function untypedLines(Parser $parser, string $source): array {
        // Vorfilter: getypte Konstanten (`const string X =`, `const ?string X =`) passen nicht.
        if (preg_match('/\bconst\s+[A-Za-z_]\w*\s*=/', $source) !== 1) {
            return [];
        }

        $lines = [];
        foreach ((new NodeFinder)->findInstanceOf($parser->parse($source) ?? [], Node\Stmt\ClassConst::class) as $const) {
            if ($const->type === null) {
                $lines[] = $const->getStartLine();
            }
        }

        return $lines;
    }
}
