<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
    use ResolvesGlobalDateRange;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        $isAdmin = Auth::user()?->isAdmin() ?? false;
        $scope = $request->string('scope', 'mine')->toString();
        if ($scope !== 'team' || ! $isAdmin) {
            $scope = 'mine';
        }

        $globalFrom = $this->globalDateRange()['from'];
        $year = (int) $request->input('year', $globalFrom->year);
        $week = (int) $request->input('week', $globalFrom->isoWeek);
        $year = max(2000, min(2100, $year));
        $week = max(1, min(53, $week));

        $start = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $end = $start->copy()->endOfWeek();

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

        // Navigation
        $prev = $start->copy()->subWeek();
        $next = $start->copy()->addWeek();

        $start->locale($locale);
        $weekLabel = sprintf('KW %02d / %d', $week, $year);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($byUser, $users, $dayLabels, $dayTotals, $weekTotal, $weekRate, $year, $week);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($byUser, $users, $dayLabels, $dayTotals, $weekTotal, $weekRate, $weekLabel, $year, $week);
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
            'prevYear' => (int) $prev->isoWeekYear,
            'prevWeek' => (int) $prev->isoWeek,
            'nextYear' => (int) $next->isoWeekYear,
            'nextWeek' => (int) $next->isoWeek,
        ]);
    }

    /**
     * @param array<int, array{days: array<int, int>, total: int, rate: float}> $byUser
     * @param \Illuminate\Database\Eloquent\Collection<int, User> $users
     * @param array<int, string> $dayLabels
     * @param array<int, int> $dayTotals
     */
    private function exportCsv(array $byUser, $users, array $dayLabels, array $dayTotals, int $weekTotal, float $weekRate, int $year, int $week): Response {
        $filename = sprintf('woche_%04d-W%02d.csv', $year, $week);
        $rows = [array_merge(['Mitarbeiter'], $dayLabels, ['Wochensumme', 'Erloes'])];
        foreach ($byUser as $uid => $row) {
            $userModel = $users->get($uid);
            $name = $userModel instanceof User ? $userModel->name : '#' . $uid;
            $cols = [(string) $name];
            foreach ($row['days'] as $m) {
                $cols[] = (int) $m;
            }
            $cols[] = (int) $row['total'];
            $cols[] = number_format((float) $row['rate'], 2, '.', '');
            $rows[] = $cols;
        }
        $totalRow = ['Gesamt'];
        foreach ($dayTotals as $m) {
            $totalRow[] = (int) $m;
        }
        $totalRow[] = (int) $weekTotal;
        $totalRow[] = number_format((float) $weekRate, 2, '.', '');
        $rows[] = $totalRow;

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(static function ($v): string {
                $s = (string) $v;
                if (str_contains($s, ';') || str_contains($s, '"') || str_contains($s, "\n")) {
                    $s = '"' . str_replace('"', '""', $s) . '"';
                }
                return $s;
            }, $row)) . "\r\n";
        }

        return response("\xEF\xBB\xBF" . $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param array<int, array{days: array<int, int>, total: int, rate: float}> $byUser
     * @param \Illuminate\Database\Eloquent\Collection<int, User> $users
     * @param array<int, string> $dayLabels
     * @param array<int, int> $dayTotals
     */
    private function exportPdf(array $byUser, $users, array $dayLabels, array $dayTotals, int $weekTotal, float $weekRate, string $weekLabel, int $year, int $week): SymfonyResponse {
        $filename = sprintf('woche_%04d-W%02d.pdf', $year, $week);
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('reports.pdf.week-by-user', [
            'byUser' => $byUser,
            'users' => $users,
            'dayLabels' => $dayLabels,
            'dayTotals' => $dayTotals,
            'weekTotal' => $weekTotal,
            'weekRate' => $weekRate,
            'weekLabel' => $weekLabel,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }
}
