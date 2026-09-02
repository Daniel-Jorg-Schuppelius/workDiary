<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MyYearReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\ResolvesStandardReportFilters;
use App\Models\TimeEntry;
use App\Support\Query\DateRange;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Mein Jahr"-Report: Heatmap-Übersicht der eigenen Arbeitszeit als
 * Monat × Tag-Matrix mit Monats-/Tages-/Jahressummen.
 *
 * Pattern angelehnt an Kimai's UserYearController (AGPL-3.0) — eigene
 * Implementierung, kein Code-Reuse.
 */
class MyYearReportController extends Controller {
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;

    public function index(Request $request): View {
        $userId = (int) Auth::id();
        $year = (int) $this->resolveRange($request)[0]->year;
        $year = max(2000, min(2100, $year));

        $kind = (string) $request->input('kind', 'all');
        $allowedKinds = array_merge(['all'], TimeEntryKind::values());
        if (! in_array($kind, $allowedKinds, true)) {
            $kind = 'all';
        }

        $start = Carbon::create($year, 1, 1, 0, 0, 0) ?: Carbon::now()->startOfYear();
        $end = $start->copy()->endOfYear();

        $filters = $this->standardFilters($request, ['customer', 'project'], $start->toImmutable(), $end->toImmutable());

        $query = TimeEntry::query()
            ->where('user_id', $userId)
            ->whereBetween('date', DateRange::days($start, $end))
            ->select('date', 'minutes', 'kind');
        if ($kind !== 'all') {
            $query->where('kind', $kind);
        }
        $filters->applyToTimeEntryQuery($query);

        // Aggregation in PHP (DB-agnostisch). Datensatz ist bounded (ein User,
        // ein Jahr), Performance unkritisch.
        /** @var array<int, array<int, int>> $matrix [month][day] => minutes */
        $matrix = [];
        for ($m = 1; $m <= 12; $m++) {
            $matrix[$m] = array_fill(1, 31, 0);
        }
        $monthTotals = array_fill(1, 12, 0);
        $dayTotals = array_fill(1, 31, 0);
        $yearTotal = 0;
        $maxCell = 0;

        foreach ($query->get() as $entry) {
            $date = Carbon::parse((string) $entry->date);
            $m = (int) $date->month;
            $d = (int) $date->day;
            $min = (int) $entry->minutes;
            $matrix[$m][$d] += $min;
            $monthTotals[$m] += $min;
            $dayTotals[$d] += $min;
            $yearTotal += $min;
            if ($matrix[$m][$d] > $maxCell) {
                $maxCell = $matrix[$m][$d];
            }
        }

        $monthNames = [];
        $daysInMonth = [];
        $monthUrls = [];
        $monthlySeries = [];
        $locale = app()->getLocale();
        for ($m = 1; $m <= 12; $m++) {
            $first = Carbon::create($year, $m, 1, 0, 0, 0) ?: Carbon::now();
            $first->locale($locale);
            $monthNames[$m] = $first->isoFormat('MMMM');
            $daysInMonth[$m] = (int) $first->daysInMonth;
            // Drilldown: Monat in „Mein Monat" öffnen — Zeitraum + Filterkontext erben.
            $monthUrls[$m] = route('reports.my-month', array_merge($filters->toQueryParams(), [
                'from' => $first->toDateString(),
                'to' => $first->copy()->endOfMonth()->toDateString(),
                'kind' => $kind,
            ]));
            $monthlySeries[] = [
                'x' => $first->isoFormat('MMM'),
                'y' => round($monthTotals[$m] / 60, 1),
                'url' => $monthUrls[$m],
            ];
        }
        if ($yearTotal === 0) {
            $monthlySeries = []; // Leerzustand statt Null-Achse (§Diagramm-UX).
        }

        return view('reports.my-year', [
            'year' => $year,
            'kind' => $kind,
            'kinds' => $allowedKinds,
            'matrix' => $matrix,
            'monthTotals' => $monthTotals,
            'dayTotals' => $dayTotals,
            'yearTotal' => $yearTotal,
            'monthNames' => $monthNames,
            'daysInMonth' => $daysInMonth,
            'maxCell' => $maxCell,
            'monthUrls' => $monthUrls,
            'monthlySeries' => $monthlySeries,
            'standardFilters' => $filters,
            'filterFields' => ['customer', 'project'],
            ...$this->standardFilterOptions(['customer', 'project'], $filters),
        ]);
    }
}
