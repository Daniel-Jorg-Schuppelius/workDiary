<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileMetricsService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Agile;

use App\Enums\Agile\AgileColumnCategory;
use App\Models\Agile\{AgileBoard, AgileBoardColumn, AgileEvent, AgileSprint};
use App\Services\Agile\Metrics\MetricResult;
use Illuminate\Support\{Carbon, Collection};

/**
 * Agile Kennzahlen (Feature 064, MVP-143) — Kern für die Berichte P8–P10.
 * Alle Werte werden AUSSCHLIESSLICH aus agile_events + Sprint-Snapshots
 * gerechnet (DoD „aus Ereignissen reproduzierbar"); Spalten-IDs werden über
 * die aktuelle Spaltenliste auf Kategorien abgebildet. Definitionen sind
 * versioniert (METRIC_VERSION) — Formeländerungen erhöhen die Version,
 * Ergebnisse tragen sie im DTO.
 */
class AgileMetricsService {
    public const METRIC_VERSION = 1;

    /**
     * Velocity: done_points je abgeschlossenem Sprint desselben Boards
     * (nur completion_snapshots — nachträgliche Item-Änderungen sind egal),
     * dazu Median und Spannweite.
     */
    public function velocity(AgileBoard $board): MetricResult {
        $sprints = AgileSprint::query()
            ->where('board_id', $board->id)
            ->where('status', AgileSprint::STATUS_COMPLETED)
            ->orderBy('completed_at')
            ->get();

        $series = $sprints->map(fn(AgileSprint $sprint): array => [
            'sprint' => $sprint->name,
            'done_points' => (int) (($sprint->completion_snapshot ?? [])['done_points'] ?? 0),
            'committed_points' => (int) (($sprint->completion_snapshot ?? [])['committed_points'] ?? 0),
            'scope_added' => (int) (($sprint->completion_snapshot ?? [])['scope_added'] ?? 0),
        ])->values()->all();
        $values = array_map(fn(array $row): int => $row['done_points'], $series);

        return $this->result('velocity', 'story_points', ['board_id' => $board->id], [
            'sprints' => $series,
            'median' => $this->median($values),
            'min' => $values === [] ? 0 : min($values),
            'max' => $values === [] ? 0 : max($values),
        ]);
    }

    /**
     * Burndown eines gestarteten Sprints: Tagesreihe verbleibender Punkte;
     * Scope-Zugänge (sprint.item_added nach Start) und -Abgänge getrennt.
     * Punktquelle: commitment_snapshot bzw. story_points im Zugangs-Event.
     */
    public function burndown(AgileSprint $sprint): MetricResult {
        if ($sprint->started_at === null) {
            throw new \InvalidArgumentException('Burndown braucht einen gestarteten Sprint.');
        }

        $commitment = collect((array) ($sprint->commitment_snapshot ?? []));
        $pointsByItem = $commitment
            ->mapWithKeys(fn(array $row): array => [(int) $row['work_item_id'] => (int) ($row['story_points'] ?? 0)]);
        $committedPoints = (int) $pointsByItem->sum();

        $events = AgileEvent::query()
            ->where('board_id', $sprint->board_id)
            ->where('created_at', '>=', $sprint->started_at)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Scope-Zugänge/-Abgänge dieses Sprints (nach Start).
        $scope = [];
        foreach ($events as $event) {
            if ((int) $event->sprint_id !== (int) $sprint->id || $event->work_item_id === null) {
                continue;
            }
            if ($event->event === 'sprint.item_added' && (bool) ($event->payload['added_after_start'] ?? false)) {
                $points = (int) ($event->payload['story_points'] ?? 0);
                $pointsByItem->put((int) $event->work_item_id, $points);
                $scope[] = ['at' => $event->created_at, 'delta' => $points];
            }
            if ($event->event === 'sprint.item_removed') {
                $scope[] = ['at' => $event->created_at, 'delta' => -(int) $pointsByItem->get((int) $event->work_item_id, 0)];
            }
        }

        // Erster done-Eintritt je Sprint-Item (column.moved auf done-Spalte).
        $doneAt = $this->firstCategoryEntries($this->board($sprint), AgileColumnCategory::Done, $events)
            ->only($pointsByItem->keys()->all());

        $endOfRange = $sprint->completed_at ?? $sprint->ends_on ?? now();
        $series = [];
        $day = $sprint->started_at->copy()->startOfDay();
        while ($day->lte($endOfRange)) {
            $endOfDay = $day->copy()->endOfDay();
            $donePoints = (int) $doneAt
                ->filter(fn(\Carbon\Carbon $at): bool => $at->lte($endOfDay))
                ->keys()
                ->sum(fn(int $itemId): int => (int) $pointsByItem->get($itemId, 0));
            $scopeDelta = (int) collect($scope)
                ->filter(fn(array $change): bool => $change['at']->lte($endOfDay))
                ->sum('delta');
            $series[] = [
                'date' => $day->toDateString(),
                'remaining' => $committedPoints + $scopeDelta - $donePoints,
                'scope_delta' => $scopeDelta,
            ];
            $day->addDay();
        }

        return $this->result('burndown', 'story_points', ['sprint_id' => $sprint->id], [
            'committed' => $committedPoints,
            'series' => $series,
        ]);
    }

