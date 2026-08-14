<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditActivityReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{AuditLog, User};
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Lang;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Audit-Aktivitätsbericht: Aggregation der AuditLogs nach Event, User und Auditable-Typ.
 * Nur für Administratoren.
 */
class AuditActivityReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    private const RECENT_LIMIT = 100;

    public function index(Request $request): View|SymfonyResponse {
        abort_unless($this->viewerIsAdmin(), 403);

        [$from, $to] = $this->resolveRange($request);
        $filters = $this->standardFilters($request, ['user'], $from, $to);

        $base = AuditLog::query()->whereBetween('created_at', [$from, $to]);
        $filters->applyUserAndTeam($base);

        /** @var array<string, int> $byEvent */
        $byEvent = (clone $base)
            ->selectRaw('event, COUNT(*) as c')
            ->groupBy('event')
            ->orderByDesc('c')
            ->pluck('c', 'event')
            ->map(static fn($v): int => (int) $v)
            ->all();

        /** @var array<string, int> $byType */
        $byType = (clone $base)
            ->selectRaw('auditable_type, COUNT(*) as c')
            ->groupBy('auditable_type')
            ->orderByDesc('c')
            ->limit(20)
            ->pluck('c', 'auditable_type')
            ->map(static fn($v): int => (int) $v)
            ->all();

        $byUserRaw = (clone $base)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as c')
            ->groupBy('user_id')
            ->orderByDesc('c')
            ->limit(20)
            ->pluck('c', 'user_id')
            ->all();

        /** @var array<int, int> $byUserCounts */
        $byUserCounts = [];
        foreach ($byUserRaw as $uid => $c) {
            $byUserCounts[(int) $uid] = (int) $c;
        }

        /** @var Collection<int, User> $userModels */
        $userModels = $byUserCounts === []
            ? new Collection
            : User::query()->whereIn('id', array_keys($byUserCounts))->get(['id', 'name']);
        /** @var array<int, User> $usersById */
        $usersById = [];
        foreach ($userModels as $u) {
            $usersById[(int) $u->id] = $u;
        }

        /** @var array<int, array{user: ?User, count:int}> $byUser */
        $byUser = [];
        foreach ($byUserCounts as $uid => $c) {
            $byUser[$uid] = ['user' => $usersById[$uid] ?? null, 'count' => $c];
        }

        $total = (int) (clone $base)->count();
        $distinctUsers = count($byUserCounts);
        $distinctTypes = count($byType);

        /** @var Collection<int, AuditLog> $recent */
        $recent = (clone $base)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'user_id', 'event', 'auditable_type', 'auditable_id', 'ip', 'created_at']);

        $exportFilters = $filters->toAuditArray();
        $dailyBuckets = $this->dailyEventBuckets($base);
        $granularity = $this->bucketGranularity($from, $to);
        $timelineSeries = $this->timelineSeries($dailyBuckets, $granularity);
        [$monthlyEventSeries, $eventBands] = $this->monthlyEventSeries($dailyBuckets, $granularity);

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($byEvent, $byType, $byUser, $recent, $from->toDateString(), $to->toDateString(), $exportFilters, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($byEvent, $byType, $byUser, $recent, [
                'total' => $total,
                'users' => $distinctUsers,
                'types' => $distinctTypes,
            ], $from->toDateString(), $to->toDateString(), $timelineSeries, $exportFilters, $request);
        }

        return view('reports.audit-activity', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'byEvent' => $byEvent,
            'byType' => $byType,
            'byUser' => $byUser,
            'recent' => $recent,
            'totals' => [
                'total' => $total,
                'users' => $distinctUsers,
                'types' => $distinctTypes,
            ],
            'standardFilters' => $filters,
            'filterFields' => ['user'],
            'timelineSeries' => $timelineSeries,
            'topActorsSeries' => $this->topActorsSeries($byUser),
            'monthlyEventSeries' => $monthlyEventSeries,
            'eventBands' => $eventBands,
            'periodPhrase' => $this->periodPhrase($granularity),
            'periodAxis' => $this->periodAxisLabel($granularity),
            ...$this->standardFilterOptions(['user'], $filters),
        ]);
    }

    /**
     * Ereigniszählung je Tag × Event-Typ (eine Aggregatabfrage; DATE() läuft
     * auf MySQL wie SQLite) — Basis für Verlaufskurve und Monats-Stapel.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AuditLog>  $base
     * @return array<string, array<string, int>>  [Y-m-d][event] => Anzahl
     */
    private function dailyEventBuckets(\Illuminate\Database\Eloquent\Builder $base): array {
        /** @var array<string, array<string, int>> $buckets */
        $buckets = [];
        (clone $base)
            ->selectRaw('DATE(created_at) as day, event, COUNT(*) as c')
            ->groupBy('day', 'event')
            ->orderBy('day')
            ->get()
            ->each(function ($row) use (&$buckets): void {
                $buckets[(string) $row->getAttribute('day')][(string) $row->getAttribute('event')] = (int) $row->getAttribute('c');
            });

        return $buckets;
    }

    /**
     * Ereignisverlauf, aggregiert in der Granularität des Header-Zeitraums.
     *
     * @param  array<string, array<string, int>>  $dailyBuckets
     * @param  'day'|'week'|'month'|'quarter'  $granularity
     * @return list<array{x: string, y: int}>
     */
    private function timelineSeries(array $dailyBuckets, string $granularity): array {
        if ($dailyBuckets === []) {
            return []; // Leerzustand statt Null-Linie (§Diagramm-UX).
        }

        /** @var array<string, array{label: string, y: int}> $byBucket */
        $byBucket = [];
        foreach ($dailyBuckets as $day => $byEvent) {
            [$key, $label] = ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $day));
            $byBucket[$key] ??= ['label' => $label, 'y' => 0];
            $byBucket[$key]['y'] += array_sum($byEvent);
        }
        ksort($byBucket, SORT_STRING);

        $series = [];
        foreach ($byBucket as $bucket) {
            $series[] = ['x' => $bucket['label'], 'y' => $bucket['y']];
        }

        return $series;
    }

    /**
     * Top-Akteure (Top 15) für bar-h.
     *
     * @param  array<int, array{user: ?User, count:int}>  $byUser
     * @return list<array{x: string, y: int}>
     */
    private function topActorsSeries(array $byUser): array {
        return array_values(collect($byUser)
            ->map(static fn(array $row): array => [
                'x' => $row['user'] !== null ? (string) $row['user']->name : '—',
                'y' => $row['count'],
            ])
            ->filter(static fn(array $point): bool => $point['y'] > 0)
            ->take(15)
            ->all());
    }

    /**
     * Ereignisse je Bucket (adaptiv zur Header-Granularität), gestapelt nach
     * Event-Typ (Top 4 + Rest).
     *
     * @param  array<string, array<string, int>>  $dailyBuckets
     * @param  'day'|'week'|'month'|'quarter'  $granularity
     * @return array{0: list<array<string, string|int>>, 1: list<array{key: string, label: string}>}
     */
    private function monthlyEventSeries(array $dailyBuckets, string $granularity): array {
        /** @var array<string, int> $eventTotals */
        $eventTotals = [];
        /** @var array<string, array<string, int>> $byBucket */
        $byBucket = [];
        /** @var array<string, string> $labelByKey */
        $labelByKey = [];
        foreach ($dailyBuckets as $day => $byEvent) {
            [$key, $label] = ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $day));
            $labelByKey[$key] = $label;
            foreach ($byEvent as $event => $count) {
                $eventTotals[$event] = ($eventTotals[$event] ?? 0) + $count;
                $byBucket[$key][$event] = ($byBucket[$key][$event] ?? 0) + $count;
            }
        }
        if ($byBucket === []) {
            return [[], []];
        }

        arsort($eventTotals);
        $topEvents = array_slice(array_keys($eventTotals), 0, 4);
        $hasRest = count($eventTotals) > count($topEvents);

        $bands = array_map(fn(string $event): array => [
            'key' => $event,
            'label' => Lang::has('audit-events.' . $event) ? (string) __('audit-events.' . $event) : $event,
        ], $topEvents);
        if ($hasRest) {
            $bands[] = ['key' => 'other', 'label' => (string) __('Sonstige')];
        }

        ksort($byBucket, SORT_STRING);
        $series = [];
        foreach ($byBucket as $key => $byEvent) {
            $point = ['x' => $labelByKey[$key]];
            $rest = 0;
            foreach ($byEvent as $event => $count) {
                if (in_array($event, $topEvents, true)) {
                    $point[$event] = ($point[$event] ?? 0) + $count;
                } else {
                    $rest += $count;
                }
            }
            foreach ($topEvents as $event) {
                $point[$event] ??= 0;
            }
            if ($hasRest) {
                $point['other'] = $rest;
            }
            $series[] = $point;
        }

        return [$series, $bands];
    }

    /**
     * @param  array<string, int>  $byEvent
     * @param  array<string, int>  $byType
     * @param  array<int, array{user: ?User, count:int}>  $byUser
     * @param  Collection<int, AuditLog>  $recent
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(array $byEvent, array $byType, array $byUser, $recent, string $from, string $to, array $exportFilters, Request $request): Response {
        $filename = sprintf('audit_%s_%s.csv', $from, $to);
        $rows = [];
        $rows[] = ['Bereich', 'Schlüssel', 'Anzahl'];
        foreach ($byEvent as $ev => $c) {
            $rows[] = ['Event', $ev, $c];
        }
        foreach ($byType as $t => $c) {
            $rows[] = ['Typ', $this->shortType($t), $c];
        }
        foreach ($byUser as $u) {
            $rows[] = ['User', $u['user'] !== null ? $u['user']->name : '—', $u['count']];
        }
        $rows[] = [];
        $rows[] = ['Zeitpunkt', 'User', 'Event', 'Typ', 'ID', 'IP'];
        foreach ($recent as $log) {
            $rows[] = [
                $log->created_at?->format('Y-m-d H:i:s') ?? '',
                $log->user !== null ? $log->user->name : '—',
                $log->event,
                $this->shortType((string) $log->auditable_type),
                (string) $log->auditable_id,
                $log->ip?->getValue() ?? '',
            ];
        }

        return $this->csvWithMetadata($rows, $filename, 'audit-activity', $exportFilters, $request);
    }

    /**
     * @param  array<string, int>  $byEvent
     * @param  array<string, int>  $byType
     * @param  array<int, array{user: ?User, count:int}>  $byUser
     * @param  Collection<int, AuditLog>  $recent
     * @param  array{total:int, users:int, types:int}  $totals
     * @param  list<array{x: string, y: int}>  $timelineSeries
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $byEvent, array $byType, array $byUser, $recent, array $totals, string $from, string $to, array $timelineSeries, array $exportFilters, Request $request): SymfonyResponse {
        $filename = sprintf('audit_%s_%s.pdf', $from, $to);
        // Zeitreihe im Druck als bar-h (letzte 24 Datenpunkte, Nullen raus).
        $printSeries = array_values(array_filter($timelineSeries, static fn(array $point): bool => $point['y'] > 0));
        $printSeries = array_slice($printSeries, -24);
        return $this->pdfDownload('reports.pdf.audit-activity', [
            'byEvent' => $byEvent,
            'byType' => $byType,
            'byUser' => $byUser,
            'recent' => $recent,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Ereignisse im Verlauf'),
                'unit' => __('Events'),
                'xLabel' => __('Zeitraum'),
                'yLabel' => __('Events'),
                'series' => $printSeries,
                'note' => __('Vereinfachte Druckdarstellung.'),
            ],
        ], $filename, request: $request, reportCode: 'audit-activity', filters: $exportFilters);
    }

    private function shortType(string $fqcn): string {
        $parts = explode('\\', $fqcn);

        return (string) end($parts);
    }
}
