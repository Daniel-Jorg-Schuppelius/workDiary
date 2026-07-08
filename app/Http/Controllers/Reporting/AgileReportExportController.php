<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileReportExportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\Agile\{AgileBoard, AgileEvent, AgileSprint};
use App\Models\Project;
use App\Services\Agile\AgileMetricsService;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Drilldowns und Exporte des agilen Berichtszentrums (Feature 064,
 * P11/MVP-149). Drilldown NUR über signierten, kurzlebigen Link PLUS
 * Report-Recht (Whitebox-Leitplanke Export-Authz) mit sichtbarer
 * Summen-Konsistenzprüfung; CSV mit Exportkopf (Reportcode, Filter,
 * metric_version, Berechnungsstand, Einheit) und Audit report.exported;
 * PDF über RendersReportPdf (pdf-toolkit).
 */
class AgileReportExportController extends Controller {
    use RendersReportPdf;
    use WritesReportCsv;

    private const CSV_METRICS = ['burndown', 'velocity', 'throughput', 'blocked', 'cfd', 'quality'];

    public function __construct(private readonly AgileMetricsService $metrics) {}

    /**
     * Drilldown hinter einem Datenpunkt: kind ∈ throughput_week |
     * blocked_reason | quality_week. `expected` trägt den Kennzahlwert des
     * Punktes — weicht die Trefferzahl ab, erscheint ein sichtbarer Hinweis.
     */
    public function drilldown(Request $request, Project $project): View {
        abort_unless($request->hasValidSignature(), 403);
        Gate::authorize(Permission::AgileReportView->value);
        Gate::authorize('view', $project);

        $board = AgileBoard::query()->where('project_id', $project->id)->firstOrFail();
        $kind = (string) $request->query('kind');
        $key = (string) $request->query('key');
        $expected = (int) $request->query('expected', 0);

        [$title, $rows] = match ($kind) {
            'throughput_week' => $this->throughputRows($board, $key),
            'blocked_reason' => $this->blockedRows($board, $key),
            'quality_week' => $this->qualityRows($board, $key),
            default => abort(404),
        };

        return view('agile.reports.drilldown', [
            'project' => $project,
            'title' => $title,
            'kind' => $kind,
            'key' => $key,
            'rows' => $rows,
            'expected' => $expected,
            'consistent' => count($rows) === $expected,
        ]);
    }

    /** CSV der Diagramm-Rohdaten einer Kennzahl (Exportkopf + Audit). */
    public function csv(Request $request, Project $project, string $metric): Response {
        Gate::authorize(Permission::AgileReportView->value);
        Gate::authorize('view', $project);
        abort_unless(in_array($metric, self::CSV_METRICS, true), 404);

        $board = AgileBoard::query()->where('project_id', $project->id)->firstOrFail();
        $result = $this->metricResult($board, $metric, $request);
        $filters = [...$result->filters, 'metric' => $metric];

        $rows = [
            ['metric_version', (string) $result->metricVersion],
            ['unit', $result->unit],
            ['computed_at', $result->computedAt->toIso8601String()],
            [''],
            ...$this->csvRows($metric, $result->data),
        ];

        $this->auditExport($request, 'agile_' . $metric, 'csv', $filters);

        return $this->csvWithMetadata(
            array_map(fn(array $row): array => array_values($row), $rows),
            sprintf('agile_%s_%s.csv', $metric, now()->format('Ymd')),
            'agile_' . $metric . '_v' . $result->metricVersion,
            $filters,
        );
    }

    /** Sprint-Cockpit als PDF (Kennzahlen-Tabellen, pdf-toolkit). */
    public function pdf(Request $request, Project $project): SymfonyResponse {
        Gate::authorize(Permission::AgileReportView->value);
        Gate::authorize('view', $project);

        $board = AgileBoard::query()->where('project_id', $project->id)->firstOrFail();
        $sprint = AgileSprint::query()
            ->where('board_id', $board->id)
            ->whereNotNull('started_at')
            ->orderByDesc('id')
            ->first();

        $this->auditExport($request, 'agile_sprint_cockpit', 'pdf', ['project_id' => $project->id, 'sprint_id' => $sprint?->id]);

        return $this->pdfDownload('agile.reports.pdf', [
            'project' => $project,
            'board' => $board,
            'sprint' => $sprint,
            'burndown' => $sprint?->started_at !== null ? $this->metrics->burndown($sprint) : null,
            'velocity' => $this->metrics->velocity($board),
            'quality' => $this->metrics->qualitySeries($board),
        ], sprintf('agile_sprint_cockpit_%s.pdf', now()->format('Ymd')));
    }

