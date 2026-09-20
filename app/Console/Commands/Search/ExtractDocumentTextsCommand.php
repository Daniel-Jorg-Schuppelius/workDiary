<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExtractDocumentTextsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Search;

use App\Console\Commands\Search\Concerns\SelectsSearchScope;
use App\Enums\Document\DocumentTextFailure;
use App\Enums\Search\SearchSourceType;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use App\Services\Search\Indexing\SearchSourceRegistry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Dateitext für Bestandsdokumente nachziehen (MVP-819).
 *
 * Ausgelesen wird sonst nur beim Hochladen einer Version — alles, was vorher
 * im Archiv lag, stünde ohne Dateitext im Tätigkeitsindex. Der Lauf ist
 * wiederholbar: er greift nur Versionen ohne Eintrag. Ein Fehlschlag **ist**
 * ein Eintrag; er wird erst mit `--retry-failed` erneut versucht und auch dann
 * nur, wo sich etwas geändert haben kann.
 *
 * Die Auswahl kommt aus der Indexquelle selbst, damit Mandantengrenze,
 * Personalakten-Ausschluss und Papierkorb nicht ein zweites Mal dastehen und
 * beim nächsten Zuschnitt auseinanderlaufen.
 *
 * `--limit` ist der Häppchen-Schnitt: bei `QUEUE_CONNECTION=sync` läuft jede
 * Extraktion sofort im Vordergrund, und OCR eines Scans dauert Minuten.
 */
final class ExtractDocumentTextsCommand extends Command {
    use SelectsSearchScope;

    protected $signature = 'search:extract-texts
        {--organization= : Nur diese Organisation (ID)}
        {--limit=200 : Höchstens N Dokumente je Lauf (0 = alle)}
        {--retry-failed : Behebbare Fehlschläge erneut versuchen (fehlende Datei, gescheitertes Auslesen)}';

    protected $description = 'Zieht den Dateitext für Bestandsdokumente nach (Tätigkeitsindex).';

    public function handle(SearchSourceRegistry $registry): int {
        if (! config('search.indexing', true)) {
            $this->error('Die Indizierung ist abgeschaltet (search.indexing) — der Dateitext entsteht ausschließlich für den Tätigkeitsindex.');

            return self::FAILURE;
        }

        $organizationId = $this->organizationOption();
        if ($organizationId === false) {
            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $retryFailed = (bool) $this->option('retry-failed');

        // Die Eager Loads der Indexquelle braucht der Nachlauf nicht: er reicht
        // nur die Versions-ID an den Job weiter.
        $query = $registry->get(SearchSourceType::Document)
            ->query($organizationId)
            ->setEagerLoads([])
            ->whereNotNull('current_version_id')
            ->whereHas('currentVersion', fn (Builder $version): Builder => $this->needsText($version, $retryFailed));

        $queued = 0;
        foreach ($query->lazyById(500) as $document) {
            /** @var Document $document */
            ExtractDocumentTextJob::dispatch((int) $document->current_version_id);
            if (++$queued === $limit) {
                break;
            }
        }

        $this->info($queued === 0
            ? 'Kein Bestandsdokument ohne Dateitext.'
            : sprintf('%d Dokument(e) zur Textextraktion eingereiht (Warteschlange „media").', $queued));

        return self::SUCCESS;
    }

    /**
     * Versionen ohne Texteintrag — mit `--retry-failed` zusätzlich die, deren
     * vermerkter Grund einen zweiten Versuch überhaupt lohnt.
     *
     * Die Query kommt aus `whereHas` über die Indexquelle und ist dort nur als
     * {@see Model} typisiert; gemeint ist die Version des Dokuments.
     *
     * @param  Builder<Model>  $version
     * @return Builder<Model>
     */
    private function needsText(Builder $version, bool $retryFailed): Builder {
        return $version->where(function (Builder $scope) use ($retryFailed): void {
            $scope->whereDoesntHave('extractedText');
            if ($retryFailed) {
                $scope->orWhereHas(
                    'extractedText',
                    static fn (Builder $text): Builder => $text->whereIn('failure_reason', DocumentTextFailure::retryableValues()),
                );
            }
        });
    }
}
