<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommandTestCoverageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „jeder Console-Command hat einen Test" (Vollscan
 * 2026-08-23, D7 / MVP-725).
 *
 * Ein Command gilt als abgedeckt, wenn irgendeine Testdatei seine Klasse
 * (FQCN oder Kurzname) oder seine Signatur nennt. Nicht mitgezählt werden
 * Dateien, die Commands nur AUFZÄHLEN statt sie auszuführen — die frozen
 * Scheduler-Registrierung und die Architektur-Gates selbst (dieses
 * eingeschlossen). Sonst hätte jeder Scheduler-Eintrag automatisch einen
 * „Test", ohne dass je jemand das Kommando gestartet hätte; genau diese
 * Scheinabdeckung war der Befund.
 *
 * Die BASELINE ist die Liste begründeter Ausnahmen und darf nur **schrumpfen**:
 * `test_baseline_has_no_stale_entries` schlägt an, sobald ein gelisteter
 * Command doch einen Test bekommt — der Eintrag muss dann raus.
 */
class CommandTestCoverageTest extends TestCase {
    use ScansSourceTree;

    /**
     * Testdateien/-verzeichnisse, die Commands nur aufzählen (Registrierung,
     * Doku-Parität, Namens-Sweeps) — keine Verhaltensabdeckung.
     *
     * @var list<string>
     */
    private const REGISTRY_ONLY = [
        'tests/Unit/Architecture/',
        'tests/Feature/Scheduling/SchedulerRegistrationTest.php',
    ];

    /**
     * Begründete Ausnahmen: FQCN → Grund. **Nur kürzer werden.**
     *
     * @var array<class-string, string>
     */
    private const BASELINE = [];

    public function test_every_console_command_has_a_referencing_test(): void {
        $uncovered = $this->uncoveredCommands();
        $new = array_diff_key($uncovered, self::BASELINE);

        $this->assertSame([], array_keys($new), "Console-Commands ohne Test:\n"
            . implode("\n", array_map(
                static fn (string $class, string $signature): string => sprintf('  %s (%s)', $class, $signature),
                array_keys($new),
                array_values($new),
            ))
            . "\n\nJeder Command braucht mindestens: Lauf mit Minimaldaten, Guard-/Fehlerpfad und einen geprüften\n"
            . "Seiteneffekt (Muster: tests/Feature/Console/*). Ist ein Command nachweislich nicht testbar,\n"
            . 'gehört er mit Begründung in die BASELINE dieses Gates.');
    }

    public function test_baseline_has_no_stale_entries(): void {
        $uncovered = $this->uncoveredCommands();
        $stale = array_diff(array_keys(self::BASELINE), array_keys($uncovered));

        $this->assertSame([], array_values($stale), 'Diese BASELINE-Einträge haben inzwischen einen Test — '
            . "Eintrag entfernen (die Ausnahmeliste darf nur schrumpfen):\n" . implode("\n", $stale));
    }

    public function test_the_command_inventory_is_actually_scanned(): void {
        // Schutz gegen ein stilles Leerlaufen des Gates (falscher Pfad, kaputte
        // Regex): das Repo hat weit über hundert Commands.
        $this->assertGreaterThan(100, count($this->commands()));
    }

    /**
     * Nicht abgedeckte Commands: FQCN → Signatur.
     *
     * @return array<class-string, string>
     */
    private function uncoveredCommands(): array {
        $tests = $this->sourcesOfBehaviourTests();

        $uncovered = [];
        foreach ($this->commands() as $class => $command) {
            foreach ($tests as $source) {
                // Bewusst nur FQCN + Signatur: der bloße Klassen-Kurzname
                // (ListCommand, SeedRoles, Preflight …) trifft zu oft
                // unbeteiligten Text und täuscht Abdeckung vor.
                if (str_contains($source, $command['fqcn'])
                    || ($command['signature'] !== '' && str_contains($source, $command['signature']))) {
                    continue 2;
                }
            }
            $uncovered[$class] = $command['signature'];
        }

        ksort($uncovered);

        return $uncovered;
    }

    /**
     * Konkrete Commands: FQCN → Metadaten.
     *
     * @return array<class-string, array{fqcn: string, basename: string, signature: string}>
     */
    private function commands(): array {
        $commands = [];
        foreach ($this->phpFiles('app/Console/Commands') as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match('/^abstract class /m', $source) === 1) {
                continue;
            }
            if (preg_match('/^class\s+(\w+)/m', $source, $m) !== 1) {
                continue;
            }
            $basename = $m[1];
            $namespace = preg_match('/^namespace\s+([^;]+);/m', $source, $n) === 1 ? trim($n[1]) : '';
            $fqcn = $namespace === '' ? $basename : $namespace . '\\' . $basename;

            $signature = preg_match('/\$signature\s*=\s*[\'"]([^\s\'"{]+)/', $source, $s) === 1 ? $s[1] : '';

            /** @var class-string $fqcn */
            $commands[$fqcn] = ['fqcn' => $fqcn, 'basename' => $basename, 'signature' => $signature];
        }

        ksort($commands);

        return $commands;
    }

    /**
     * Inhalte aller Testdateien, die Verhalten prüfen (siehe REGISTRY_ONLY).
     *
     * @return list<string>
     */
    private function sourcesOfBehaviourTests(): array {
        $sources = [];
        foreach ($this->phpFiles('tests') as $file) {
            $relative = $this->relativePath($file);
            foreach (self::REGISTRY_ONLY as $prefix) {
                if (str_starts_with($relative, $prefix)) {
                    continue 2;
                }
            }
            $sources[] = (string) file_get_contents($file);
        }

        return $sources;
    }
}