    /**
     * Lead-/Cycle-Time in Stunden (P50/P85/P95). Lead: backlog.added →
     * erster done-Eintritt; Cycle: erster in_progress- → erster done-Eintritt.
     */
    public function leadCycleTime(AgileBoard $board): MetricResult {
        $events = $this->boardEvents($board);
        $added = $this->firstEventAt($events, 'backlog.added');
        $inProgress = $this->firstCategoryEntries($board, AgileColumnCategory::InProgress, $events);
        $done = $this->firstCategoryEntries($board, AgileColumnCategory::Done, $events);

        $lead = [];
        $cycle = [];
        foreach ($done as $itemId => $doneEntry) {
            $addedAt = $added->get($itemId);
            if ($addedAt !== null) {
                $lead[] = round($addedAt->diffInMinutes($doneEntry) / 60, 2);
            }
            $startedAt = $inProgress->get($itemId);
            if ($startedAt !== null && $startedAt->lte($doneEntry)) {
                $cycle[] = round($startedAt->diffInMinutes($doneEntry) / 60, 2);
            }
        }

        return $this->result('lead_cycle_time', 'hours', ['board_id' => $board->id], [
            'lead' => $this->percentiles($lead),
            'cycle' => $this->percentiles($cycle),
            'sample_size' => count($done),
        ]);
    }

    /** Durchsatz: erledigte Elemente je ISO-Woche (erster done-Eintritt). */
    public function throughput(AgileBoard $board): MetricResult {
        $done = $this->firstCategoryEntries($board, AgileColumnCategory::Done, $this->boardEvents($board));

        $weeks = $done->groupBy(fn(\Carbon\Carbon $at): string => $at->format('o-\WW'))
            ->map(fn(Collection $group): int => $group->count())
            ->sortKeys();

        return $this->result('throughput', 'items_per_week', ['board_id' => $board->id], [
            'weeks' => $weeks->all(),
            'total' => (int) $weeks->sum(),
        ]);
    }

    /**
     * CFD-Reihen: je Tag die Anzahl Elemente je Spaltenkategorie
     * (Rekonstruktion der Spaltenwechsel; Elemente ohne Spalte zählen nicht).
     */
    public function cfd(AgileBoard $board, Carbon $from, Carbon $to): MetricResult {
        $categories = $this->columnCategories($board);
        $events = $this->boardEvents($board)->where('event', 'column.moved');

        // Je Item die zeitlich sortierten Kategorie-Übergänge.
        $transitions = [];
        foreach ($events as $event) {
            if ($event->work_item_id === null) {
                continue;
            }
            $transitions[(int) $event->work_item_id][] = [
                'at' => $event->created_at,
                'category' => $categories[(int) ($event->payload['to'] ?? 0)] ?? null,
            ];
        }

        $series = [];
        $day = $from->copy()->startOfDay();
        while ($day->lte($to)) {
            $endOfDay = $day->copy()->endOfDay();
            $counts = array_fill_keys(array_map(fn(AgileColumnCategory $c): string => $c->value, AgileColumnCategory::cases()), 0);
            foreach ($transitions as $itemTransitions) {
                $current = null;
                foreach ($itemTransitions as $transition) {
                    if ($transition['at']->gt($endOfDay)) {
                        break;
                    }
                    $current = $transition['category'];
                }
                if ($current !== null) {
                    $counts[$current]++;
                }
            }
            $series[] = ['date' => $day->toDateString(), ...$counts];
            $day->addDay();
        }

        return $this->result('cfd', 'items', ['board_id' => $board->id, 'from' => $from->toDateString(), 'to' => $to->toDateString()], [
            'series' => $series,
        ]);
    }

