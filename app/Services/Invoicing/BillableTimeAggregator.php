<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillableTimeAggregator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Invoicing;

use App\Models\{Project, TimeEntry};
use Illuminate\Support\Collection;

/**
 * Wendet die projektspezifische Abrechnungs-Taktung (Aufrundung) und die
 * Zusammenfassung dicht beieinander liegender Zeiteinträge an.
 *
 * Pro (Projekt, kind):
 *  - Taktung & max. Lücke werden über das Projekt aufgelöst
 *    ({@see Project::effectiveBillingIncrement()},
 *     {@see Project::effectiveBillingGroupingGap()}).
 *  - Einträge mit started_at UND ended_at werden chronologisch zu Blöcken
 *    verkettet, solange die Lücke zum Vorgänger ≤ Schwelle ist. Die Lücke wird
 *    mitberechnet; der Block wird EINMAL aufgerundet.
 *  - Einträge ohne Zeitstempel werden einzeln aufgerundet.
 *
 * Default (Taktung 1, Lücke 0) ⇒ keine Verkettung, Aufrundung auf 1 Min ist
 * ein No-op ⇒ Verhalten identisch zur minutengenauen Abrechnung.
 */
class BillableTimeAggregator {
    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @return Collection<int, BillingBlock>
     */
    public function aggregate(Collection $entries): Collection {
        /** @var Collection<int, BillingBlock> $blocks */
        $blocks = new Collection;

        $grouped = $entries->groupBy(fn(TimeEntry $e): string => $e->project_id . '|' . $e->kind->value);

        foreach ($grouped as $group) {
            /** @var TimeEntry $first */
            $first = $group->first();
            $project = $first->project;
            $increment = $project?->effectiveBillingIncrement() ?? 1;
            $gap = $project?->effectiveBillingGroupingGap() ?? 0;

            $partition = $group->partition(
                fn(TimeEntry $e): bool => $e->started_at !== null && $e->ended_at !== null
            );
            /** @var Collection<int, TimeEntry> $timed */
            $timed = $partition->get(0, new Collection);
            /** @var Collection<int, TimeEntry> $untimed */
            $untimed = $partition->get(1, new Collection);

            foreach ($this->buildTimedBlocks($timed->values(), $gap) as $chunk) {
                $blocks->push($this->makeBlock($chunk, $project, $increment, bridged: true, gap: $gap));
            }

            foreach ($untimed as $entry) {
                $blocks->push($this->makeBlock(new Collection([$entry]), $project, $increment, bridged: false, gap: $gap));
            }
        }

        return $blocks;
    }

    public function ceilToIncrement(int $minutes, int $increment): int {
        $increment = max(1, $increment);
        if ($minutes <= 0) {
            return 0;
        }

        return (int) (ceil($minutes / $increment) * $increment);
    }

    /**
     * Verkettet chronologisch sortierte timed-Einträge zu Blöcken, solange die
     * Lücke zum Vorgänger ≤ $gap ist. Bei $gap <= 0 bleibt jeder Eintrag allein.
     *
     * @param  Collection<int, TimeEntry>  $timed
     * @return list<Collection<int, TimeEntry>>
     */
    private function buildTimedBlocks(Collection $timed, int $gap): array {
        $sorted = $timed->sortBy(fn(TimeEntry $e): int => $e->started_at?->getTimestamp() ?? 0)->values();

        /** @var list<Collection<int, TimeEntry>> $blocks */
        $blocks = [];
        /** @var Collection<int, TimeEntry>|null $current */
        $current = null;
        /** @var TimeEntry|null $prev */
        $prev = null;

        foreach ($sorted as $entry) {
            if ($current === null) {
                $current = new Collection([$entry]);
            } else {
                $gapMinutes = $prev !== null && $prev->ended_at !== null && $entry->started_at !== null
                    ? (int) $prev->ended_at->diffInMinutes($entry->started_at, false)
                    : PHP_INT_MAX;
                if ($gap > 0 && $gapMinutes >= 0 && $gapMinutes <= $gap) {
                    $current->push($entry);
                } else {
                    $blocks[] = $current;
                    $current = new Collection([$entry]);
                }
            }
            $prev = $entry;
        }

        if ($current !== null) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    /**
     * @param  Collection<int, TimeEntry>  $chunk
     */
    private function makeBlock(Collection $chunk, ?Project $project, int $increment, bool $bridged, int $gap): BillingBlock {
        /** @var TimeEntry $firstEntry */
        $firstEntry = $chunk->first();

        $workedMinutes = (int) $chunk->sum(fn(TimeEntry $e): int => (int) $e->minutes);

        $firstStart = null;
        $lastEnd = null;
        $bridgedGaps = 0;
        if ($bridged) {
            $ordered = $chunk->sortBy(fn(TimeEntry $e): int => $e->started_at?->getTimestamp() ?? 0)->values();
            $firstStart = $ordered->first()?->started_at?->copy();
            $lastEnd = $ordered->last()?->ended_at?->copy();

            $prev = null;
            foreach ($ordered as $e) {
                if ($prev !== null && $prev->ended_at !== null && $e->started_at !== null) {
                    $g = (int) $prev->ended_at->diffInMinutes($e->started_at, false);
                    if ($g > 0 && $g <= $gap) {
                        $bridgedGaps += $g;
                    }
                }
                $prev = $e;
            }
        }

        $rawMinutes = $workedMinutes + $bridgedGaps;
        $billedMinutes = $this->ceilToIncrement($rawMinutes, $increment);
        $revenue = round((float) $chunk->sum(fn(TimeEntry $e): float => $e->rate?->toFloat() ?? 0.0), 2);

        $description = $chunk->count() === 1
            ? ($firstEntry->description !== null ? trim((string) $firstEntry->description) : null)
            : null;

        return new BillingBlock(
            project: $project,
            kind: $firstEntry->kind,
            entryIds: array_values($chunk->map(fn(TimeEntry $e): int => (int) $e->id)->all()),
            primaryEntryId: (int) $firstEntry->id,
            workedMinutes: $workedMinutes,
            rawMinutes: $rawMinutes,
            billedMinutes: $billedMinutes,
            revenue: $revenue,
            firstStart: $firstStart,
            lastEnd: $lastEnd,
            description: $description !== '' ? $description : null,
        );
    }
}
