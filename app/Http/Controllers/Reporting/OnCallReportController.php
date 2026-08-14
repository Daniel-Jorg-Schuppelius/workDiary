<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnCallReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{EmergencyAssignment, OnCallShift, User};
use App\Services\Reporting\ReportFilters;
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Notdienst-Auswertung: Bereitschaftsstunden und tatsächliche
 * Einsatzzeiten je Mitarbeiter im gewählten Zeitraum.
 */
class OnCallReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        [$fromDate, $toDate] = $this->resolveRange($request);
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $filters = $this->standardFilters($request, ['user', 'team'], $fromDate, $toDate, scope: $scope);

        $rows = $this->aggregate($fromDate, $toDate, $scope, $userId, $filters);
        $totals = $this->totals($rows);
        [$heatmapRows, $weekLabels] = $this->standbyHeatmap($rows, $fromDate, $toDate, $scope, $userId, $filters);
        $exportFilters = array_merge(['scope' => $scope], $filters->toAuditArray());

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($rows, $totals, $from, $to, $request, $exportFilters);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($rows, $totals, $from, $to, $scope, $heatmapRows, $weekLabels, $request, $exportFilters);
        }

        return view('reports.on-call', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'rows' => $rows,
            'totals' => $totals,
            'standardFilters' => $filters,
            'filterFields' => ['user', 'team'],
            'heatmapRows' => $heatmapRows,
            'weekLabels' => $weekLabels,
            'monthlyAssignmentSeries' => $this->monthlyAssignmentSeries($fromDate, $toDate, $scope, $userId, $filters),
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($fromDate, $toDate)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($fromDate, $toDate)),
            ...$this->standardFilterOptions(['user', 'team'], $filters),
        ]);
    }
    /**
     * @return array<int, array{
     *   user: User,
     *   shift_count: int,
     *   shift_minutes: int,
     *   assignment_count: int,
     *   assignment_minutes: int,
     *   ratio: float|null
     * }>
     */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId, ReportFilters $filters): array {
        $shiftsQ = OnCallShift::query()
            ->where('is_archived', false)
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from);
        $assignmentsQ = EmergencyAssignment::query()
            ->where('is_archived', false)
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from);
        if ($scope === 'mine') {
            $shiftsQ->where('user_id', $userId);
            $assignmentsQ->where('user_id', $userId);
        }
        $filters->applyUserAndTeam($shiftsQ);
        $filters->applyUserAndTeam($assignmentsQ);

        /** @var array<int, array{shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int}> $byUser */
        $byUser = [];
        $ensure = static function (array &$byUser, int $uid): void {
            if (! isset($byUser[$uid])) {
                $byUser[$uid] = [
                    'shift_count' => 0,
                    'shift_minutes' => 0,
                    'assignment_count' => 0,
                    'assignment_minutes' => 0,
                ];
            }
        };

        foreach ($shiftsQ->get() as $shift) {
            $uid = (int) $shift->user_id;
            $ensure($byUser, $uid);
            $start = $shift->start_at->greaterThan($from) ? $shift->start_at : $from;
            $end = $shift->end_at->lessThan($to) ? $shift->end_at : $to;
            $minutes = max(0, (int) $start->diffInMinutes($end, true));
            $byUser[$uid]['shift_count']++;
            $byUser[$uid]['shift_minutes'] += $minutes;
        }

        foreach ($assignmentsQ->get() as $assignment) {
            $uid = (int) $assignment->user_id;
            $ensure($byUser, $uid);
            $start = $assignment->start_at->greaterThan($from) ? $assignment->start_at : $from;
            $end = $assignment->end_at->lessThan($to) ? $assignment->end_at : $to;
            $minutes = max(0, (int) $start->diffInMinutes($end, true));
            $byUser[$uid]['assignment_count']++;
            $byUser[$uid]['assignment_minutes'] += $minutes;
        }

        if ($byUser === []) {
            return [];
        }

        /** @var Collection<int, User> $users */
        $users = User::query()->whereIn('id', array_keys($byUser))->orderBy('name')->get();
        $rows = [];
        foreach ($users as $user) {
            $uid = (int) $user->id;
            $u = $byUser[$uid];
            $ratio = $u['shift_minutes'] > 0
                ? $u['assignment_minutes'] / $u['shift_minutes']
                : null;
            $rows[] = [
                'user' => $user,
                'shift_count' => (int) $u['shift_count'],
                'shift_minutes' => (int) $u['shift_minutes'],
                'assignment_count' => (int) $u['assignment_count'],
                'assignment_minutes' => (int) $u['assignment_minutes'],
                'ratio' => $ratio,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{user: User, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}>  $rows
     * @return array{users:int, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}
     */
    private function totals(array $rows): array {
        $shiftCount = 0;
        $shiftMin = 0;
        $assignCount = 0;
        $assignMin = 0;
        foreach ($rows as $r) {
            $shiftCount += $r['shift_count'];
            $shiftMin += $r['shift_minutes'];
            $assignCount += $r['assignment_count'];
            $assignMin += $r['assignment_minutes'];
        }

        return [
            'users' => count($rows),
            'shift_count' => $shiftCount,
            'shift_minutes' => $shiftMin,
            'assignment_count' => $assignCount,
            'assignment_minutes' => $assignMin,
            'ratio' => $shiftMin > 0 ? $assignMin / $shiftMin : null,
        ];
    }

    /**
     * Heatmap Mitarbeiter × ISO-Woche (Bereitschaftsminuten, anteilig auf die
     * Wochen aufgeteilt und auf den Zeitraum geklemmt). Zeilenreihenfolge wie
     * die Tabelle; leere Zeilenliste → Empty-State der Komponente.
     *
     * @param  array<int, array{user: User, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}>  $rows
     * @return array{0: list<array{label: string, cells: list<array{value: int}|null>}>, 1: list<string>}
     */
    private function standbyHeatmap(array $rows, CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId, ReportFilters $filters): array {
        $weeks = [];
        $cursor = $from->startOfWeek();
        for ($i = 0; $i < 160 && $cursor->lte($to); $i++) {
            $weeks[sprintf('%04d-W%02d', $cursor->isoWeekYear, $cursor->isoWeek)] = 'KW ' . $cursor->isoWeek;
            $cursor = $cursor->addWeek();
        }
        if ($rows === [] || $weeks === []) {
            return [[], array_values($weeks)];
        }

        $shiftsQ = OnCallShift::query()
            ->where('is_archived', false)
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from);
        if ($scope === 'mine') {
            $shiftsQ->where('user_id', $userId);
        }
        $filters->applyUserAndTeam($shiftsQ);

        /** @var array<int, array<string, int>> $byUserWeek */
        $byUserWeek = [];
        foreach ($shiftsQ->get() as $shift) {
            $uid = (int) $shift->user_id;
            $start = $shift->start_at->greaterThan($from) ? $shift->start_at->toImmutable() : $from;
            $end = $shift->end_at->lessThan($to) ? $shift->end_at->toImmutable() : $to;
            // Minuten wochenweise zuschneiden (Schichten laufen über Wochengrenzen).
            $weekCursor = $start;
            while ($weekCursor->lessThan($end)) {
                $weekEnd = $weekCursor->endOfWeek();
                $sliceEnd = $weekEnd->lessThan($end) ? $weekEnd : $end;
                $key = sprintf('%04d-W%02d', $weekCursor->isoWeekYear, $weekCursor->isoWeek);
                $minutes = max(0, (int) $weekCursor->diffInMinutes($sliceEnd, true));
                $byUserWeek[$uid][$key] = ($byUserWeek[$uid][$key] ?? 0) + $minutes;
                $weekCursor = $weekCursor->startOfWeek()->addWeek();
            }
        }

        $heatmapRows = [];
        foreach ($rows as $row) {
            $uid = (int) $row['user']->id;
            $heatmapRows[] = [
                'label' => (string) $row['user']->name,
                'cells' => array_map(
                    static fn(string $weekKey): array => ['value' => (int) ($byUserWeek[$uid][$weekKey] ?? 0)],
                    array_keys($weeks),
                ),
            ];
        }

        return [$heatmapRows, array_values($weeks)];
    }

    /**
     * Einsätze je Bucket (adaptiv zur Header-Granularität) über den Zeitraum —
     * leere Serie statt Null-Achse (§Diagramm-UX). Zählung nach (geklemmtem)
     * Einsatzbeginn.
     *
     * @return list<array{x: string, y: int}>
     */
    private function monthlyAssignmentSeries(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId, ReportFilters $filters): array {
        $granularity = $this->bucketGranularity($from, $to);
        $q = EmergencyAssignment::query()
            ->where('is_archived', false)
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from);
        if ($scope === 'mine') {
            $q->where('user_id', $userId);
        }
        $filters->applyUserAndTeam($q);

        /** @var array<string, int> $byMonth */
        $byMonth = [];
        foreach ($q->get() as $assignment) {
            $start = $assignment->start_at->greaterThan($from) ? $assignment->start_at->toImmutable() : $from;
            $key = ChartBucket::keyLabel($granularity, $start)[0];
            $byMonth[$key] = ($byMonth[$key] ?? 0) + 1;
        }
        if ($byMonth === []) {
            return [];
        }

        $series = [];
        foreach ($this->buildBucketsInRange($from, $to) as $bucket) {
            $series[] = ['x' => $bucket['shortLabel'], 'y' => (int) ($byMonth[$bucket['key']] ?? 0)];
        }

        return $series;
    }

    /**
     * @param  array<int, array{user: User, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}>  $rows
     * @param  array{users:int, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}  $totals
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(array $rows, array $totals, string $from, string $to, Request $request, array $exportFilters): Response {
        $filename = sprintf('notdienst_%s_%s.csv', $from, $to);
        $fmt = static function (int $minutes): string {
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;

            return sprintf('%d:%02d', $h, $m);
        };

        $out = [['Mitarbeiter', 'Schichten', 'Bereitschaft (h)', 'Einsätze', 'Einsatzzeit (h)', 'Aktiv-Anteil %']];
        foreach ($rows as $r) {
            $out[] = [
                (string) $r['user']->name,
                $r['shift_count'],
                $fmt($r['shift_minutes']),
                $r['assignment_count'],
                $fmt($r['assignment_minutes']),
                $r['ratio'] !== null ? NumberHelper::toUSFormat($r['ratio'] * 100, 1) : '',
            ];
        }
        $out[] = [
            'Gesamt',
            $totals['shift_count'],
            $fmt($totals['shift_minutes']),
            $totals['assignment_count'],
            $fmt($totals['assignment_minutes']),
            $totals['ratio'] !== null ? NumberHelper::toUSFormat($totals['ratio'] * 100, 1) : '',
        ];

        return $this->csvWithMetadata($out, $filename, 'on-call', $exportFilters, $request);
    }

    /**
     * @param  array<int, array{user: User, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}>  $rows
     * @param  array{users:int, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}  $totals
     * @param  list<array{label: string, cells: list<array{value: int}|null>}>  $heatmapRows
     * @param  list<string>  $weekLabels
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $rows, array $totals, string $from, string $to, string $scope, array $heatmapRows, array $weekLabels, Request $request, array $exportFilters): SymfonyResponse {
        $filename = sprintf('notdienst_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.on-call', [
            'rows' => $rows,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'chart' => [
                'type' => 'heatmap',
                'title' => __('Bereitschaft je Mitarbeiter und Woche'),
                'unit' => 'h',
                'xLabel' => __('Mitarbeiter'),
                'rows' => $heatmapRows,
                'colLabels' => $weekLabels,
                'format' => fn(float $minutes): string => intdiv((int) $minutes, 60) . ':' . str_pad((string) ((int) $minutes % 60), 2, '0', STR_PAD_LEFT),
            ],
        ], $filename, 'landscape', $request, 'on-call', $exportFilters);
    }
}
