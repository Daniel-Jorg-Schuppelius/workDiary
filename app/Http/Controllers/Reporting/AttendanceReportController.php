<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Attendance\AttendanceStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Attendance, TimeEntry, User, WorkSchedule};
use App\Services\Reporting\ReportFilters;
use Carbon\{Carbon, CarbonImmutable, CarbonInterface, CarbonPeriod};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Anwesenheits-Auswertung: tatsächliche Attendance-Minuten vs. WorkSchedule-Soll
 * und gebuchte TimeEntry-Minuten je Mitarbeiter.
 */
class AttendanceReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    /** Ab dieser Zeitraumlänge wird die Zeitverlauf-Serie wochenweise aggregiert. */
    private const WEEKLY_THRESHOLD_DAYS = 62;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        [$from, $to] = $this->resolveRange($request);
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $filters = $this->standardFilters($request, ['user', 'team'], $from, $to, scope: $scope);

        $rows = $this->aggregate($from, $to, $scope, $userId, $filters);
        $exportFilters = array_merge(['scope' => $scope], $filters->toAuditArray());
        $weekdayLabels = $this->weekdayLabels();
        $heatmapRows = $this->weekdayHeatmapRows($from, $to, $rows);

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($rows, $fromStr, $toStr, $exportFilters, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($rows, $fromStr, $toStr, $scope, $heatmapRows, $weekdayLabels, $exportFilters, $request);
        }

        return view('reports.attendance', [
            'rows' => $rows,
            'from' => $fromStr,
            'to' => $toStr,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'totals' => $this->totals($rows),
            'standardFilters' => $filters,
            'filterFields' => ['user', 'team'],
            'heatmapRows' => $heatmapRows,
            'weekdayLabels' => $weekdayLabels,
            'timelineSeries' => $this->timelineSeries($from, $to, $rows),
            ...$this->standardFilterOptions(['user', 'team'], $filters),
        ]);
    }

    /**
     * Lokalisierte Wochentagskürzel Mo–So für die Heatmap-Spalten.
     *
     * @return list<string>
     */
    private function weekdayLabels(): array {
        $locale = app()->getLocale();
        $monday = Carbon::now()->startOfWeek();
        $labels = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $monday->copy()->addDays($i);
            $day->locale($locale);
            $labels[] = $day->isoFormat('dd');
        }

        return $labels;
    }

    /**
     * Heatmap Mitarbeiter × Wochentag (Anwesenheitsminuten; Anzeige h:mm).
     *
     * @param  array<int, array{user: User, attendance_minutes:int, time_entry_minutes:int, target_minutes:int, workdays:int, variance:int}>  $rows
     * @return list<array{label: string, cells: list<array{value: int}>}>
     */
    private function weekdayHeatmapRows(CarbonImmutable $from, CarbonImmutable $to, array $rows): array {
        if ($rows === []) {
            return [];
        }
        $userIds = array_map(static fn(array $r): int => (int) $r['user']->id, $rows);

        $attendances = Attendance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [AttendanceStatus::Cancelled->value, AttendanceStatus::Open->value])
            ->get(['user_id', 'date', 'duration_minutes']);
        if ($attendances->isEmpty()) {
            return []; // Leerzustand statt Null-Matrix (§Diagramm-UX).
        }

        /** @var array<int, array<int, int>> $byUserDay [user_id][0..6] => minutes */
        $byUserDay = [];
        foreach ($attendances as $a) {
            $idx = CarbonImmutable::parse((string) $a->date)->dayOfWeekIso - 1;
            $uid = (int) $a->user_id;
            $byUserDay[$uid][$idx] = ($byUserDay[$uid][$idx] ?? 0) + (int) $a->duration_minutes;
        }

        $heatmap = [];
        foreach ($rows as $r) {
            $uid = (int) $r['user']->id;
            $cells = [];
            for ($i = 0; $i < 7; $i++) {
                $cells[] = ['value' => $byUserDay[$uid][$i] ?? 0];
            }
            $heatmap[] = ['label' => (string) $r['user']->name, 'cells' => $cells];
        }

        return $heatmap;
    }

    /**
     * Anwesenheitsstunden im Zeitverlauf — je Tag, bei langen Zeiträumen je
     * ISO-Woche (über alle gefilterten Mitarbeiter summiert).
     *
     * @param  array<int, array{user: User, attendance_minutes:int, time_entry_minutes:int, target_minutes:int, workdays:int, variance:int}>  $rows
     * @return list<array{x: string, y: float}>
     */
    private function timelineSeries(CarbonImmutable $from, CarbonImmutable $to, array $rows): array {
        if ($rows === []) {
            return [];
        }
        $userIds = array_map(static fn(array $r): int => (int) $r['user']->id, $rows);

        /** @var array<string, int> $minutesByDate */
        $minutesByDate = Attendance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [AttendanceStatus::Cancelled->value, AttendanceStatus::Open->value])
            ->selectRaw('date, COALESCE(SUM(duration_minutes), 0) as m')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('m', 'date')
            ->map(static fn($v): int => (int) $v)
            ->all();
        if ($minutesByDate === [] || array_sum($minutesByDate) === 0) {
            return []; // Leerzustand statt Null-Linie (§Diagramm-UX).
        }

        $weekly = (int) $from->diffInDays($to) > self::WEEKLY_THRESHOLD_DAYS;
        $buckets = [];
        foreach ($minutesByDate as $dateStr => $minutes) {
            $date = CarbonImmutable::parse((string) $dateStr);
            $key = $weekly ? sprintf('KW %02d/%04d', $date->isoWeek, $date->isoWeekYear) : $date->isoFormat('DD.MM.');
            $buckets[$key] = ($buckets[$key] ?? 0) + $minutes;
        }

        $series = [];
        foreach ($buckets as $key => $minutes) {
            $series[] = ['x' => (string) $key, 'y' => round($minutes / 60, 1)];
        }

        return $series;
    }

    /**
     * @return array<int, array{
     *   user: User,
     *   attendance_minutes: int,
     *   time_entry_minutes: int,
     *   target_minutes: int,
     *   workdays: int,
     *   variance: int
     * }>
     */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId, ReportFilters $filters): array {
        // Mandantengrenze: User hat KEINEN globalen OrganizationScope — ohne expliziten
        // Org-Filter erschienen im Team-Scope User aller Orgs als Zeilen (Tenant-Leak, Bauturbo A17).
        $usersQuery = User::query()
            ->where('organization_id', Auth::user()?->organization_id)
            ->orderBy('name');
        if ($scope === 'mine') {
            $usersQuery->where('id', $userId);
        }
        $filters->applyUserAndTeam($usersQuery, 'id');
        /** @var Collection<int, User> $users */
        $users = $usersQuery->get(['id', 'name']);

        if ($users->isEmpty()) {
            return [];
        }
        $userIds = $users->pluck('id')->map(static fn($v): int => (int) $v)->all();

        /** @var array<int, int> $attMinByUser */
        $attMinByUser = Attendance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [AttendanceStatus::Cancelled->value, AttendanceStatus::Open->value])
            ->selectRaw('user_id, COALESCE(SUM(duration_minutes), 0) as m')
            ->groupBy('user_id')
            ->pluck('m', 'user_id')
            ->map(static fn($v): int => (int) $v)
            ->all();

        /** @var array<int, int> $teMinByUser */
        $teMinByUser = TimeEntry::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('user_id, COALESCE(SUM(minutes), 0) as m')
            ->groupBy('user_id')
            ->pluck('m', 'user_id')
            ->map(static fn($v): int => (int) $v)
            ->all();

        /** @var Collection<int, WorkSchedule> $schedules */
        $schedules = WorkSchedule::query()
            ->whereIn('user_id', $userIds)
            ->where('valid_from', '<=', $to->toDateString())
            ->where(function ($q) use ($from): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $from->toDateString());
            })
            ->orderBy('user_id')
            ->orderBy('valid_from')
            ->get();

        /** @var array<int, array<int, WorkSchedule>> $schedByUser */
        $schedByUser = [];
        foreach ($schedules as $s) {
            $schedByUser[(int) $s->user_id][] = $s;
        }

        $rows = [];
        foreach ($users as $u) {
            $uid = (int) $u->id;
            $userSchedules = array_values($schedByUser[$uid] ?? []);
            [$workdays, $targetMin] = $this->computeTarget($from, $to, $userSchedules);
            $att = $attMinByUser[$uid] ?? 0;
            $te = $teMinByUser[$uid] ?? 0;
            $rows[] = [
                'user' => $u,
                'attendance_minutes' => $att,
                'time_entry_minutes' => $te,
                'target_minutes' => $targetMin,
                'workdays' => $workdays,
                'variance' => $att - $targetMin,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<WorkSchedule>  $schedules
     * @return array{0:int,1:int} [workdays, targetMinutes]
     */
    private function computeTarget(CarbonImmutable $from, CarbonImmutable $to, array $schedules): array {
        if ($schedules === []) {
            return [0, 0];
        }

        $workdays = 0;
        $target = 0;
        $period = CarbonPeriod::create($from->toDateString(), $to->toDateString());
        foreach ($period as $day) {
            $sched = $this->scheduleFor($day, $schedules);
            if ($sched === null) {
                continue;
            }
            $iso = (int) $day->dayOfWeekIso;
            $dayTarget = $sched->targetMinutesForWeekday($iso);
            if ($dayTarget <= 0 && ! $sched->appliesOnWeekday($iso)) {
                continue;
            }
            $workdays++;
            $target += $dayTarget;
        }

        return [$workdays, $target];
    }

    /**
     * @param  list<WorkSchedule>  $schedules
     */
    private function scheduleFor(CarbonInterface $day, array $schedules): ?WorkSchedule {
        $match = null;
        foreach ($schedules as $s) {
            if ($s->valid_from->lte($day) && ($s->valid_to === null || $s->valid_to->gte($day))) {
                if ($match === null || $s->valid_from->gt($match->valid_from)) {
                    $match = $s;
                }
            }
        }

        return $match;
    }

    /**
     * @param  array<int, array{user: User, attendance_minutes:int, time_entry_minutes:int, target_minutes:int, workdays:int, variance:int}>  $rows
     * @return array{attendance:int, time_entry:int, target:int, variance:int}
     */
    private function totals(array $rows): array {
        $att = 0;
        $te = 0;
        $tg = 0;
        foreach ($rows as $r) {
            $att += $r['attendance_minutes'];
            $te += $r['time_entry_minutes'];
            $tg += $r['target_minutes'];
        }

        return [
            'attendance' => $att,
            'time_entry' => $te,
            'target' => $tg,
            'variance' => $att - $tg,
        ];
    }

    /**
     * @param  array<int, array{user: User, attendance_minutes:int, time_entry_minutes:int, target_minutes:int, workdays:int, variance:int}>  $rows
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(array $rows, string $from, string $to, array $exportFilters, Request $request): Response {
        $filename = sprintf('anwesenheit_%s_%s.csv', $from, $to);
        $out = [];
        $out[] = ['Mitarbeiter', 'Arbeitstage', 'Soll (min)', 'Anwesend (min)', 'Gebucht (min)', 'Saldo (min)'];
        foreach ($rows as $r) {
            $out[] = [$r['user']->name, $r['workdays'], $r['target_minutes'], $r['attendance_minutes'], $r['time_entry_minutes'], $r['variance']];
        }
        $totals = $this->totals($rows);
        $out[] = ['GESAMT', '', $totals['target'], $totals['attendance'], $totals['time_entry'], $totals['variance']];

        return $this->csvWithMetadata($out, $filename, 'attendance', $exportFilters, $request);
    }

    /**
     * @param  array<int, array{user: User, attendance_minutes:int, time_entry_minutes:int, target_minutes:int, workdays:int, variance:int}>  $rows
     * @param  list<array{label: string, cells: list<array{value: int}>}>  $heatmapRows
     * @param  list<string>  $weekdayLabels
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $rows, string $from, string $to, string $scope, array $heatmapRows, array $weekdayLabels, array $exportFilters, Request $request): SymfonyResponse {
        $filename = sprintf('anwesenheit_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.attendance', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'chart' => [
                'type' => 'heatmap',
                'title' => __('Anwesenheit je Mitarbeiter und Wochentag'),
                'unit' => 'h',
                'xLabel' => __('Mitarbeiter'),
                'rows' => $heatmapRows,
                'colLabels' => $weekdayLabels,
                'format' => fn(float $minutes): string => intdiv((int) $minutes, 60) . ':' . str_pad((string) ((int) $minutes % 60), 2, '0', STR_PAD_LEFT),
            ],
        ], $filename, request: $request, reportCode: 'attendance', filters: $exportFilters);
    }
}
