<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchIndexObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Services\Search\Indexing\SearchIndexer;
use Illuminate\Database\Eloquent\Model;

/**
 * Hält den Tätigkeitsindex aktuell (Feature 153): Quellen und ihre
 * Kind-Modelle (Kommentar, Ticket-Nachricht) melden jede Änderung — der
 * Indexer schreibt nach dem Commit und entfernt Nicht-mehr-Indizierbares.
 */
final class SearchIndexObserver {
    public function __construct(private readonly SearchIndexer $indexer) {}

    public function saved(Model $model): void {
        $this->indexer->schedule($model);
    }

    public function deleted(Model $model): void {
        $this->indexer->schedule($model);
    }

    public function restored(Model $model): void {
        $this->indexer->schedule($model);
    }

    public function forceDeleted(Model $model): void {
        $this->indexer->schedule($model);
    }
}
