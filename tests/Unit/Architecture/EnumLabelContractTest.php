<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnumLabelContractTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use App\Enums\Contracts\HasLabel;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionEnum;

/**
 * Architektur-Gate für den Label-Vertrag (konsolidierungs-audit-2026-07,
 * Befund D1): Jedes Enum in app/Enums mit eigener label()-Methode MUSS
 * App\Enums\Contracts\HasLabel implementieren — sonst fehlen options()/
 * values()/tryFromName() (HasOptions) und Aufrufer bauen ::cases()-Loops nach.
 *
 * Bewusste Ausnahmen (z. B. pure Enums — HasOptions verlangt BackedEnum)
 * gehören mit Begründung in die WHITELIST.
 */
class EnumLabelContractTest extends TestCase {
    /**
     * Bewusst ausgenommene Enums: FQCN → Begründung.
     *
     * @var array<class-string, string>
     */
    private const WHITELIST = [];

    /**
     * Gescannte Wurzeln: Verzeichnis (relativ zum Repo) → Namespace-Präfix.
     * Vollaudit 2026-07 (N54): ganz app/ statt nur app/Enums — gelabelte
     * Enums leben auch in Scheduling/Settings/Plugins.
     *
     * @var array<string, string>
     */
    private const SCAN_ROOTS = [
        'app' => 'App\\',
    ];

    public function test_every_labelled_enum_implements_has_label(): void {
        $root = (string) realpath(__DIR__ . '/../../..');
        $violations = [];

        foreach ($this->allEnumClasses($root) as $fqcn) {
            if (array_key_exists($fqcn, self::WHITELIST)) {
                continue;
            }

            $reflection = new ReflectionEnum($fqcn);

            if (!$reflection->hasMethod('label')) {
                continue;
            }

            if (!$reflection->implementsInterface(HasLabel::class)) {
                $violations[] = $fqcn;
            }
        }

        sort($violations);

        $this->assertSame([], $violations, sprintf(
            "Enum mit label()-Methode ohne HasLabel-Vertrag gefunden (Befund D1).\n"
                . "Fix: `implements HasLabel` + `use HasOptions;` ergänzen (backed Enums)\n"
                . "oder das Enum mit fachlicher Begründung in die WHITELIST eintragen:\n%s",
            implode("\n", $violations),
        ));
    }

    /**
     * Alle Enum-FQCNs unter den SCAN_ROOTS (PSR-4-Auflösung je Wurzel).
     *
     * @return iterable<class-string>
     */
    private function allEnumClasses(string $root): iterable {
        foreach (self::SCAN_ROOTS as $dir => $prefix) {
            yield from $this->enumClasses($root . DIRECTORY_SEPARATOR . $dir, $prefix);
        }
    }

    /**
     * Alle Enum-FQCNs unter $dir (PSR-4: $prefix + relativer Pfad).
     *
     * @return iterable<class-string>
     */
    private function enumClasses(string $dir, string $prefix = 'App\\Enums\\'): iterable {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($dir) + 1, -4);
            /** @var class-string $fqcn */
            $fqcn = $prefix . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

            // Nicht-Klassendateien (Views/Configs in Plugins) und Interfaces/
            // Traits fallen durch enum_exists; class_exists-Autoload-Fehler
            // vermeiden wir über den vorherigen Dateiinhalts-Check.
            if (! str_contains((string) file_get_contents($file->getPathname()), 'enum ' . basename($relative))) {
                continue;
            }
            if (enum_exists($fqcn)) {
                yield $fqcn;
            }
        }
    }
}
