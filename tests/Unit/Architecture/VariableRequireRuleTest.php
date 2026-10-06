<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VariableRequireRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „kein `require`/`include` mit variablem Pfad"
 * (Sicherheitsaudit 2026-10-04, authz-b-1 und xi-2).
 *
 * Der Code eines importierten Branchenprofils landete ungeprüft in vier
 * `require`-Pfaden. Jede Stelle, die eine Datei über einen berechneten Pfad
 * einbindet, steht deshalb unten mit der Herkunft des Pfadteils. Neue Stellen
 * nutzen einen vorhandenen Lader (z. B. `App\Support\BranchProfileFiles`) oder
 * kommen mit Begründung in die Liste.
 */
class VariableRequireRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Datei → Herkunft des Pfadteils. */
    private const ALLOWED = [
        'app/Support/BranchProfileFiles.php' => 'Zentraler Lader: Code gegen CODE_PATTERN geprüft, Verzeichnis fest.',
        'app/Http/Controllers/Admin/BranchProfileController.php' => 'Verzeichnisliste der mitgelieferten Profile.',
        'app/Services/Accounting/ChartOfAccountsTemplateService.php' => 'Verzeichnisliste der mitgelieferten Kontenrahmen.',
        'app/Services/Isms/NormProfileRegistry.php' => 'Verzeichnisliste der mitgelieferten Normprofile.',
        'app/Services/Isms/CrosswalkRegistry.php' => 'Verzeichnisliste der mitgelieferten Crosswalks.',
        'app/Services/Club/ClubStarterPackService.php' => 'Verzeichnisliste bzw. Code nach preg_replace auf [a-z0-9-].',
        'app/Services/Licensing/LicenseSeal.php' => 'Pfad aus config(license.seal_path), nur vom Betreiber gesetzt.',
        'app/Support/Translations.php' => 'Sprachdateien des Repos, nur aus den lang:*-Konsolenkommandos.',
        'app/Console/Commands/Architecture/MorphMapGenerateCommand.php' => 'Fester Pfad config/morph-map.php in einer Variablen.',
        'app/Modules/ModuleRegistry.php' => 'Cache-Datei der Manifeste, vom Deploy geschrieben.',
    ];

    public function test_require_and_include_use_fixed_paths(): void {
        $found = [];
        foreach ([...$this->phpFiles('app'), ...$this->phpFiles('database/seeders'), ...$this->phpFiles('routes'), ...$this->phpFiles('config')] as $file) {
            if (str_ends_with($file, '.blade.php')) {
                continue;
            }
            $relative = $this->relativePath($file);
            foreach ($this->variableIncludes((string) file_get_contents($file)) as $line) {
                $found[$relative][] = $line;
            }
        }

        $violations = [];
        foreach ($found as $relative => $lines) {
            if (! isset(self::ALLOWED[$relative])) {
                $violations[] = $relative . ':' . implode(',', $lines);
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "require/include mit variablem Pfad. Einen vorhandenen Lader nutzen "
            . "(z. B. App\\Support\\BranchProfileFiles) oder die Datei mit der Herkunft des Pfadteils in ALLOWED eintragen:\n"
            . implode("\n", $violations));

        $stale = array_values(array_diff(array_keys(self::ALLOWED), array_keys($found)));
        $this->assertSame([], $stale, "ALLOWED-Eintrag ohne variable Einbindung — bitte streichen:\n" . implode("\n", $stale));
    }

    /** @return list<int> Zeilen der Einbindungen, deren Pfadausdruck eine Variable enthält. */
    private function variableIncludes(string $source): array {
        $tokens = token_get_all($source);
        $lines = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (! is_array($token) || ! in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
                continue;
            }
            for ($j = $i + 1; $j < $count && $tokens[$j] !== ';'; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_VARIABLE) {
                    $lines[] = $token[2];
                    break;
                }
            }
        }

        return $lines;
    }
}
