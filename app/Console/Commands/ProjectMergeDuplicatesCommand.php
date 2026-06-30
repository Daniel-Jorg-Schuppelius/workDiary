<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectMergeDuplicatesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\{ProjectDuplicateFinder, ProjectMergeService};
use Illuminate\Console\Command;

/**
 * Listet Projekt-Dubletten und führt Treffer auf Wunsch automatisch zusammen
 * (z. B. mehrfach angelegte „Wartung"-Projekte nach dem Toggl-Import). Standard
 * ist ein Dry-Run; erst `--apply` schreibt. Default-Stufe ist „likely" (gleicher
 * Kunde + identischer Name); unscharfe Treffer (fuzzy) bleiben dem manuellen
 * Abgleich vorbehalten, sofern nicht ausdrücklich gewählt.
 */
class ProjectMergeDuplicatesCommand extends Command {
    protected $signature = 'project:merge-duplicates
        {--organization= : ID einer einzelnen Organisation, sonst alle}
        {--confidence=likely : Welche Stufe automatisch gemergt wird (exact|likely|fuzzy)}
        {--apply : Tatsächlich zusammenführen (sonst nur Vorschau)}';

    protected $description = 'Findet doppelte Projekte (gleicher Kunde + Name) und führt sie zusammen. Ohne --apply nur Vorschau.';

    public function handle(ProjectDuplicateFinder $finder, ProjectMergeService $merger): int {
        $orgId = $this->option('organization');
        $confidence = (string) $this->option('confidence');
        $apply = (bool) $this->option('apply');

        $query = Organization::query();
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        $organizations = $query->get();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        $merged = 0;
        foreach ($organizations as $org) {
            $candidates = $finder->candidates($org, $confidence);
            if ($candidates->isEmpty()) {
                continue;
            }

            $this->line("Organisation #{$org->id} ({$org->name}): {$candidates->count()} Kandidat(en) [{$confidence}]");
            foreach ($candidates as $pair) {
                /** @var \App\Models\Project $source */
                $source = $pair['source'];
                /** @var \App\Models\Project $target */
                $target = $pair['target'];
                $reasons = implode(', ', $pair['reasons']);

                $arrow = $apply ? '→ zusammengeführt' : '→ würde zusammenführen';
                $this->line(sprintf(
                    '  #%d %s  %s  #%d %s  (%s)',
                    $source->id,
                    $source->name,
                    $arrow,
                    $target->id,
                    $target->name,
                    $reasons,
                ));

                if ($apply) {
                    $merger->merge($source, $target);
                    $merged++;
                }
            }
        }

        if (! $apply) {
            $this->info('Dry-Run — nichts geändert. Mit --apply ausführen.');
        } else {
            $this->info("{$merged} Projekt(e) zusammengeführt.");
        }

        return self::SUCCESS;
    }
}
