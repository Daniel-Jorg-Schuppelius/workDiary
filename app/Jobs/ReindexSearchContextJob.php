<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReindexSearchContextJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Search\Indexing\SearchIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Schreibt alle Suchdokumente mit einem Projekt-, Endkunden- oder Kundenbezug
 * neu (Feature 153). Idempotent — ein doppelter Lauf schreibt dasselbe.
 */
final class ReindexSearchContextJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $column,
        public readonly int $id,
    ) {}

    public function handle(SearchIndexer $indexer): void {
        $indexer->reindexContext($this->column, $this->id);
    }
}