    /** WIP-Zeitreihe: in_progress-Bestand je Tag (CFD-Projektion). */
    public function wipSeries(AgileBoard $board, Carbon $from, Carbon $to): MetricResult {
        $cfd = $this->cfd($board, $from, $to);

        $series = array_map(fn(array $row): array => [
            'date' => $row['date'],
            'wip' => (int) ($row[AgileColumnCategory::InProgress->value] ?? 0),
        ], (array) $cfd->data['series']);

        return $this->result('wip', 'items', ['board_id' => $board->id, 'from' => $from->toDateString(), 'to' => $to->toDateString()], [
            'series' => $series,
        ]);
    }

    /**
     * Blockierdauer je Grund in Stunden — nur abgeschlossene Paare
     * item.blocked → item.unblocked (offene Blockierungen wären nicht
     * reproduzierbar, sie hingen vom Abfragezeitpunkt ab).
     */
    public function blockedDurations(AgileBoard $board): MetricResult {
        $open = [];
        $byReason = [];
        foreach ($this->boardEvents($board) as $event) {
            if ($event->work_item_id === null) {
                continue;
            }
            $itemId = (int) $event->work_item_id;
            if ($event->event === 'item.blocked') {
                $open[$itemId] = ['at' => $event->created_at, 'reason' => (string) ($event->payload['reason'] ?? '')];
            }
            if ($event->event === 'item.unblocked' && isset($open[$itemId])) {
                $reason = $open[$itemId]['reason'];
                $byReason[$reason] ??= ['hours' => 0.0, 'count' => 0];
                $byReason[$reason]['hours'] += round($open[$itemId]['at']->diffInMinutes($event->created_at) / 60, 2);
                $byReason[$reason]['count']++;
                unset($open[$itemId]);
            }
        }

        return $this->result('blocked_durations', 'hours', ['board_id' => $board->id], [
            'reasons' => $byReason,
        ]);
    }

    /**
     * Qualitätsreihe je ISO-Woche (P8): Wiederöffnungen (column.moved von
     * einer done- in eine nicht-done-Spalte) und Übersteuerungen
     * (override.*-Events) — Frühindikator statt Personen-Scoring.
     */
    public function qualitySeries(AgileBoard $board): MetricResult {
        $categories = $this->columnCategories($board);

        $weeks = [];
        foreach ($this->boardEvents($board) as $event) {
            $week = $event->created_at->format('o-\WW');
            $isReopen = $event->event === 'column.moved'
                && ($categories[(int) ($event->payload['from'] ?? 0)] ?? null) === AgileColumnCategory::Done->value
                && ($categories[(int) ($event->payload['to'] ?? 0)] ?? null) !== AgileColumnCategory::Done->value;
            $isOverride = str_starts_with($event->event, 'override.');
            if (! $isReopen && ! $isOverride) {
                continue;
            }
            $weeks[$week] ??= ['reopened' => 0, 'overrides' => 0];
            $weeks[$week][$isReopen ? 'reopened' : 'overrides']++;
        }
        ksort($weeks);

        return $this->result('quality', 'events_per_week', ['board_id' => $board->id], [
            'weeks' => $weeks,
        ]);
    }

    /**
     * Flow-Effizienz (P9): Arbeitszeit / (Arbeits- + Wartezeit) je erledigtem
     * Element — NUR wenn alle nicht-done-Spalten eine Berichtsrolle tragen;
     * sonst kommt ein Datenqualitäts-Hinweis statt eines Schätzwerts.
     */
    public function flowEfficiency(AgileBoard $board): MetricResult {
        $columns = AgileBoardColumn::query()->where('board_id', $board->id)->get();
        $unclassified = $columns
            ->filter(fn(AgileBoardColumn $c): bool => $c->category !== AgileColumnCategory::Done && $c->report_role === null);
        if ($unclassified->isNotEmpty()) {
            return $this->result('flow_efficiency', 'percent', ['board_id' => $board->id], [
                'available' => false,
                'unclassified_columns' => $unclassified->pluck('name')->values()->all(),
            ]);
        }

        $roles = $columns->mapWithKeys(fn(AgileBoardColumn $c): array => [(int) $c->id => $c->report_role])->all();
        $categories = $this->columnCategories($board);

        // Je Item: Spaltenaufenthalte bis zum ersten done-Eintritt.
        $timeline = [];
        foreach ($this->boardEvents($board) as $event) {
            if ($event->event !== 'column.moved' || $event->work_item_id === null) {
                continue;
            }
            $timeline[(int) $event->work_item_id][] = [
                'at' => $event->created_at,
                'to' => (int) ($event->payload['to'] ?? 0),
            ];
        }

        $values = [];
        foreach ($timeline as $moves) {
            $working = 0.0;
            $waiting = 0.0;
            $reachedDone = false;
            foreach ($moves as $i => $move) {
                if (($categories[$move['to']] ?? null) === AgileColumnCategory::Done->value) {
                    $reachedDone = true;
                    break;
                }
                $next = $moves[$i + 1] ?? null;
                if ($next === null) {
                    break;
                }
                $minutes = $move['at']->diffInMinutes($next['at']);
                if (($roles[$move['to']] ?? null) === 'waiting') {
                    $waiting += $minutes;
                } else {
                    $working += $minutes;
                }
            }
            if ($reachedDone && ($working + $waiting) > 0) {
                $values[] = round($working / ($working + $waiting) * 100, 1);
            }
        }

        return $this->result('flow_efficiency', 'percent', ['board_id' => $board->id], [
            'available' => true,
            'median' => $this->median($values),
            'sample_size' => count($values),
        ]);
    }

