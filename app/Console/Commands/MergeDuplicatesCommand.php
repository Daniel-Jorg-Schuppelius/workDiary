<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MergeDuplicatesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Gemeinsamer Kern der Merge-Duplicates-Commands (Konsolidierung B11):
 * Vorschau je Organisation, Merge erst mit --apply (Semantik bleibt).
 * Ableitungen liefern Kandidaten-Finder, Merge und Abschluss-Label.
 *
 * @template TModel of Model
 */
abstract class MergeDuplicatesCommand extends Command {
    use IteratesOrganizations;

    /**
     * Dubletten-Kandidaten einer Organisation für die gewählte Stufe.
     *
     * @return Collection<int, array{source: TModel, target: TModel, confidence: string, reasons: list<string>}>
     */
    abstract protected function candidates(Organization $organization, string $confidence): Collection;

    /**
     * Führt ein als Dublette bestätigtes Paar zusammen.
     *
     * @param TModel $source
     * @param TModel $target
     */
    abstract protected function mergePair(Model $source, Model $target): void;

    /** Abschlusszeile nach --apply (z. B. „3 Kunde(n) zusammengeführt."). */
    abstract protected function appliedSummary(int $merged): string;

    public function handle(): int {
        $confidence = (string) $this->option('confidence');
        $apply = (bool) $this->option('apply');

        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        $merged = 0;
        foreach ($organizations as $org) {
            $candidates = $this->candidates($org, $confidence);
            if ($candidates->isEmpty()) {
                continue;
            }

            $this->line("Organisation #{$org->id} ({$org->name}): {$candidates->count()} Kandidat(en) [{$confidence}]");
            foreach ($candidates as $pair) {
                $source = $pair['source'];
                $target = $pair['target'];
                $reasons = implode(', ', $pair['reasons']);

                $arrow = $apply ? '→ zusammengeführt' : '→ würde zusammenführen';
                $this->line(sprintf(
                    '  #%d %s  %s  #%d %s  (%s)',
                    $source->getKey(),
                    $source->getAttribute('name'),
                    $arrow,
                    $target->getKey(),
                    $target->getAttribute('name'),
                    $reasons,
                ));

                if ($apply) {
                    $this->mergePair($source, $target);
                    $merged++;
                }
            }
        }

        if (! $apply) {
            $this->info('Dry-Run — nichts geändert. Mit --apply ausführen.');
        } else {
            $this->info($this->appliedSummary($merged));
        }

        return self::SUCCESS;
    }
}
