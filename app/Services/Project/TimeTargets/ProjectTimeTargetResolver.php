<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeTargetResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\TimeTargets;

use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeTarget};
use App\Services\Flextime\FlexCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Soll je Person und Tag aus den Sollzeilen eines Projekts (Feature 194).
 * Vorrang: Datum vor Wochentag, Person vor Projektvorgabe; bei gleichem Rang
 * die jüngere Zeile. Urlaub (genehmigt), Krankheit und Feiertag setzen das
 * Soll auf 0 — dieselbe Regel wie das Tagessoll des Arbeitszeitmodells.
 */
final class ProjectTimeTargetResolver {
    public function __construct(private readonly FlexCalculator $flex) {}

    /**
     * Soll je Person und Tag im Zeitraum: [user_id][Y-m-d] => Minuten. Tage ohne
     * passende Zeile fehlen; Abwesenheit ergibt 0.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, int>>
     */
    public function targetsFor(Project $project, array $userIds, CarbonImmutable $from, CarbonImmutable $to): array {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return [];
        }
        $rows = $project->timeTargets()
            ->where(static fn ($q) => $q->whereNull('user_id')->orWhereIn('user_id', $userIds))
            ->orderBy('id')
            ->get();
        if ($rows->isEmpty()) {
            return [];
        }
        $absent = $this->flex->daysWithoutTarget($userIds, $from, $to);
        $targets = [];
        for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $date = $day->toDateString();
            $mutable = Carbon::parse($date);
            foreach ($userIds as $userId) {
                $minutes = $this->pick($rows, $userId, $mutable);
                if ($minutes === null) {
                    continue;
                }
                $targets[$userId][$date] = isset($absent[$userId][$date]) ? 0 : $minutes;
            }
        }

        return $targets;
    }

    public function targetFor(Project $project, User $user, CarbonImmutable $day): ?int {
        return $this->targetsFor($project, [(int) $user->id], $day, $day)[(int) $user->id][$day->toDateString()] ?? null;
    }

    /**
     * Personen, für die ein Soll gelten kann: alle zuweisbaren Personen bei einer
     * Projektvorgabe, sonst nur die mit eigener Zeile.
     *
     * @return list<int>
     */
    public function subjectUserIds(Project $project): array {
        $rows = $project->timeTargets()->get(['user_id']);
        if ($rows->isEmpty()) {
            return [];
        }
        $ids = $rows->pluck('user_id')->filter()->map(static fn ($id): int => (int) $id);
        if ($rows->contains(static fn (ProjectTimeTarget $row): bool => $row->user_id === null)) {
            $ids = $ids->merge($project->assignableUsers()->pluck('id')->map(static fn ($id): int => (int) $id));
        }

        return array_values($ids->unique()->values()->all());
    }

    /** @param  \Illuminate\Database\Eloquent\Collection<int, ProjectTimeTarget>  $rows */
    private function pick(\Illuminate\Database\Eloquent\Collection $rows, int $userId, Carbon $day): ?int {
        $best = null;
        $bestRank = -1;
        foreach ($rows as $row) {
            if ($row->user_id !== null && (int) $row->user_id !== $userId) {
                continue;
            }
            if (! $row->appliesOn($day)) {
                continue;
            }
            $rank = ($row->specific_date !== null ? 2 : 0) + ($row->user_id !== null ? 1 : 0);
            if ($rank > $bestRank || ($rank === $bestRank && $best !== null && $row->id > $best->id)) {
                $best = $row;
                $bestRank = $rank;
            }
        }

        return $best?->target_minutes;
    }
}
