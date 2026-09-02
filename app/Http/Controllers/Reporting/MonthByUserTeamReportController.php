<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthByUserTeamReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{TimeEntry, User};
use App\Support\Query\DateRange;
use App\Support\XlsxExport;
use Carbon\Carbon;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Team-Monatsreport: Pivot User × Monat (1..12) eines Jahres mit
 * Minutensummen + Erlös. Admin/Buchhaltung only.
 *
 * Pattern angelehnt an Kimai's MonthByUserController (AGPL-3.0) — eigene
 * Implementierung, kein Code-Reuse.
 */
class MonthByUserTeamReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        /** @var User|null $auth */
        $auth = Auth::user();
        if (! $auth instanceof User || ! $auth->isAdmin()) {
            Gate::authorize('viewAny', User::class);
        }

        [$rangeFrom] = $this->resolveRange($request);
        $year = max(2000, min(2100, (int) $rangeFrom->year));

        $start = Carbon::create($year, 1, 1, 0, 0, 0) ?: Carbon::now()->startOfYear();
        $end = $start->copy()->endOfYear();

        $filters = $this->standardFilters(
            $request,
            ['user', 'team'],
            $start->toImmutable(),
            $end->toImmutable(),
        );

        // Aggregation in SQL statt Hydration des Jahresbestands (Vollscan
        // 2026-08-23, A9); Monatsausdruck treiberabhängig wie im
        // TimeAccountPostingService (strftime nur in SQLite).
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'MONTH(date)' : "CAST(strftime('%m', date) AS INTEGER)";
        $entriesQuery = TimeEntry::query()
            ->whereBetween('date', DateRange::days($start, $end))
            ->selectRaw("user_id, {$monthExpr} AS month_idx, SUM(minutes) AS minutes_sum, SUM(rate) AS rate_sum")
            ->groupBy('user_id')
            ->groupByRaw($monthExpr);
        $aggregates = $filters->applyToTimeEntryQuery($entriesQuery)->toBase()->get();

        /** @var array<int, array{months: array<int, int>, total: int, rate: float}> $byUser */
        $byUser = [];
        foreach ($aggregates as $row) {
            $uid = (int) $row->user_id;
            $monthIdx = (int) $row->month_idx;
            if (! isset($byUser[$uid])) {
                $byUser[$uid] = ['months' => array_fill(1, 12, 0), 'total' => 0, 'rate' => 0.0];
            }
            $byUser[$uid]['months'][$monthIdx] += (int) $row->minutes_sum;
            $byUser[$uid]['total'] += (int) $row->minutes_sum;
            $byUser[$uid]['rate'] += (float) $row->rate_sum;
        }

        /** @var Collection<int, User> $users */
        $users = User::query()->whereIn('id', array_keys($byUser))->get()->keyBy('id');

        uksort($byUser, function ($a, $b) use ($users): int {
            $userA = $users->get($a);
            $userB = $users->get($b);
            $na = $userA instanceof User ? $userA->name : '~';
            $nb = $userB instanceof User ? $userB->name : '~';

            return strnatcasecmp($na, $nb);
        });

        $locale = app()->getLocale();
        $monthLabels = [];
        for ($i = 1; $i <= 12; $i++) {
            $d = Carbon::create($year, $i, 1);
            if ($d === null) {
                continue;
            }
            $d->locale($locale);
            $monthLabels[$i] = $d->isoFormat('MMM');
        }

        $monthTotals = array_fill(1, 12, 0);
        $yearTotal = 0;
        $yearRate = 0.0;
        foreach ($byUser as $row) {
            foreach ($row['months'] as $i => $m) {
                $monthTotals[$i] += $m;
            }
            $yearTotal += $row['total'];
            $yearRate += $row['rate'];
        }

        $exportFilters = array_merge(['year' => $year], $filters->toAuditArray());
        $heatmapRows = $this->heatmapRows($byUser, $users);
        $userHoursSeries = $this->userHoursSeries($byUser, $users);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($byUser, $users, $monthLabels, $monthTotals, $yearTotal, $yearRate, $year, $exportFilters, $request);
        }
        if ($request->query('export') === 'xlsx') {
            return $this->exportXlsx($byUser, $users, $monthLabels, $monthTotals, $yearTotal, $yearRate, $year);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($byUser, $users, $monthLabels, $monthTotals, $yearTotal, $yearRate, $year, $heatmapRows, $exportFilters, $request);
        }

        return view('reports.month-by-user-team', [
            'year' => $year,
            'byUser' => $byUser,
            'users' => $users,
            'monthLabels' => $monthLabels,
            'monthTotals' => $monthTotals,
            'yearTotal' => $yearTotal,
            'yearRate' => $yearRate,
            'standardFilters' => $filters,
            'filterFields' => ['user', 'team'],
            'userHoursSeries' => $userHoursSeries,
            'hoursMedian' => $userHoursSeries === [] ? null : NumberHelper::median(array_column($userHoursSeries, 'y')),
            'heatmapRows' => $heatmapRows,
            ...$this->standardFilterOptions(['user', 'team'], $filters),
        ]);
    }

    /**
     * Stunden je Mitarbeiter (Jahressumme). Der Report führt keine Soll-Werte
     * je User — Vergleichslinie ist daher der Median über die User-Summen.
     *
     * @param  array<int, array{months: array<int, int>, total: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @return list<array{x: string, y: float}>
     */
    private function userHoursSeries(array $byUser, Collection $users): array {
        $series = [];
        foreach ($byUser as $uid => $row) {
            $userModel = $users->get($uid);
            $series[] = [
                'x' => $userModel instanceof User ? $userModel->name : '#' . $uid,
                'y' => round($row['total'] / 60, 1),
            ];
        }

        return $series;
    }

    /**
     * Heatmap-Zeilen User × Monat (Minuten; Anzeige h:mm via format-Prop).
     *
     * @param  array<int, array{months: array<int, int>, total: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @return list<array{label: string, cells: list<array{value: int}>}>
     */
    private function heatmapRows(array $byUser, Collection $users): array {
        $rows = [];
        foreach ($byUser as $uid => $row) {
            $userModel = $users->get($uid);
            $rows[] = [
                'label' => $userModel instanceof User ? $userModel->name : '#' . $uid,
                'cells' => array_map(fn(int $minutes): array => ['value' => $minutes], array_values($row['months'])),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{months: array<int, int>, total: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @param  array<int, int>  $monthTotals
     * @return list<list<int|float|string|null>>
     */
    private function buildRows(array $byUser, Collection $users, array $monthTotals, int $yearTotal, float $yearRate): array {
        $rows = [];
        foreach ($byUser as $uid => $row) {
            $userModel = $users->get($uid);
            $name = $userModel instanceof User ? $userModel->name : '#' . $uid;
            $cols = [(string) $name];
            foreach ($row['months'] as $m) {
                $cols[] = (int) $m;
            }
            $cols[] = (int) $row['total'];
            $cols[] = (float) $row['rate'];
            $rows[] = $cols;
        }
        $totalRow = ['Gesamt'];
        foreach ($monthTotals as $m) {
            $totalRow[] = (int) $m;
        }
        $totalRow[] = (int) $yearTotal;
        $totalRow[] = (float) $yearRate;
        $rows[] = $totalRow;

        return $rows;
    }

    /**
     * @param  array<int, array{months: array<int, int>, total: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @param  array<int, string>  $monthLabels
     * @param  array<int, int>  $monthTotals
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(array $byUser, Collection $users, array $monthLabels, array $monthTotals, int $yearTotal, float $yearRate, int $year, array $exportFilters, Request $request): Response {
        $filename = sprintf('monat-team-%04d.csv', $year);
        $rows = [array_merge(['Mitarbeiter'], array_values($monthLabels), ['Jahressumme', 'Erloes'])];
        foreach ($this->buildRows($byUser, $users, $monthTotals, $yearTotal, $yearRate) as $row) {
            $rows[] = array_map(static fn($v) => is_float($v) ? NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) : $v, $row);
        }

        return $this->csvWithMetadata($rows, $filename, 'month-by-user-team', $exportFilters, $request);
    }

    /**
     * @param  array<int, array{months: array<int, int>, total: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @param  array<int, string>  $monthLabels
     * @param  array<int, int>  $monthTotals
     */
    private function exportXlsx(array $byUser, Collection $users, array $monthLabels, array $monthTotals, int $yearTotal, float $yearRate, int $year): SymfonyResponse {
        $filename = sprintf('monat-team-%04d.xlsx', $year);
        $headers = array_merge(['Mitarbeiter'], array_values($monthLabels), ['Jahressumme', 'Erloes']);

        return XlsxExport::streamFromArray($filename, $headers, $this->buildRows($byUser, $users, $monthTotals, $yearTotal, $yearRate));
    }

    /**
     * @param  array<int, array{months: array<int, int>, total: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @param  array<int, string>  $monthLabels
     * @param  array<int, int>  $monthTotals
     * @param  list<array{label: string, cells: list<array{value: int}>}>  $heatmapRows
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $byUser, Collection $users, array $monthLabels, array $monthTotals, int $yearTotal, float $yearRate, int $year, array $heatmapRows, array $exportFilters, Request $request): SymfonyResponse {
        $filename = sprintf('monat-team-%04d.pdf', $year);
        return $this->pdfDownload('reports.pdf.month-by-user-team', [
            'byUser' => $byUser,
            'users' => $users,
            'monthLabels' => $monthLabels,
            'monthTotals' => $monthTotals,
            'yearTotal' => $yearTotal,
            'yearRate' => $yearRate,
            'year' => $year,
            'chart' => [
                'type' => 'heatmap',
                'title' => __('Stunden je Mitarbeiter und Monat'),
                'unit' => 'h',
                'xLabel' => __('Mitarbeiter'),
                'rows' => $heatmapRows,
                'colLabels' => array_values($monthLabels),
                'format' => fn(float $minutes): string => intdiv((int) $minutes, 60) . ':' . str_pad((string) ((int) $minutes % 60), 2, '0', STR_PAD_LEFT),
            ],
        ], $filename, 'landscape', $request, 'month-by-user-team', $exportFilters);
    }
}
