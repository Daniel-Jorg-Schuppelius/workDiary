<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
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

    public function index(Request $request): View {
        $userId = (int) Auth::id();
        $year = (int) $this->globalDateRange()['from']->year;
        $year = max(2000, min(2100, $year));

        $kind = (string) $request->input('kind', 'all');
        $allowedKinds = array_merge(['all'], TimeEntry::KINDS);
        if (! in_array($kind, $allowedKinds, true)) {
            $kind = 'all';
        }

        $start = Carbon::create($year, 1, 1, 0, 0, 0) ?: Carbon::now()->startOfYear();
        $end = $start->copy()->endOfYear();

        $query = TimeEntry::query()
            ->where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->select('date', 'minutes', 'kind');
        if ($kind !== 'all') {
            $query->where('kind', $kind);
        }

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
        $locale = app()->getLocale();
        for ($m = 1; $m <= 12; $m++) {
            $first = Carbon::create($year, $m, 1, 0, 0, 0) ?: Carbon::now();
            $first->locale($locale);
            $monthNames[$m] = $first->isoFormat('MMMM');
            $daysInMonth[$m] = (int) $first->daysInMonth;
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
        ]);
    }
}
