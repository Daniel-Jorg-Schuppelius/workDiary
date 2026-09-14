<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchReconcileCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Search;

use App\Console\Commands\Search\Concerns\SelectsSearchScope;
use App\Services\Search\Indexing\{SearchContext, SearchIndexer, SearchSourceRegistry};
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/**
 * Nächtlicher Abgleich des Tätigkeitsindex (Feature 153, MVP-770): schreibt
 * Quellen ohne Dokument und solche, die sich nach dem letzten Indizieren
 * geändert haben (`updated_at`), und entfernt verwaiste Dokumente. Fängt, was
 * Observer nicht sehen — Query-Builder-Massenänderungen, Fremdschlüssel-
 * Kaskaden, abgebrochene Jobs. Auch der erste Lauf nach dem Deploy.
 */
final class SearchReconcileCommand extends Command {
    use SelectsSearchScope;

    protected $signature = 'search:reconcile
        {--organization= : Nur diese Organisation (ID)}
        {--type=* : Nur diese Quellen (z. B. time_entry, diary_entry)}';

    protected $description = 'Gleicht den Tätigkeits-Suchindex ab: fehlende und veraltete Dokumente schreiben, verwaiste entfernen.';

    public function handle(SearchSourceRegistry $registry, SearchIndexer $indexer, SearchContext $context): int {
        $organizationId = $this->organizationOption();
        $sources = $this->selectedSources($registry);
        if ($organizationId === false || $sources === null) {
            return self::FAILURE;
        }

        foreach ($sources as $source) {
            $table = $source->query(null)->getModel()->getTable();
            $type = $source->type()->value;
            $written = 0;

            $source->query($organizationId)
                ->leftJoin('search_documents as sd', static function (JoinClause $join) use ($table, $type): void {
                    $join->on('sd.source_id', '=', $table . '.id')->where('sd.source_type', '=', $type);
                })
                ->where(static fn(Builder $q) => $q
                    ->whereNull('sd.id')
                    ->orWhereColumn($table . '.updated_at', '>', 'sd.source_updated_at'))
                ->select($table . '.*')
                ->chunkById(500, function (Collection $models) use ($indexer, $source, &$written): void {
                    $written += $indexer->writeModels($source, $models);
                }, $table . '.id', 'id');

            $removed = $indexer->removeOrphans($source, $organizationId);
            $context->flush();

            $this->line(sprintf('  %-20s %7d geschrieben, %6d entfernt', $type, $written, $removed));
        }

        return self::SUCCESS;
    }
}
