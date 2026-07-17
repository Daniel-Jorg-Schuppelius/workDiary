<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectDuplicateFinder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\{Organization, Project, ProjectMergeDismissal};
use App\Services\Integration\Match\{EntityMatcher, MatchProfile};
use App\Services\Integration\Profiles\ProjectMatchProfile;
use Illuminate\Database\Eloquent\{Collection as EloquentCollection, Model};

/**
 * Findet Dubletten-Kandidaten unter den Projekten einer Organisation (z. B.
 * mehrfach angelegte „Wartung"-Projekte nach dem Toggl-Import). Vergleiche
 * laufen ausschließlich innerhalb desselben Kunden (inkl. „ohne Kunde"), damit
 * gleichnamige Projekte verschiedener Kunden nicht fälschlich als Dublette
 * erscheinen — und das Zusammenführen kundenkonsistent bleibt.
 * Paar-Schleife/Dismissal-Filter siehe {@see AbstractDuplicateFinder}.
 *
 * @extends AbstractDuplicateFinder<Project>
 */
class ProjectDuplicateFinder extends AbstractDuplicateFinder {
    public function __construct(
        EntityMatcher $matcher,
        private readonly ProjectMatchProfile $profile,
    ) {
        parent::__construct($matcher);
    }

    protected function profile(): MatchProfile {
        return $this->profile;
    }

    protected function fetchCandidates(Organization $organization): EloquentCollection {
        return $this->profile->candidates($organization)
            ->withCount(['diaryEntries', 'timeEntries'])
            ->get();
    }

    /** Nur innerhalb desselben Kunden vergleichen (null => Gruppe "0"). */
    protected function groupKey(Model $model): int|string {
        return (int) ($model->customer_id ?? 0);
    }

    /**
     * Direkt verwandte Projekte (Eltern/Kind) sind nicht zusammenführbar —
     * gar nicht erst vorschlagen.
     */
    protected function skipPair(Model $a, Model $b): bool {
        return (int) $a->parent_id === (int) $b->id || (int) $b->parent_id === (int) $a->id;
    }

    /**
     * Ziel-Heuristik: Standardprojekt > mehr verknüpfte Einträge
     * (Zeiten + Aufträge) > kleinere (ältere) ID.
     */
    protected function score(Model $model): array {
        $entries = (int) ($model->diary_entries_count ?? 0) + (int) ($model->time_entries_count ?? 0);

        return [$model->is_default ? 1 : 0, $entries, -((int) $model->id)];
    }

    protected function dismissalModel(): string {
        return ProjectMergeDismissal::class;
    }

    protected function dismissalKeyColumns(): array {
        return ['project_low_id', 'project_high_id'];
    }
}
