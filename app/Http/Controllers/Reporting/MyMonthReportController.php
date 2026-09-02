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

use App\Enums\TimeEntry\TimeEntryKind;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Customer, Project, Task, TimeEntry};
use App\Support\Query\DateRange;
use App\Support\XlsxExport;
use Carbon\Carbon;
use CommonToolkit\Helper\Data\NumberHelper;
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
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$rangeFrom] = $this->resolveRange($request);
        $year = max(2000, min(2100, (int) $rangeFrom->year));
        $month = max(1, min(12, (int) $rangeFrom->month));

        $start = Carbon::create($year, $month, 1, 0, 0, 0) ?: Carbon::now()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $filters = $this->standardFilters(
            $request,
            ['customer', 'project'],
            $start->toImmutable(),
            $end->toImmutable(),
        );
        $kind = (string) $request->query('kind', 'all');
        if (! in_array($kind, array_merge(['all'], TimeEntryKind::values()), true)) {
            $kind = 'all';
        }

        $entriesQuery = TimeEntry::query()
            ->with(['project.customer', 'task'])
            ->where('user_id', $userId)
            ->whereBetween('date', DateRange::days($start, $end))
            ->when($kind !== 'all', fn($q) => $q->where('kind', $kind))
            ->orderBy('date')
            ->orderBy('started_at')
            ->orderBy('id');
        $entries = $filters->applyToTimeEntryQuery($entriesQuery)->get();

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
            $byDay[$key]['rate'] += ($entry->rate?->toFloat() ?? 0.0);
            $monthMinutes += (int) $entry->minutes;
            $monthRate += ($entry->rate?->toFloat() ?? 0.0);
        }
        ksort($byDay);

        $locale = app()->getLocale();
        $start->locale($locale);
        $monthLabel = $start->isoFormat('MMMM YYYY');

        $exportFilters = array_merge(['year' => $year, 'month' => $month, 'kind' => $kind], $filters->toAuditArray());

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($entries, $year, $month, $request, $exportFilters);
        }
        if ($request->query('export') === 'xlsx') {
            return $this->exportXlsx($entries, $year, $month);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($byDay, $monthLabel, $this->dailyHoursSeries($byDay, $start), $monthMinutes, $monthRate, $request, $exportFilters);
        }

        return view('reports.my-month', [
            'year' => $year,
            'month' => $month,
            'monthLabel' => $monthLabel,
            'byDay' => $byDay,
            'monthMinutes' => $monthMinutes,
            'monthRate' => $monthRate,
            'kind' => $kind,
            'standardFilters' => $filters,
            'filterFields' => ['customer', 'project'],
            'dailySeries' => $this->dailyHoursSeries($byDay, $start),
            'weekKindSeries' => $this->weeklyKindSeries($entries),
            'kindBands' => TimeEntryKind::chartBands(),
            ...$this->standardFilterOptions(['customer', 'project'], $filters),
        ]);
    }

    /**
     * Stunden je Kalendertag des Monats (Chart-Datenkontrakt, Screen + PDF).
     *
     * @param  array<string, array{entries: Collection<int, TimeEntry>, minutes: int, rate: float}>  $byDay
     * @return list<array{x: string, y: float}>
     */
    private function dailyHoursSeries(array $byDay, Carbon $start): array {
        if ($byDay === []) {
            return []; // Leerzustand statt Null-Linie (§Diagramm-UX).
        }

        $series = [];
        for ($day = 1; $day <= (int) $start->daysInMonth; $day++) {
            $key = sprintf('%04d-%02d-%02d', $start->year, $start->month, $day);
            $series[] = [
                'x' => sprintf('%02d.', $day),
                'y' => round(((int) ($byDay[$key]['minutes'] ?? 0)) / 60, 1),
            ];
        }

        return $series;
    }

    /**
     * Stunden je ISO-Woche, aufgeteilt nach Art (work/travel/standby).
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, TimeEntry>  $entries
     * @return list<array<string, string|float>>
     */
    private function weeklyKindSeries(\Illuminate\Database\Eloquent\Collection $entries): array {
        $byWeek = [];
        foreach ($entries as $entry) {
            $date = Carbon::parse((string) $entry->date);
            $week = 'KW ' . $date->isoWeek;
            $byWeek[$week] ??= array_fill_keys(TimeEntryKind::values(), 0);
            $byWeek[$week][$entry->kind->value] += (int) $entry->minutes;
        }

        $series = [];
        foreach ($byWeek as $week => $minutesByKind) {
            $row = ['x' => $week];
            foreach ($minutesByKind as $kindValue => $minutes) {
                $row[$kindValue] = round($minutes / 60, 1);
            }
            $series[] = $row;
        }

        return $series;
    }

    /**
     * @return list<string>
     */
    private function exportHeaders(): array {
        return ['Datum', 'Start', 'Ende', 'Art', 'Kunde', 'Projekt', 'Aufgabe', 'Beschreibung', 'Minuten', 'Erloes'];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, TimeEntry>  $entries
     * @return list<list<int|float|string|null>>
     */
    private function exportRows(\Illuminate\Database\Eloquent\Collection $entries): array {
        $rows = [];
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
                ($e->rate?->toFloat() ?? 0.0),
            ];
        }

        return $rows;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, TimeEntry>  $entries
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(\Illuminate\Database\Eloquent\Collection $entries, int $year, int $month, Request $request, array $exportFilters): Response {
        $filename = sprintf('mein-monat-%04d-%02d.csv', $year, $month);
        $rows = [$this->exportHeaders()];
        foreach ($this->exportRows($entries) as $row) {
            // Floats für CSV als DE-Decimal-String serialisieren.
            $row = array_map(static fn($v) => is_float($v) ? NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) : $v, $row);
            $rows[] = $row;
        }

        return $this->csvWithMetadata($rows, $filename, 'my-month', $exportFilters, $request);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, TimeEntry>  $entries
     */
    private function exportXlsx(\Illuminate\Database\Eloquent\Collection $entries, int $year, int $month): SymfonyResponse {
        $filename = sprintf('mein-monat-%04d-%02d.xlsx', $year, $month);

        return XlsxExport::streamFromArray($filename, $this->exportHeaders(), $this->exportRows($entries));
    }

    /**
     * @param  array<string, array{entries: Collection<int, TimeEntry>, minutes: int, rate: float}>  $byDay
     * @param  list<array{x: string, y: float}>  $dailySeries
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $byDay, string $monthLabel, array $dailySeries, int $monthMinutes, float $monthRate, Request $request, array $exportFilters): SymfonyResponse {
        $filename = sprintf('mein-monat-%04d-%02d.pdf', (int) $exportFilters['year'], (int) $exportFilters['month']);
        return $this->pdfDownload('reports.pdf.my-month', [
            'byDay' => $byDay,
            'monthLabel' => $monthLabel,
            'monthMinutes' => $monthMinutes,
            'monthRate' => $monthRate,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Stunden pro Tag'),
                'unit' => 'h',
                'xLabel' => __('Tag'),
                'yLabel' => __('Stunden'),
                'series' => array_values(array_filter($dailySeries, fn(array $point): bool => $point['y'] > 0)),
            ],
        ], $filename, request: $request, reportCode: 'my-month', filters: $exportFilters);
    }
}