    /**
     * Aging-WIP (P9): Elemente, die aktuell in einer in_progress-Spalte
     * stehen, mit Alter seit dem ersten in_progress-Eintritt (Tage).
     * Anzeigekennzahl — hängt bewusst vom Abfragezeitpunkt ab.
     */
    public function agingWip(AgileBoard $board): MetricResult {
        $inProgress = $this->firstCategoryEntries($board, AgileColumnCategory::InProgress, $this->boardEvents($board));

        $items = \App\Models\Agile\AgileWorkItem::query()
            ->where('board_id', $board->id)
            ->whereHas('column', fn($q) => $q->where('category', AgileColumnCategory::InProgress->value))
            ->with(['task', 'column'])
            ->get();

        $rows = $items->map(function (\App\Models\Agile\AgileWorkItem $item) use ($inProgress): array {
            $enteredAt = $inProgress->get($item->id);

            return [
                'work_item_id' => $item->id,
                'title' => $item->task?->title,
                'column' => $item->column?->name,
                'blocked' => $item->isBlocked(),
                'age_days' => $enteredAt !== null ? round($enteredAt->diffInHours(now()) / 24, 1) : null,
            ];
        })->sortByDesc('age_days')->values()->all();

        return $this->result('aging_wip', 'days', ['board_id' => $board->id], [
            'items' => $rows,
        ]);
    }

    /**
     * Backlog-Zu-/Abgang (P9) je ISO-Woche: neue Elemente (backlog.added),
     * entfernte (backlog.removed) und erledigte (erster done-Eintritt).
     */
    public function backlogFlow(AgileBoard $board): MetricResult {
        $events = $this->boardEvents($board);
        $done = $this->firstCategoryEntries($board, AgileColumnCategory::Done, $events);

        $weeks = [];
        foreach ($events as $event) {
            if (! in_array($event->event, ['backlog.added', 'backlog.removed'], true)) {
                continue;
            }
            $week = $event->created_at->format('o-\WW');
            $weeks[$week] ??= ['added' => 0, 'removed' => 0, 'done' => 0];
            $weeks[$week][$event->event === 'backlog.added' ? 'added' : 'removed']++;
        }
        foreach ($done as $at) {
            $week = $at->format('o-\WW');
            $weeks[$week] ??= ['added' => 0, 'removed' => 0, 'done' => 0];
            $weeks[$week]['done']++;
        }
        ksort($weeks);

        return $this->result('backlog_flow', 'items_per_week', ['board_id' => $board->id], [
            'weeks' => $weeks,
        ]);
    }

