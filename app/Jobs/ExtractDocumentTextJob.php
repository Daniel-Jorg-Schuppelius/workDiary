<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExtractDocumentTextJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Search\SearchSourceType;
use App\Exceptions\DocumentTextUnavailableException;
use App\Models\{DocumentVersion, DocumentVersionText};
use App\Services\Document\DocumentTextExtractor;
use App\Services\Search\Indexing\SearchIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Text einer Dokumentversion auslesen und für den Tätigkeitsindex ablegen
 * (MVP-819).
 *
 * In der Medien-Warteschlange und mit **einem** Versuch, wie Transcoding und
 * Untertitel: OCR eines mehrseitigen Scans läuft minutenlang und würde die
 * Standard-Warteschlange blockieren; was an der Datei scheitert, scheitert
 * beim zweiten Mal genauso. Der Fehlschlag wird festgehalten, damit kein
 * späterer Lauf erneut dieselbe OCR startet.
 */
class ExtractDocumentTextJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly int $documentVersionId) {
        $this->onQueue('media');

        // Siehe TranscodeVideoJob: retry_after gilt pro Verbindung.
        if (config('queue.default') !== 'sync') {
            $this->onConnection('media');
        }
    }

    public function handle(DocumentTextExtractor $extractor, SearchIndexer $indexer): void {
        $version = DocumentVersion::query()->with('document')->find($this->documentVersionId);
        if ($version === null || $version->document === null) {
            return;
        }

        try {
            $text = $extractor->extract($version);
            $failure = null;
        } catch (DocumentTextUnavailableException $e) {
            $text = null;
            $failure = $e->reason;
        }

        DocumentVersionText::query()->updateOrCreate(
            ['document_version_id' => (int) $version->id],
            ['text' => $text, 'extracted_at' => now(), 'failure_reason' => $failure?->value],
        );

        // Erst jetzt hat das Dokument seinen Volltext — neu indizieren.
        $indexer->index(SearchSourceType::Document, (int) $version->document_id);
    }
}
