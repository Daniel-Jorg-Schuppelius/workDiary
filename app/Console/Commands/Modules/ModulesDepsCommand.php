<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModulesDepsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Modules;

use App\Modules\{BoundaryRules, DependencyGraph, ModuleRegistry};
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Console\Command;

/**
 * Modulkanten und Verstöße gegen die Abhängigkeitsregel (MVP-863). `--baseline`
 * schreibt den Bestand als Allowlist des Gates `ModuleBoundaryRuleTest`; die
 * Datei darf danach nur schrumpfen.
 */
class ModulesDepsCommand extends Command {
    protected $signature = 'modules:deps {--baseline : Verstöße als Baseline schreiben} {--all : Alle Kanten ausgeben, nicht nur Verstöße}';

    protected $description = 'Zeigt Abhängigkeiten zwischen Modulen und Verstöße gegen die Modulregel (Plattform/Kern/Feature).';

    public const BASELINE = 'tests/Unit/Architecture/baselines/module-boundaries.php';

    public function handle(ModuleRegistry $registry): int {
        $graph = new DependencyGraph($registry, new BoundaryRules($registry), base_path());
        $edges = $graph->edges();
        $violations = array_values(array_filter($edges, static fn (array $e): bool => ! $e['allowed']));

        $pairs = [];
        foreach ($edges as $edge) {
            $pairs[$edge['from'] . '→' . $edge['to']] = ($pairs[$edge['from'] . '→' . $edge['to']] ?? 0) + 1;
        }
        $this->info(sprintf('%d Kanten zwischen %d Modulpaaren, davon %d Verstöße.', count($edges), count($pairs), count($violations)));

        $rows = $this->option('all') ? $edges : $violations;
        $grouped = [];
        foreach ($rows as $edge) {
            $grouped[$edge['reason']][] = $edge['file'] . ' → ' . class_basename($edge['class']);
        }
        ksort($grouped);
        foreach ($grouped as $reason => $lines) {
            $this->line(sprintf('  %s (%d)', $reason, count($lines)));
            foreach (array_slice($lines, 0, 6) as $line) {
                $this->line('      ' . $line);
            }
            if (count($lines) > 6) {
                $this->line(sprintf('      … %d weitere', count($lines) - 6));
            }
        }

        if ($this->option('baseline')) {
            $keys = $graph->violationKeys();
            $content = "<?php\n\n// Baseline der Modulgrenzen (MVP-863) — nur schrumpfen: `php artisan modules:deps --baseline` nach Abbau.\nreturn [\n" . implode('', array_map(static fn (string $k): string => "    '" . addslashes($k) . "',\n", $keys)) . "];\n";
            File::write(base_path(self::BASELINE), $content);
            $this->info(sprintf('Baseline geschrieben: %d Einträge → %s', count($keys), self::BASELINE));
        }

        return self::SUCCESS;
    }
}
