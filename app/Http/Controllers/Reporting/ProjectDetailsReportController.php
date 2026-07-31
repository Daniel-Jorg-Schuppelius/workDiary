<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectDetailsReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{DiaryEntry, EntryType, Project, TimeEntry, User};
use App\Services\Reporting\ReportFilters;
use App\Support\{Sqid, XlsxExport};
use Carbon\{Carbon, CarbonImmutable};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Projekt-Details-Report: Für ein gewähltes Projekt zeigt Monatswerte
 * (12 Monate eines Jahres) sowie Aufschlüsselung pro Mitarbeiter.
 *
 * Pattern angelehnt an Kimai's ProjectDetailsController (AGPL-3.0) — eigene
 * Implementierung, kein Code-Reuse.
 */
class ProjectDetailsReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        $isAdmin = $this->viewerIsAdmin();

        [$rangeFrom, $rangeTo] = $this->resolveRange($request);
        $year = max(2000, min(2100, (int) $rangeFrom->year));

        // Mitarbeiter-Filter nur für Admins — Nicht-Admins sehen ohnehin nur eigene Zeiten.
        $filterFields = $isAdmin ? ['customer', 'project', 'user'] : ['customer', 'project'];
        $filters = $this->standardFilters($request, $filterFields, $rangeFrom, $rangeTo);

        // Legacy-Parameter project_id (alte Bookmarks) ins Standard-Set übernehmen.
        $projectId = $filters->projectId ?? Sqid::decodeOrNumeric(Project::class, $request->query('project_id'));
        $projectId ??= 0;

        $projects = $this->loadAccessibleProjects($isAdmin, $userId, $filters->customerId);

        $project = $projectId > 0 ? $projects->firstWhere('id', $projectId) : null;
        if (! $project instanceof Project) {
            $project = $projects->first();
            $projectId = $project instanceof Project ? (int) $project->id : 0;
        }

        // Effektives Projekt ins Filterset spiegeln, damit Partial-Auswahl,
        // Export-Links und Audit denselben Stand sehen.
        $filters = new ReportFilters(
            from: $rangeFrom,
            to: $rangeTo,
            customerId: $filters->customerId,
            projectId: $projectId > 0 ? $projectId : null,
            userId: $filters->userId,
        );

        $aggregate = $this->aggregateYear($project, $year, $isAdmin, $userId, $filters->userId);
        $monthMatrix = $aggregate['monthMatrix'];
        $byUser = $aggregate['byUser'];
        $yearMinutes = $aggregate['yearMinutes'];
        $yearRate = $aggregate['yearRate'];
        $entries = $aggregate['entries'];

        $users = User::query()->whereIn('id', array_keys($byUser))->get()->keyBy('id');
        $byUser = $this->sortByUserByName($byUser, $users);

        $monthLabels = $this->buildMonthLabels($year);
        $planIst = $this->planIstMonthlySeries($project, $year, $monthMatrix, $monthLabels, $yearMinutes, $filters->userId);
        $exportFilters = array_merge(['year' => $year], $filters->toAuditArray());

        if ($request->query('export') === 'csv' && $project instanceof Project) {
            return $this->exportCsv($project, $year, $monthMatrix, $monthLabels, $byUser, $users, $yearMinutes, $yearRate, $exportFilters, $request);
        }
        if ($request->query('export') === 'xlsx' && $project instanceof Project) {
            return $this->exportXlsx($project, $year, $monthMatrix, $monthLabels, $byUser, $users, $yearMinutes, $yearRate);
        }
        if ($request->query('export') === 'pdf' && $project instanceof Project) {
            return $this->exportPdf($project, $year, $monthMatrix, $monthLabels, $byUser, $users, $yearMinutes, $yearRate, $planIst['series'], $exportFilters, $request);
        }

        $typeMonthly = $this->typeMonthlySeries($entries, $monthLabels, $yearMinutes);

        return view('reports.project-details', [
            'year' => $year,
            'projectId' => $projectId,
            'project' => $project,
            'projects' => $projects,
            'monthMatrix' => $monthMatrix,
            'monthLabels' => $monthLabels,
            'byUser' => $byUser,
            'users' => $users,
            'yearMinutes' => $yearMinutes,
            'yearRate' => $yearRate,
            'standardFilters' => $filters,
            'filterFields' => $filterFields,
            'timelineSeries' => $this->timelineSeries($entries, $rangeFrom, $rangeTo, $year),
            'planIstSeries' => $planIst['series'],
            'planIstMedian' => $planIst['median'],
            'typeMonthlySeries' => $typeMonthly['series'],
            'typeBands' => $typeMonthly['bands'],
            ...$this->standardFilterOptions($filterFields, $filters),
        ]);
    }

    /**
     * @return Collection<int, Project>
     */
    private function loadAccessibleProjects(bool $isAdmin, int $userId, ?int $customerId = null): Collection {
        $projectsQuery = Project::with('customer')
            ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
            ->orderBy('name');
        if (! $isAdmin) {
            $accessibleIds = TimeEntry::query()
                ->where('user_id', $userId)
                ->distinct()
                ->pluck('project_id')
                ->all();
            $projectsQuery->whereIn('id', $accessibleIds);
        }

        /** @var Collection<int, Project> $projects */
        $projects = $projectsQuery->get();

        return $projects;
    }

    /**
     * @return array{monthMatrix: array<int, array{minutes: int, rate: float}>, byUser: array<int, array{minutes: int, rate: float}>, yearMinutes: int, yearRate: float, entries: \Illuminate\Support\Collection<int, TimeEntry>}
     */
    private function aggregateYear(?Project $project, int $year, bool $isAdmin, int $userId, ?int $filterUserId = null): array {
        /** @var array<int, array{minutes: int, rate: float}> $monthMatrix */
        $monthMatrix = array_fill(1, 12, ['minutes' => 0, 'rate' => 0.0]);
        /** @var array<int, array{minutes: int, rate: float}> $byUser */
        $byUser = [];
        $yearMinutes = 0;
        $yearRate = 0.0;

        if (! $project instanceof Project) {
            return ['monthMatrix' => $monthMatrix, 'byUser' => $byUser, 'yearMinutes' => $yearMinutes, 'yearRate' => $yearRate, 'entries' => collect()];
        }

        $start = Carbon::create($year, 1, 1, 0, 0, 0) ?: Carbon::now()->startOfYear();
        $end = $start->copy()->endOfYear();

        $entries = TimeEntry::query()
            ->where('project_id', $project->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($filterUserId !== null, fn($q) => $q->where('user_id', $filterUserId))
            ->select('user_id', 'date', 'minutes', 'rate', 'diary_entry_id')
            ->get();
        if (! $isAdmin) {
            $entries = $entries->where('user_id', $userId);
        }

        foreach ($entries as $e) {
            $month = (int) Carbon::parse((string) $e->date)->month;
            $monthMatrix[$month] = [
                'minutes' => (int) ($monthMatrix[$month]['minutes'] ?? 0) + (int) $e->minutes,
                'rate' => (float) ($monthMatrix[$month]['rate'] ?? 0.0) + ($e->rate?->toFloat() ?? 0.0),
            ];
            $uid = (int) $e->user_id;
            $byUser[$uid] = [
                'minutes' => (int) ($byUser[$uid]['minutes'] ?? 0) + (int) $e->minutes,
                'rate' => (float) ($byUser[$uid]['rate'] ?? 0.0) + ($e->rate?->toFloat() ?? 0.0),
            ];
            $yearMinutes += (int) $e->minutes;
            $yearRate += ($e->rate?->toFloat() ?? 0.0);
        }

        return ['monthMatrix' => $monthMatrix, 'byUser' => $byUser, 'yearMinutes' => $yearMinutes, 'yearRate' => $yearRate, 'entries' => $entries->values()];
    }

    /**
     * Stundenverlauf über den effektiven Zeitraum — Tagesbuckets bei kurzen,
     * Wochenbuckets (ISO-KW) bei langen Zeiträumen; begrenzt auf das
     * Berichtsjahr, aus dem die Monatswerte stammen.
     *
     * @param  \Illuminate\Support\Collection<int, TimeEntry>  $entries
     * @return list<array{x: string, y: float}>
     */
    private function timelineSeries($entries, CarbonImmutable $rangeFrom, CarbonImmutable $rangeTo, int $year): array {
        $yearStart = (CarbonImmutable::create($year, 1, 1) ?: CarbonImmutable::now()->startOfYear())->startOfDay();
        $from = $rangeFrom->max($yearStart)->startOfDay();
        $to = $rangeTo->min($yearStart->endOfYear())->startOfDay();
        if ($to->lessThan($from)) {
            return [];
        }

        /** @var array<string, int> $minutesByDay */
        $minutesByDay = [];
        $total = 0;
        foreach ($entries as $e) {
            $day = Carbon::parse((string) $e->date)->toDateString();
            if ($day < $from->toDateString() || $day > $to->toDateString()) {
                continue;
            }
            $minutesByDay[$day] = ($minutesByDay[$day] ?? 0) + (int) $e->minutes;
            $total += (int) $e->minutes;
        }
        if ($total === 0) {
            return []; // Leerzustand statt Null-Linie (§Diagramm-UX).
        }

        $series = [];
        if ($from->diffInDays($to) <= 62) {
            for ($cursor = $from; $cursor->lte($to); $cursor = $cursor->addDay()) {
                $series[] = [
                    'x' => $cursor->format('d.m.'),
                    'y' => round(($minutesByDay[$cursor->toDateString()] ?? 0) / 60, 1),
                ];
            }

            return $series;
        }

        /** @var array<string, int> $minutesByWeek */
        $minutesByWeek = [];
        for ($cursor = $from; $cursor->lte($to); $cursor = $cursor->addDay()) {
            $week = sprintf('KW %02d', $cursor->isoWeek);
            $minutesByWeek[$week] = ($minutesByWeek[$week] ?? 0) + ($minutesByDay[$cursor->toDateString()] ?? 0);
        }
        foreach ($minutesByWeek as $week => $minutes) {
            $series[] = ['x' => $week, 'y' => round($minutes / 60, 1)];
        }

        return $series;
    }

    /**
     * Ist- und Plan-Stunden je Monat: Ist aus den Zeiteinträgen, Plan aus
     * planned_minutes der im jeweiligen Monat terminierten Aufträge
     * (start_at); ohne Plan-Daten stattdessen Median der Ist-Monatswerte.
     *
     * @param  array<int, array{minutes: int, rate: float}>  $monthMatrix
     * @param  array<int, string>  $monthLabels
     * @return array{series: list<array<string, string|float|null>>, median: ?float}
     */
    private function planIstMonthlySeries(?Project $project, int $year, array $monthMatrix, array $monthLabels, int $yearMinutes, ?int $filterUserId = null): array {
        if (! $project instanceof Project) {
            return ['series' => [], 'median' => null];
        }

        $yearStart = (CarbonImmutable::create($year, 1, 1) ?: CarbonImmutable::now()->startOfYear())->startOfDay();
        /** @var array<int, int> $planByMonth */
        $planByMonth = array_fill(1, 12, 0);
        $planTotal = 0;
        DiaryEntry::query()
            ->where('project_id', $project->id)
            ->whereBetween('start_at', [$yearStart, $yearStart->endOfYear()])
            ->whereNotNull('planned_minutes')
            ->when($filterUserId !== null, fn($q) => $q->where('user_id', $filterUserId))
            ->get(['start_at', 'planned_minutes'])
            ->each(function (DiaryEntry $entry) use (&$planByMonth, &$planTotal): void {
                $month = (int) ($entry->start_at->month ?? 0);
                if ($month >= 1 && $month <= 12) {
                    $planByMonth[$month] += (int) $entry->planned_minutes;
                    $planTotal += (int) $entry->planned_minutes;
                }
            });

        if ($yearMinutes === 0 && $planTotal === 0) {
            return ['series' => [], 'median' => null]; // Leerzustand statt Null-Achse.
        }

        $hasPlan = $planTotal > 0;
        $series = [];
        $istValues = [];
        for ($month = 1; $month <= 12; $month++) {
            $ist = round(((int) ($monthMatrix[$month]['minutes'] ?? 0)) / 60, 1);
            $istValues[] = $ist;
            $row = ['x' => $monthLabels[$month] ?? (string) $month, 'y' => $ist];
            if ($hasPlan) {
                $row['y2'] = round($planByMonth[$month] / 60, 1);
            }
            $series[] = $row;
        }

        return ['series' => $series, 'median' => $hasPlan ? null : $this->median($istValues)];
    }

    /**
     * Stunden nach Auftragstyp je Monat (max. 4 Typen + Rest-Band): Zeiten
     * folgen ihrem verknüpften Auftrag (diary_entry_id → entry_type);
     * unverknüpfte oder typlose Zeiten landen im Rest-Band.
     *
     * @param  \Illuminate\Support\Collection<int, TimeEntry>  $entries
     * @param  array<int, string>  $monthLabels
     * @return array{series: list<array<string, string|float>>, bands: list<array{key: string, label: string}>}
     */
    private function typeMonthlySeries($entries, array $monthLabels, int $yearMinutes): array {
        if ($yearMinutes === 0 || $entries->isEmpty()) {
            return ['series' => [], 'bands' => []];
        }

        $diaryIds = $entries->pluck('diary_entry_id')->filter()->unique()->values()->all();
        /** @var array<int, int|null> $typeByDiary */
        $typeByDiary = $diaryIds === []
            ? []
            : DiaryEntry::query()->whereIn('id', $diaryIds)->pluck('entry_type_id', 'id')
                ->map(static fn($v): ?int => $v !== null ? (int) $v : null)
                ->all();

        /** @var array<int, array<int|string, int>> $byMonthType Monat → Typ-ID|'rest' → Minuten */
        $byMonthType = [];
        /** @var array<int, int> $typeTotals */
        $typeTotals = [];
        foreach ($entries as $e) {
            $month = (int) Carbon::parse((string) $e->date)->month;
            $typeId = $e->diary_entry_id !== null ? ($typeByDiary[(int) $e->diary_entry_id] ?? null) : null;
            $key = $typeId ?? 'rest';
            $byMonthType[$month][$key] = ($byMonthType[$month][$key] ?? 0) + (int) $e->minutes;
            if ($typeId !== null) {
                $typeTotals[$typeId] = ($typeTotals[$typeId] ?? 0) + (int) $e->minutes;
            }
        }

        arsort($typeTotals);
        $topTypeIds = array_slice(array_keys($typeTotals), 0, 4);
        $labels = EntryType::query()->whereIn('id', $topTypeIds)->pluck('label', 'id');

        $bands = [];
        foreach ($topTypeIds as $typeId) {
            $bands[] = ['key' => 'type_' . $typeId, 'label' => (string) ($labels[$typeId] ?? ('#' . $typeId))];
        }
        $needsRest = count($typeTotals) > count($topTypeIds)
            || $entries->contains(fn(TimeEntry $e): bool => $e->diary_entry_id === null || ($typeByDiary[(int) $e->diary_entry_id] ?? null) === null);
        if ($needsRest || $bands === []) {
            $bands[] = ['key' => 'rest', 'label' => __('Rest')];
        }

        $series = [];
        for ($month = 1; $month <= 12; $month++) {
            $row = ['x' => $monthLabels[$month] ?? (string) $month];
            $rest = 0;
            foreach ($byMonthType[$month] ?? [] as $key => $minutes) {
                if ($key !== 'rest' && in_array((int) $key, $topTypeIds, true)) {
                    $row['type_' . $key] = round($minutes / 60, 1);
                } else {
                    $rest += $minutes;
                }
            }
            if ($rest > 0) {
                $row['rest'] = round($rest / 60, 1);
            }
            $series[] = $row;
        }

        return ['series' => $series, 'bands' => $bands];
    }

    /**
     * @param  list<float>  $values
     */
    private function median(array $values): ?float {
        if ($values === []) {
            return null;
        }
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$middle]
            : round(($values[$middle - 1] + $values[$middle]) / 2, 1);
    }

    /**
     * @param  array<int, array{minutes: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @return array<int, array{minutes: int, rate: float}>
     */
    private function sortByUserByName(array $byUser, Collection $users): array {
        uksort($byUser, function ($a, $b) use ($users): int {
            $ua = $users->get($a);
            $ub = $users->get($b);
            $na = $ua instanceof User ? $ua->name : '~';
            $nb = $ub instanceof User ? $ub->name : '~';

            return strnatcasecmp($na, $nb);
        });

        return $byUser;
    }

    /**
     * @return array<int, string>
     */
    private function buildMonthLabels(int $year): array {
        $monthLabels = [];
        $locale = app()->getLocale();
        for ($i = 1; $i <= 12; $i++) {
            $d = Carbon::create($year, $i, 1) ?: Carbon::now();
            $d->locale($locale);
            $monthLabels[$i] = $d->isoFormat('MMM');
        }

        return $monthLabels;
    }

    /**
     * @param  array<int, array{minutes: int, rate: float}>  $monthMatrix
     * @param  array<int, string>  $monthLabels
     * @param  array<int, array{minutes: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(Project $project, int $year, array $monthMatrix, array $monthLabels, array $byUser, $users, int $yearMinutes, float $yearRate, array $exportFilters, Request $request): Response {
        $filename = sprintf('projekt-%d-%d.csv', $project->id, $year);
        $rows = [['Monat', 'Minuten', 'Erloes']];
        foreach ($monthMatrix as $idx => $row) {
            $rows[] = [$monthLabels[$idx] ?? (string) $idx, (int) $row['minutes'], NumberHelper::toGermanFormat((float) $row['rate'], 2, withThousandsSeparator: true)];
        }
        $rows[] = ['Gesamt', $yearMinutes, NumberHelper::toGermanFormat($yearRate, 2, withThousandsSeparator: true)];
        $rows[] = [];
        $rows[] = ['Mitarbeiter', 'Minuten', 'Erloes'];
        foreach ($byUser as $uid => $row) {
            $userModel = $users->get($uid);
            $name = $userModel instanceof User ? $userModel->name : '#' . $uid;
            $rows[] = [(string) $name, (int) $row['minutes'], NumberHelper::toGermanFormat((float) $row['rate'], 2, withThousandsSeparator: true)];
        }

        return $this->csvWithMetadata($rows, $filename, 'project-details', $exportFilters, $request);
    }

    /**
     * @param  array<int, array{minutes: int, rate: float}>  $monthMatrix
     * @param  array<int, string>  $monthLabels
     * @param  array<int, array{minutes: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     */
    private function exportXlsx(Project $project, int $year, array $monthMatrix, array $monthLabels, array $byUser, $users, int $yearMinutes, float $yearRate): SymfonyResponse {
        $filename = sprintf('projekt-%d-%d.xlsx', $project->id, $year);

        // Bauen einer kombinierten Tabelle: erst Monate, dann separator, dann Mitarbeiter.
        $headers = ['Bereich', 'Bezeichnung', 'Minuten', 'Erloes'];
        $rows = [];
        foreach ($monthMatrix as $idx => $row) {
            $rows[] = ['Monat', $monthLabels[$idx] ?? (string) $idx, (int) $row['minutes'], (float) $row['rate']];
        }
        $rows[] = ['Monat', 'Gesamt', (int) $yearMinutes, (float) $yearRate];
        foreach ($byUser as $uid => $row) {
            $userModel = $users->get($uid);
            $name = $userModel instanceof User ? $userModel->name : '#' . $uid;
            $rows[] = ['Mitarbeiter', (string) $name, (int) $row['minutes'], (float) $row['rate']];
        }

        return XlsxExport::streamFromArray($filename, $headers, $rows);
    }

    /**
     * @param  array<int, array{minutes: int, rate: float}>  $monthMatrix
     * @param  array<int, string>  $monthLabels
     * @param  array<int, array{minutes: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @param  list<array<string, string|float|null>>  $planIstSeries
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(Project $project, int $year, array $monthMatrix, array $monthLabels, array $byUser, $users, int $yearMinutes, float $yearRate, array $planIstSeries, array $exportFilters, Request $request): SymfonyResponse {
        $filename = sprintf('projekt-%d-%d.pdf', $project->id, $year);
        return $this->pdfDownload('reports.pdf.project-details', [
            'project' => $project,
            'year' => $year,
            'monthMatrix' => $monthMatrix,
            'monthLabels' => $monthLabels,
            'byUser' => $byUser,
            'users' => $users,
            'yearMinutes' => $yearMinutes,
            'yearRate' => $yearRate,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Ist- und Plan-Stunden je Monat'),
                'unit' => 'h',
                'xLabel' => __('Monat'),
                'yLabel' => __('Ist'),
                'y2Label' => __('Plan'),
                'series' => array_values(array_filter(
                    $planIstSeries,
                    static fn(array $point): bool => ((float) ($point['y'] ?? 0)) > 0 || ((float) ($point['y2'] ?? 0)) > 0,
                )),
            ],
        ], $filename, request: $request, reportCode: 'project-details', filters: $exportFilters);
    }
}
