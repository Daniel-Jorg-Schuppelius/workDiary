<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchRebuildCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Search;

use App\Console\Commands\Search\Concerns\SelectsSearchScope;
use App\Models\SearchDocument;
use App\Services\Search\Indexing\{SearchContext, SearchIndexer, SearchSourceRegistry};
use App\Services\Search\SearchVocabulary;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Baut den Tätigkeitsindex vollständig neu auf (Feature 153, MVP-770): jede
 * indizierbare Zeile wird geschrieben, verwaiste Dokumente entfernt, danach
 * das Wortverzeichnis je Organisation neu erzeugt.
 */
final class SearchRebuildCommand extends Command {
    use SelectsSearchScope;

    protected $signature = 'search:rebuild
        {--organization= : Nur diese Organisation (ID)}
        {--type=* : Nur diese Quellen (z. B. time_entry, diary_entry)}';

    protected $description = 'Baut den Tätigkeits-Suchindex und das Wortverzeichnis neu auf.';

    public function handle(SearchSourceRegistry $registry, SearchIndexer $indexer, SearchVocabulary $vocabulary, SearchContext $context): int {
        $organizationId = $this->organizationOption();
        $sources = $this->selectedSources($registry);
        if ($organizationId === false || $sources === null) {
            return self::FAILURE;
        }

        foreach ($sources as $source) {
            $written = 0;
            $source->query($organizationId)->chunkById(500, function (Collection $models) use ($indexer, $source, &$written): void {
                $written += $indexer->writeModels($source, $models);
            });
            $removed = $indexer->removeOrphans($source, $organizationId);
            $context->flush();

            $this->line(sprintf('  %-20s %7d geschrieben, %6d entfernt', $source->type()->value, $written, $removed));
        }

        $organizations = $organizationId !== null
            ? [$organizationId]
            : SearchDocument::query()->withoutGlobalScopes()->distinct()->pluck('organization_id')->map(static fn($id): int => (int) $id)->all();
        foreach ($organizations as $organization) {
            $this->line(sprintf('  Wortverzeichnis Organisation %d: %d Wörter', $organization, $vocabulary->rebuild($organization)));
        }

        $this->info('Suchindex neu aufgebaut.');

        return self::SUCCESS;
    }
}
