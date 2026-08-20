<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileFlowReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Agile\{AgileBoard, AgileEvent};
use App\Models\Project;
use App\Services\Agile\AgileMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Fluss-Bericht (Feature 064, P9/MVP-147): CFD, Durchsatz, Lead/Cycle mit
 * Control Chart, WIP-Historie + Aging-WIP, Blockier-Pareto, Backlog-Zu-/
 * Abgang, Flow-Effizienz (nur bei vollständiger Spalten-Klassifikation —
 * sonst Datenqualitäts-Hinweis). Berechnung NUR über AgileMetricsService.
 */
class AgileFlowReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(private readonly AgileMetricsService $metrics) {}

    public function index(Request $request, Project $project): View {
        Gate::authorize(Permission::AgileReportView->value);
        Gate::authorize('view', $project);

        $board = AgileBoard::query()->where('project_id', $project->id)->firstOrFail();

        // Zeitraum: ?from/?to, Default letzte 6 Wochen bzw. ab erstem Event.
        $firstEventAt = AgileEvent::query()->where('board_id', $board->id)->min('created_at');
        [$rangeFrom, $rangeTo] = $this->resolveRangeWithDefault($request, static fn (): array => [
            \Carbon\CarbonImmutable::parse((string) ($firstEventAt !== null
                ? Carbon::parse((string) $firstEventAt)->max(now()->subWeeks(6))
                : now()->subWeeks(6))),
            \Carbon\CarbonImmutable::parse((string) now()),
        ]);
        // AgileMetricsService erwartet mutable Carbon-Instanzen.
        $from = Carbon::instance($rangeFrom->toDateTime());
        $to = Carbon::instance($rangeTo->toDateTime());

        // Drilldown-Links (P11): signiert + kurzlebig, expected = Punktwert.
        $throughput = $this->metrics->throughput($board);
        $throughputSeries = collect((array) $throughput->data['weeks'])->map(fn($count, $week): array => [
            'x' => $week,
            'y' => $count,
            'url' => \Illuminate\Support\Facades\URL::temporarySignedRoute('agile.reports.drilldown', now()->addMinutes(30), [
                'project' => $project,
                'kind' => 'throughput_week',
                'key' => $week,
                'expected' => $count,
            ]),
        ])->values()->all();

        $blocked = $this->metrics->blockedDurations($board);
        $blockedSeries = collect((array) $blocked->data['reasons'])->map(fn($row, $reason): array => [
            'x' => $reason,
            'y' => $row['hours'],
            'url' => \Illuminate\Support\Facades\URL::temporarySignedRoute('agile.reports.drilldown', now()->addMinutes(30), [
                'project' => $project,
                'kind' => 'blocked_reason',
                'key' => $reason,
                'expected' => $row['count'],
            ]),
        ])->values()->all();

        return view('agile.reports.flow', [
            'project' => $project,
            'board' => $board,
            'from' => $from,
            'to' => $to,
            'cfd' => $this->metrics->cfd($board, $from, $to),
            'wip' => $this->metrics->wipSeries($board, $from, $to),
            'throughput' => $throughput,
            'throughputSeries' => $throughputSeries,
            'leadCycle' => $this->metrics->leadCycleTime($board),
            'cycleItems' => $this->cycleItems($board),
            'aging' => $this->metrics->agingWip($board),
            'blocked' => $blocked,
            'blockedSeries' => $blockedSeries,
            'backlogFlow' => $this->metrics->backlogFlow($board),
            'flowEfficiency' => $this->metrics->flowEfficiency($board),
        ]);
    }

    /**
     * Control-Chart-Punkte: Cycle-Time je erledigtem Element in Stunden
     * (erster in_progress- bis erster done-Eintritt), chronologisch.
     *
     * @return array<int, array{x: string, y: float, label: string|null}>
     */
    private function cycleItems(AgileBoard $board): array {
        $events = AgileEvent::query()
            ->where('board_id', $board->id)
            ->where('event', 'column.moved')
            ->orderBy('created_at')
            ->orderBy('id')
            ->with('workItem.task')
            ->get();

        $categories = \App\Models\Agile\AgileBoardColumn::query()
            ->where('board_id', $board->id)
            ->get(['id', 'category'])
            ->mapWithKeys(fn($c): array => [(int) $c->id => $c->category->value])
            ->all();

        $started = [];
        $rows = [];
        foreach ($events as $event) {
            if ($event->work_item_id === null) {
                continue;
            }
            $itemId = (int) $event->work_item_id;
            $target = $categories[(int) ($event->payload['to'] ?? 0)] ?? null;
            if ($target === 'in_progress' && ! isset($started[$itemId])) {
                $started[$itemId] = $event->created_at;
            }
            if ($target === 'done' && isset($started[$itemId]) && ! isset($rows[$itemId])) {
                $rows[$itemId] = [
                    'x' => $event->created_at->toDateString(),
                    'y' => round($started[$itemId]->diffInMinutes($event->created_at) / 60, 1),
                    'label' => $event->workItem?->task?->title,
                ];
            }
        }

        return array_values($rows);
    }
}