    /**
     * Empirische Prognose (P10): Monte-Carlo über historische Wochen-
     * durchsätze (Bootstrap-Ziehen mit Zurücklegen, Seed fixierbar) —
     * „Wie viele Wochen bis :remaining Elemente fertig sind?" mit
     * P50/P85/P95. Bei weniger als 4 vergleichbaren Wochen KEIN Ergebnis,
     * sondern ein Hinweis (available=false).
     */
    public function forecast(AgileBoard $board, ?int $remainingItems = null, int $seed = 20260708, int $runs = 1000): MetricResult {
        $weeklyThroughput = array_values((array) $this->throughput($board)->data['weeks']);

        $remainingItems ??= \App\Models\Agile\AgileWorkItem::query()
            ->where('board_id', $board->id)
            ->where(fn($q) => $q->whereNull('column_id')
                ->orWhereHas('column', fn($c) => $c->where('category', '!=', AgileColumnCategory::Done->value)))
            ->count();

        if (count($weeklyThroughput) < 4 || array_sum($weeklyThroughput) === 0) {
            return $this->result('forecast', 'weeks', ['board_id' => $board->id, 'remaining_items' => $remainingItems, 'seed' => $seed], [
                'available' => false,
                'reason' => (string) __('Weniger als 4 vergleichbare Wochen mit Durchsatz — keine belastbare Prognose.'),
                'observed_weeks' => count($weeklyThroughput),
            ]);
        }

        mt_srand($seed);
        $outcomes = [];
        $poolSize = count($weeklyThroughput);
        for ($run = 0; $run < $runs; $run++) {
            $left = $remainingItems;
            $weeks = 0;
            while ($left > 0 && $weeks < 520) {
                $left -= $weeklyThroughput[mt_rand(0, $poolSize - 1)];
                $weeks++;
            }
            $outcomes[] = (float) $weeks;
        }

        return $this->result('forecast', 'weeks', ['board_id' => $board->id, 'remaining_items' => $remainingItems, 'seed' => $seed], [
            'available' => true,
            'p50' => $this->percentile($outcomes, 50),
            'p85' => $this->percentile($outcomes, 85),
            'p95' => $this->percentile($outcomes, 95),
            'runs' => $runs,
            'observed_weeks' => $poolSize,
        ]);
    }

    // ── Bausteine ─────────────────────────────────────────────────────────

    /** @return Collection<int, AgileEvent> */
    private function boardEvents(AgileBoard $board): Collection {
        return AgileEvent::query()
            ->where('board_id', $board->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /** @return array<int, string> Spalten-ID → Kategorie-Wert */
    private function columnCategories(AgileBoard $board): array {
        return AgileBoardColumn::query()
            ->where('board_id', $board->id)
            ->get(['id', 'category'])
            ->mapWithKeys(fn(AgileBoardColumn $column): array => [(int) $column->id => $column->category->value])
            ->all();
    }

    /**
     * Erster Eintritt je Item in eine Kategorie (aus column.moved).
     *
     * @param Collection<int, AgileEvent> $events zeitlich sortiert
     * @return Collection<int, \Carbon\Carbon> work_item_id → Zeitpunkt
     */
    private function firstCategoryEntries(AgileBoard $board, AgileColumnCategory $category, Collection $events): Collection {
        $categories = $this->columnCategories($board);

        /** @var Collection<int, \Carbon\Carbon> $entries */
        $entries = collect();
        foreach ($events as $event) {
            if ($event->event !== 'column.moved' || $event->work_item_id === null) {
                continue;
            }
            $target = $categories[(int) ($event->payload['to'] ?? 0)] ?? null;
            if ($target === $category->value && ! $entries->has((int) $event->work_item_id)) {
                $entries->put((int) $event->work_item_id, $event->created_at);
            }
        }

        return $entries;
    }

    /**
     * @param Collection<int, AgileEvent> $events zeitlich sortiert
     * @return Collection<int, \Carbon\Carbon> work_item_id → erster Zeitpunkt des Events
     */
    private function firstEventAt(Collection $events, string $event): Collection {
        /** @var Collection<int, \Carbon\Carbon> $result */
        $result = collect();
        foreach ($events as $candidate) {
            if ($candidate->event === $event && $candidate->work_item_id !== null && ! $result->has((int) $candidate->work_item_id)) {
                $result->put((int) $candidate->work_item_id, $candidate->created_at);
            }
        }

        return $result;
    }

    /**
     * @param array<int, float> $values
     * @return array{p50: float, p85: float, p95: float, count: int}
     */
    private function percentiles(array $values): array {
        return [
            'p50' => $this->percentile($values, 50),
            'p85' => $this->percentile($values, 85),
            'p95' => $this->percentile($values, 95),
            'count' => count($values),
        ];
    }

    /** @param array<int, float> $values */
    private function percentile(array $values, int $percentile): float {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $index = (int) ceil($percentile / 100 * count($values)) - 1;

        return (float) $values[max(0, $index)];
    }

    /** @param array<int, int|float> $values */
    private function median(array $values): float {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (float) $values[$middle]
            : round(((float) $values[$middle - 1] + (float) $values[$middle]) / 2, 1);
    }

    private function board(AgileSprint $sprint): AgileBoard {
        return $sprint->board()->firstOrFail();
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int|string, mixed> $data
     */
    private function result(string $code, string $unit, array $filters, array $data): MetricResult {
        return new MetricResult($code, $unit, self::METRIC_VERSION, now(), $filters, $data);
    }
}