    private function metricResult(AgileBoard $board, string $metric, Request $request): \App\Services\Agile\Metrics\MetricResult {
        return match ($metric) {
            'burndown' => $this->metrics->burndown(
                AgileSprint::query()->where('board_id', $board->id)->whereNotNull('started_at')->orderByDesc('id')->firstOrFail(),
            ),
            'velocity' => $this->metrics->velocity($board),
            'throughput' => $this->metrics->throughput($board),
            'blocked' => $this->metrics->blockedDurations($board),
            'quality' => $this->metrics->qualitySeries($board),
            default => $this->metrics->cfd(
                $board,
                now()->subWeeks(6)->startOfDay(),
                now(),
            ),
        };
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int, array<int, string|int|float|null>>
     */
    private function csvRows(string $metric, array $data): array {
        return match ($metric) {
            'burndown' => [
                ['date', 'remaining', 'scope_delta'],
                ...array_map(fn(array $row): array => [$row['date'], $row['remaining'], $row['scope_delta']], (array) $data['series']),
            ],
            'velocity' => [
                ['sprint', 'done_points', 'committed_points', 'scope_added'],
                ...array_map(fn(array $row): array => [$row['sprint'], $row['done_points'], $row['committed_points'], $row['scope_added']], (array) $data['sprints']),
            ],
            'throughput' => [
                ['week', 'done_items'],
                ...collect((array) $data['weeks'])->map(fn($count, $week): array => [$week, $count])->values()->all(),
            ],
            'blocked' => [
                ['reason', 'hours', 'count'],
                ...collect((array) $data['reasons'])->map(fn($row, $reason): array => [$reason, $row['hours'], $row['count']])->values()->all(),
            ],
            'quality' => [
                ['week', 'reopened', 'overrides'],
                ...collect((array) $data['weeks'])->map(fn($row, $week): array => [$week, $row['reopened'], $row['overrides']])->values()->all(),
            ],
            default => [
                ['date', 'open', 'in_progress', 'done'],
                ...array_map(fn(array $row): array => [$row['date'], $row['open'], $row['in_progress'], $row['done']], (array) $data['series']),
            ],
        };
    }

    /** @return array{0: string, 1: array<int, array<string, mixed>>} */
    private function throughputRows(AgileBoard $board, string $week): array {
        $events = AgileEvent::query()
            ->where('board_id', $board->id)
            ->where('event', 'column.moved')
            ->orderBy('created_at')->orderBy('id')
            ->with('workItem.task')
            ->get();
        $categories = $this->categories($board);

        $seen = [];
        $rows = [];
        foreach ($events as $event) {
            if ($event->work_item_id === null || isset($seen[(int) $event->work_item_id])) {
                continue;
            }
            if (($categories[(int) ($event->payload['to'] ?? 0)] ?? null) !== 'done') {
                continue;
            }
            $seen[(int) $event->work_item_id] = true;
            if ($event->created_at->format('o-\WW') !== $week) {
                continue;
            }
            $rows[] = [
                'title' => $event->workItem?->task?->title,
                'at' => $event->created_at->isoFormat('L LT'),
                'detail' => null,
            ];
        }

        return [(string) __('Erledigt in Woche :week', ['week' => $week]), $rows];
    }

    /** @return array{0: string, 1: array<int, array<string, mixed>>} */
    private function blockedRows(AgileBoard $board, string $reason): array {
        $open = [];
        $rows = [];
        foreach (AgileEvent::query()->where('board_id', $board->id)->orderBy('created_at')->orderBy('id')->with('workItem.task')->get() as $event) {
            if ($event->work_item_id === null) {
                continue;
            }
            $itemId = (int) $event->work_item_id;
            if ($event->event === 'item.blocked') {
                $open[$itemId] = $event;
            }
            if ($event->event === 'item.unblocked' && isset($open[$itemId])) {
                if ((string) ($open[$itemId]->payload['reason'] ?? '') === $reason) {
                    $rows[] = [
                        'title' => $event->workItem?->task?->title,
                        'at' => $open[$itemId]->created_at->isoFormat('L LT'),
                        'detail' => __(':hours h blockiert', ['hours' => round($open[$itemId]->created_at->diffInMinutes($event->created_at) / 60, 1)]),
                    ];
                }
                unset($open[$itemId]);
            }
        }

        return [(string) __('Blockierungen mit Grund „:reason"', ['reason' => $reason]), $rows];
    }

    /** @return array{0: string, 1: array<int, array<string, mixed>>} */
    private function qualityRows(AgileBoard $board, string $week): array {
        $categories = $this->categories($board);

        $rows = [];
        foreach (AgileEvent::query()->where('board_id', $board->id)->orderBy('created_at')->orderBy('id')->with('workItem.task')->get() as $event) {
            if ($event->created_at->format('o-\WW') !== $week) {
                continue;
            }
            $isReopen = $event->event === 'column.moved'
                && ($categories[(int) ($event->payload['from'] ?? 0)] ?? null) === 'done'
                && ($categories[(int) ($event->payload['to'] ?? 0)] ?? null) !== 'done';
            $isOverride = str_starts_with($event->event, 'override.');
            if (! $isReopen && ! $isOverride) {
                continue;
            }
            $rows[] = [
                'title' => $event->workItem?->task?->title,
                'at' => $event->created_at->isoFormat('L LT'),
                'detail' => $isReopen ? __('Wiederöffnung') : $event->event . ' — ' . (string) ($event->payload['reason'] ?? ''),
            ];
        }

        return [(string) __('Qualitätsereignisse in Woche :week', ['week' => $week]), $rows];
    }

    /** @return array<int, string> */
    private function categories(AgileBoard $board): array {
        return \App\Models\Agile\AgileBoardColumn::query()
            ->where('board_id', $board->id)
            ->get(['id', 'category'])
            ->mapWithKeys(fn($c): array => [(int) $c->id => $c->category->value])
            ->all();
    }
}
