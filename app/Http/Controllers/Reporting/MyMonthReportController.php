<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MyMonthReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\{Customer, Project, Task, TimeEntry};
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * "Mein Monat"-Report: Tagesweise Aufstellung der eigenen Zeiteinträge
 * für den gewählten Monat — pro Tag: Liste der Einträge, Summe.
 *
 * Pattern angelehnt an Kimai's UserMonthController (AGPL-3.0) — eigene
 * Implementierung.
 */
class MyMonthReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        $globalRange = $this->globalDateRange();
        $year = (int) $globalRange['from']->year;
        $month = (int) $globalRange['from']->month;
        $year = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));

        $start = Carbon::create($year, $month, 1, 0, 0, 0) ?: Carbon::now()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $entries = TimeEntry::query()
            ->with(['project.customer', 'task'])
            ->where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        // Gruppiere nach Tag (Y-m-d).
        /** @var array<string, array{entries: Collection<int, TimeEntry>, minutes: int, rate: float}> $byDay */
        $byDay = [];
        $monthMinutes = 0;
        $monthRate = 0.0;
        foreach ($entries as $entry) {
            /** @var TimeEntry $entry */
            $key = Carbon::parse((string) $entry->date)->toDateString();
            if (! isset($byDay[$key])) {
                $byDay[$key] = ['entries' => collect(), 'minutes' => 0, 'rate' => 0.0];
            }
            $byDay[$key]['entries']->push($entry);
            $byDay[$key]['minutes'] += (int) $entry->minutes;
            $byDay[$key]['rate'] += (float) $entry->rate;
            $monthMinutes += (int) $entry->minutes;
            $monthRate += (float) $entry->rate;
        }
        ksort($byDay);

        $locale = app()->getLocale();
        $start->locale($locale);
        $monthLabel = $start->isoFormat('MMMM YYYY');

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($entries, $year, $month);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($byDay, $monthLabel, $monthMinutes, $monthRate, $year, $month);
        }

        return view('reports.my-month', [
            'year' => $year,
            'month' => $month,
            'monthLabel' => $monthLabel,
            'byDay' => $byDay,
            'monthMinutes' => $monthMinutes,
            'monthRate' => $monthRate,
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, TimeEntry>  $entries
     */
    private function exportCsv(\Illuminate\Database\Eloquent\Collection $entries, int $year, int $month): Response {
        $filename = sprintf('mein-monat-%04d-%02d.csv', $year, $month);
        $rows = [
            ['Datum', 'Start', 'Ende', 'Art', 'Kunde', 'Projekt', 'Aufgabe', 'Beschreibung', 'Minuten', 'Erloes'],
        ];
        foreach ($entries as $e) {
            $startedAt = $e->started_at !== null ? Carbon::parse((string) $e->started_at)->format('H:i') : '';
            $endedAt = $e->ended_at !== null ? Carbon::parse((string) $e->ended_at)->format('H:i') : '';
            $project = $e->project;
            $customerName = ($project instanceof Project && $project->customer instanceof Customer)
                ? $project->customer->name : '';
            $projectName = $project instanceof Project ? $project->name : '';
            $taskTitle = $e->task instanceof Task ? $e->task->title : '';
            $rows[] = [
                Carbon::parse((string) $e->date)->format('Y-m-d'),
                $startedAt,
                $endedAt,
                $e->kind->value,
                $customerName,
                $projectName,
                $taskTitle,
                (string) ($e->description ?? ''),
                (int) $e->minutes,
                number_format((float) $e->rate, 2, '.', ''),
            ];
        }

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
     * @param  array<string, array{entries: Collection<int, TimeEntry>, minutes: int, rate: float}>  $byDay
     */
    private function exportPdf(array $byDay, string $monthLabel, int $monthMinutes, float $monthRate, int $year, int $month): SymfonyResponse {
        $filename = sprintf('mein-monat-%04d-%02d.pdf', $year, $month);
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('reports.pdf.my-month', [
            'byDay' => $byDay,
            'monthLabel' => $monthLabel,
            'monthMinutes' => $monthMinutes,
            'monthRate' => $monthRate,
        ])->setPaper('a4');

        return $pdf->download($filename);
    }
}
