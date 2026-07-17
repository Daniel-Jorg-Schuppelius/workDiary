<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeekByUserReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, WritesReportCsv};
use App\Models\{TimeEntry, User};
use App\Support\XlsxExport;
use Carbon\{Carbon, CarbonImmutable};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Wochenreport: Pro Nutzer eine Zeile, 7 Spalten Mo-So mit Tagesminuten +
 * Wochensumme. "Mine" = nur eigener; "Team" = alle (admin only).
 *
 * Pattern angelehnt an Kimai's WeekByUserController (AGPL-3.0) — eigene
 * Implementierung, kein Code-Reuse.
 */
class WeekByUserReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use WritesReportCsv;

    /** Maximalanzahl gleichzeitig gerenderter Wochen-Tabs. */
    private const MAX_WEEKS = 12;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        // Aus dem globalen Header-Zeitraum alle überlappenden ISO-Wochen sammeln
        // und als Tab-Liste an die View liefern – Pattern analog zu WeekController.
        $range = $this->globalDateRange();
        $weekMeta = $this->collectWeekMeta($range['from'], $range['to']);
        $totalWeeks = count($weekMeta);
        $weeksTruncated = $totalWeeks > self::MAX_WEEKS;
        if ($weeksTruncated) {
            $weekMeta = array_slice($weekMeta, 0, self::MAX_WEEKS, true);
        }

        $requestedKey = $request->string('week')->toString();
        $activeKey = isset($weekMeta[$requestedKey]) ? $requestedKey : (string) array_key_first($weekMeta);
        $active = $weekMeta[$activeKey] ?? null;
        if ($active === null) {
            // Leerer Range → Default auf aktuelle Woche.
            $now = Carbon::now();
            $active = [
                'key' => sprintf('%04d-W%02d', $now->isoWeekYear, $now->isoWeek),
                'year' => (int) $now->isoWeekYear,
                'week' => (int) $now->isoWeek,
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
                'shortLabel' => $now->copy()->startOfWeek()->format('d.m.') . '–' . $now->copy()->endOfWeek()->format('d.m.'),
            ];
            $activeKey = $active['key'];
        }

        $year = $active['year'];
        $week = $active['week'];
        $start = $active['start']->copy();
        $end = $active['end']->copy();

        $query = TimeEntry::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->select('user_id', 'date', 'minutes', 'rate');
        if ($scope === 'mine') {
            $query->where('user_id', $userId);
        }
        $entries = $query->get();

        // Aggregate user_id -> [day_index 0..6 => minutes], plus totals.
        /** @var array<int, array{days: array<int, int>, total: int, rate: float}> $byUser */
        $byUser = [];
        foreach ($entries as $e) {
            $uid = (int) $e->user_id;
            $dayDate = Carbon::parse((string) $e->date);
            $idx = $dayDate->dayOfWeekIso - 1; // 0=Mo .. 6=So
            if (! isset($byUser[$uid])) {
                $byUser[$uid] = ['days' => array_fill(0, 7, 0), 'total' => 0, 'rate' => 0.0];
            }
            $byUser[$uid]['days'][$idx] += (int) $e->minutes;
            $byUser[$uid]['total'] += (int) $e->minutes;
            $byUser[$uid]['rate'] += (float) $e->rate;
        }

        // User-Modelle für Anzeigenamen.
        $users = User::query()->whereIn('id', array_keys($byUser))->get()->keyBy('id');

        // Sortiere Zeilen nach Name.
        uksort($byUser, function ($a, $b) use ($users): int {
            $userA = $users->get($a);
            $userB = $users->get($b);
            $na = $userA instanceof User ? $userA->name : '~';
            $nb = $userB instanceof User ? $userB->name : '~';

            return strnatcasecmp($na, $nb);
        });

        // Tagesbeschriftungen
        $locale = app()->getLocale();
        $dayLabels = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i);
            $d->locale($locale);
            $dayLabels[$i] = $d->isoFormat('dd DD.MM.');
        }
        $dayTotals = array_fill(0, 7, 0);
        $weekTotal = 0;
        $weekRate = 0.0;
        foreach ($byUser as $row) {
            foreach ($row['days'] as $i => $m) {
                $dayTotals[$i] += $m;
            }
            $weekTotal += $row['total'];
            $weekRate += $row['rate'];
        }

        $start->locale($locale);
        $weekLabel = sprintf('KW %02d / %d', $week, $year);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($byUser, $users, $dayLabels, $dayTotals, $weekTotal, $weekRate, $year, $week, $scope, $request);
        }
        if ($request->query('export') === 'xlsx') {
            return $this->exportXlsx($byUser, $users, $dayLabels, $dayTotals, $weekTotal, $weekRate, $year, $week);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($byUser, $users, $dayLabels, $dayTotals, $weekTotal, $weekRate, $weekLabel, $year, $week, $scope, $request);
        }

        return view('reports.week-by-user', [
            'year' => $year,
            'week' => $week,
            'weekLabel' => $weekLabel,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'byUser' => $byUser,
            'users' => $users,
            'dayLabels' => $dayLabels,
            'dayTotals' => $dayTotals,
            'weekTotal' => $weekTotal,
            'weekRate' => $weekRate,
            'weekTabs' => array_values($weekMeta),
            'activeKey' => $activeKey,
            'totalWeeks' => $totalWeeks,
            'weeksTruncated' => $weeksTruncated,
        ]);
    }

    /**
     * @param  array<int, array{days: array<int, int>, total: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @param  array<int, int>  $dayTotals
     * @return list<list<int|float|string|null>>
     */
    private function buildRows(array $byUser, $users, array $dayTotals, int $weekTotal, float $weekRate): array {
        $rows = [];
        foreach ($byUser as $uid => $row) {
            $userModel = $users->get($uid);
            $name = $userModel instanceof User ? $userModel->name : '#' . $uid;
            $cols = [(string) $name];
            foreach ($row['days'] as $m) {
                $cols[] = (int) $m;
            }
            $cols[] = (int) $row['total'];
            $cols[] = (float) $row['rate'];
            $rows[] = $cols;
        }
        $totalRow = ['Gesamt'];
        foreach ($dayTotals as $m) {
            $totalRow[] = (int) $m;
        }
        $totalRow[] = (int) $weekTotal;
        $totalRow[] = (float) $weekRate;
        $rows[] = $totalRow;

        return $rows;
    }

    /**
     * @param  array<int, array{days: array<int, int>, total: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @param  array<int, string>  $dayLabels
     * @param  array<int, int>  $dayTotals
     */
    private function exportCsv(array $byUser, $users, array $dayLabels, array $dayTotals, int $weekTotal, float $weekRate, int $year, int $week, string $scope, Request $request): Response {
        $filename = sprintf('woche_%04d-W%02d.csv', $year, $week);
        $rows = [array_merge(['Mitarbeiter'], $dayLabels, ['Wochensumme', 'Erloes'])];
        foreach ($this->buildRows($byUser, $users, $dayTotals, $weekTotal, $weekRate) as $row) {
            $rows[] = array_map(static fn($v) => is_float($v) ? NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) : $v, $row);
        }

        return $this->csvWithMetadata($rows, $filename, 'week-by-user', ['year' => $year, 'week' => $week, 'scope' => $scope], $request);
    }

    /**
     * @param  array<int, array{days: array<int, int>, total: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @param  array<int, string>  $dayLabels
     * @param  array<int, int>  $dayTotals
     */
    private function exportXlsx(array $byUser, $users, array $dayLabels, array $dayTotals, int $weekTotal, float $weekRate, int $year, int $week): SymfonyResponse {
        $filename = sprintf('woche_%04d-W%02d.xlsx', $year, $week);
        $headers = array_merge(['Mitarbeiter'], array_values($dayLabels), ['Wochensumme', 'Erloes']);

        return XlsxExport::streamFromArray($filename, $headers, $this->buildRows($byUser, $users, $dayTotals, $weekTotal, $weekRate));
    }

    /**
     * @param  array<int, array{days: array<int, int>, total: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @param  array<int, string>  $dayLabels
     * @param  array<int, int>  $dayTotals
     */
    private function exportPdf(array $byUser, $users, array $dayLabels, array $dayTotals, int $weekTotal, float $weekRate, string $weekLabel, int $year, int $week, string $scope, Request $request): SymfonyResponse {
        $filename = sprintf('woche_%04d-W%02d.pdf', $year, $week);
        return $this->pdfDownload('reports.pdf.week-by-user', [
            'byUser' => $byUser,
            'users' => $users,
            'dayLabels' => $dayLabels,
            'dayTotals' => $dayTotals,
            'weekTotal' => $weekTotal,
            'weekRate' => $weekRate,
            'weekLabel' => $weekLabel,
        ], $filename, 'landscape', $request, 'week-by-user', ['year' => $year, 'week' => $week, 'scope' => $scope]);
    }

    /**
     * Liefert pro überlappender ISO-Woche im Range Metadaten für den Tab-Strip.
     *
     * @return array<string, array{key: string, year: int, week: int, start: Carbon, end: Carbon, shortLabel: string}>
     */
    private function collectWeekMeta(CarbonImmutable $from, CarbonImmutable $to): array {
        $cursor = $from->startOfWeek()->startOfDay();
        $end = $to->endOfDay();
        if ($end->lt($cursor)) {
            $cursor = CarbonImmutable::today()->startOfWeek()->startOfDay();
            $end = $cursor->endOfWeek();
        }

        $meta = [];
        for ($i = 0; $i < 260 && $cursor->lte($end); $i++) {
            $weekStart = Carbon::parse($cursor->toDateString())->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();
            $key = sprintf('%04d-W%02d', $cursor->isoWeekYear, $cursor->isoWeek);
            $meta[$key] = [
                'key' => $key,
                'year' => (int) $cursor->isoWeekYear,
                'week' => (int) $cursor->isoWeek,
                'start' => $weekStart,
                'end' => $weekEnd,
                'shortLabel' => $weekStart->format('d.m.') . '–' . $weekEnd->format('d.m.'),
            ];
            $cursor = $cursor->addWeek();
        }

        return $meta;
    }
}
