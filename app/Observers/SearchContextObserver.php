<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchContextObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ReindexSearchContextJob;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Project\Project;
use App\Services\Search\Indexing\SearchContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Kontext der Suchdokumente nachziehen (Feature 153): Umbenennen oder
 * Umhängen eines Projekts, Endkunden oder Kunden ändert Text und Bezüge
 * aller Dokumente darunter — das erledigt ein Job nach dem Commit, damit das
 * Speichern der Stammdaten nicht auf tausende Zeiteinträge wartet.
 */
final class SearchContextObserver {
    /** @var array<class-string<Model>, array{column: string, attributes: list<string>}> */
    private const WATCHED = [
        Project::class => ['column' => 'project_id', 'attributes' => ['name', 'number', 'description', 'keywords', 'customer_id', 'foreign_customer_id']],
        ForeignCustomer::class => ['column' => 'foreign_customer_id', 'attributes' => ['name', 'number', 'company', 'matchcode', 'contact_name', 'customer_id']],
        Customer::class => ['column' => 'customer_id', 'attributes' => ['name', 'number', 'company']],
    ];

    public function updated(Model $model): void {
        $watch = self::WATCHED[$model::class] ?? null;
        if ($watch !== null && $model->wasChanged($watch['attributes'])) {
            $this->dispatch($watch['column'], $model);
        }
    }

    public function deleted(Model $model): void {
        $watch = self::WATCHED[$model::class] ?? null;
        if ($watch !== null) {
            $this->dispatch($watch['column'], $model);
        }
    }

    private function dispatch(string $column, Model $model): void {
        // Auch ohne Indizierung: Einträge, die im selben Request/Job noch
        // indiziert werden, dürfen den alten Kontext nicht aus dem Memo lesen.
        app(SearchContext::class)->flush();

        if (! config('search.indexing', true)) {
            return;
        }

        ReindexSearchContextJob::dispatch($column, (int) $model->getKey())->afterCommit();
    }
}
