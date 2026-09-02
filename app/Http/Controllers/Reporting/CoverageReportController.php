<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoverageReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{CoverageRequirement, ScheduledShift, ShiftType};
use App\Support\Query\DateRange;
use Carbon\{Carbon, CarbonImmutable, CarbonPeriod};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Coverage / Soll-Ist-Besetzung: vergleicht CoverageRequirement-Sollvorgaben
 * gegen ScheduledShifts pro Schichttyp und Tag.
 */
class CoverageReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        abort_unless($this->viewerIsAdmin(), 403);

        [$fromDate, $toDate] = $this->resolveRange($request);
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        // Sicherheits-Cap: maximal ~13 Monate
        $daySpan = (int) $fromDate->diffInDays($toDate, true) + 1;
        if ($daySpan > 400) {
            $toDate = $fromDate->addDays(399);
            $to = $toDate->toDateString();
            $daySpan = 400;
        }

        // Team-Filter wirkt auf die Ist-Seite (geplante Schichten); die
        // Soll-Vorgaben sind schichttyp-, nicht teambezogen.
        $filters = $this->standardFilters($request, ['team'], $fromDate, $toDate);

        [$perShiftType, $underfilledDays, $totals, $weekdayMatrix] = $this->aggregate($fromDate, $toDate, $filters->teamUserIds());
        $exportFilters = $filters->toAuditArray();

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($perShiftType, $totals, $from, $to, $exportFilters, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($perShiftType, $underfilledDays, $totals, $weekdayMatrix, $from, $to, $exportFilters, $request);
        }

        return view('reports.coverage', [
            'from' => $from,
            'to' => $to,
            'rows' => $perShiftType,
            'underfilled' => $underfilledDays,
            'totals' => $totals,
            'daySpan' => $daySpan,
            'standardFilters' => $filters,
            'filterFields' => ['team'],
            'coverageHeatmapRows' => $this->coverageHeatmapRows($weekdayMatrix),
            'weekdayLabels' => $this->weekdayLabels(),
            'underfilledWeekSeries' => $this->underfilledWeekSeries($underfilledDays),
            ...$this->standardFilterOptions(['team'], $filters),
        ]);
    }

    /**
     * Heatmap-Zeilen Schichttyp × Wochentag: Zelle = Deckungsgrad in %,
     * Anzeige „Ist/Soll"; Wochentage ohne Soll bleiben leer (null).
     *
     * @param  array<int, array{shiftType: ShiftType, required: array<int, int>, scheduled: array<int, int>}>  $weekdayMatrix
     * @return list<array{label: string, cells: list<array{value: float, label: string, title: string}|null>}>
     */
    private function coverageHeatmapRows(array $weekdayMatrix): array {
        $dayLabels = $this->weekdayLabels();
        $rows = [];
        foreach ($weekdayMatrix as $entry) {
            $cells = [];
            for ($idx = 0; $idx < 7; $idx++) {
                $required = $entry['required'][$idx] ?? 0;
                $scheduled = $entry['scheduled'][$idx] ?? 0;
                if ($required <= 0) {
                    $cells[] = null;

                    continue;
                }
                $rate = round($scheduled / $required * 100);
                $cells[] = [
                    'value' => $rate,
                    'label' => $scheduled . '/' . $required,
                    'title' => $entry['shiftType']->name . ' · ' . $dayLabels[$idx] . ': ' . $rate . ' %',
                ];
            }
            $rows[] = ['label' => (string) $entry['shiftType']->name, 'cells' => $cells];
        }

        return $rows;
    }

    /**
     * Übersetzte Wochentagskürzel Mo–So (2026-01-05 ist ein Montag).
     *
     * @return list<string>
     */
    private function weekdayLabels(): array {
        $monday = CarbonImmutable::parse('2026-01-05');
        $labels = [];
        for ($i = 0; $i < 7; $i++) {
            $labels[] = $monday->addDays($i)->translatedFormat('D');
        }

        return $labels;
    }

    /**
     * Fehlende Personentage je ISO-Woche (aus den Unterdeckungstagen).
     *
     * @param  array<int, array{date:string, shiftType: ShiftType, required:int, scheduled:int, gap:int}>  $underfilled
     * @return list<array{x: string, y: int}>
     */
    private function underfilledWeekSeries(array $underfilled): array {
        if ($underfilled === []) {
            return []; // Leerzustand statt Null-Serie (§Diagramm-UX).
        }

        /** @var array<string, int> $byWeek */
        $byWeek = [];
        foreach ($underfilled as $day) {
            $date = Carbon::parse($day['date']);
            $key = sprintf('KW %02d/%02d', $date->isoWeek, $date->isoWeekYear % 100);
            $byWeek[$key] = ($byWeek[$key] ?? 0) + (-1 * $day['gap']);
        }

        $series = [];
        foreach ($byWeek as $week => $missing) {
            $series[] = ['x' => $week, 'y' => $missing];
        }

        return $series;
    }

    /**
     * @param  list<int>  $teamUserIds  leere Liste = kein Team-Filter
     * @return array{
     *   0: array<int, array{shiftType: ShiftType, required:int, scheduled:int, gap:int, fill_rate:float|null, days_under:int}>,
     *   1: array<int, array{date:string, shiftType: ShiftType, required:int, scheduled:int, gap:int}>,
     *   2: array{shift_types:int, required:int, scheduled:int, gap:int, fill_rate:float|null, days_under:int},
     *   3: array<int, array{shiftType: ShiftType, required: array<int, int>, scheduled: array<int, int>}>
     * }
     */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to, array $teamUserIds = []): array {
        /** @var Collection<int, ShiftType> $shiftTypes */
        $shiftTypes = ShiftType::query()->orderBy('name')->get();
        if ($shiftTypes->isEmpty()) {
            return [[], [], ['shift_types' => 0, 'required' => 0, 'scheduled' => 0, 'gap' => 0, 'fill_rate' => null, 'days_under' => 0], []];
        }
        $shiftTypeIds = $shiftTypes->pluck('id')->all();

        // CoverageRequirements vorab gruppieren
        /** @var array<int, array<string, int>> $reqByDate [shift_type_id][YYYY-MM-DD] */
        $reqByDate = [];
        /** @var array<int, array<int, int>> $reqByWeekday [shift_type_id][0..6] */
        $reqByWeekday = [];
        /** @var array<int, int> $reqDefault [shift_type_id] */
        $reqDefault = [];
        foreach (CoverageRequirement::query()->whereIn('shift_type_id', $shiftTypeIds)->get() as $r) {
            $sid = (int) $r->shift_type_id;
            if ($r->specific_date !== null) {
                $reqByDate[$sid][$r->specific_date->toDateString()] = (int) $r->min_staff;
            } elseif ($r->weekday !== null) {
                $reqByWeekday[$sid][(int) $r->weekday] = (int) $r->min_staff;
            } else {
                $reqDefault[$sid] = (int) $r->min_staff;
            }
        }

        // Scheduled Shifts vorab je (date|shift_type_id) zählen
        /** @var array<string, int> $scheduledByKey */
        $scheduledByKey = [];
        ScheduledShift::query()
            ->whereBetween('date', DateRange::days($from, $to))
            ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
            ->whereNotNull('shift_type_id')
            ->when($teamUserIds !== [], fn ($q) => $q->whereIn('user_id', $teamUserIds))
            ->get(['date', 'shift_type_id'])
            ->each(function ($s) use (&$scheduledByKey): void {
                $key = $s->date->toDateString() . '|' . $s->shift_type_id;
                $scheduledByKey[$key] = ($scheduledByKey[$key] ?? 0) + 1;
            });

        /** @var array<int, int> $reqSumBySid */
        $reqSumBySid = [];
        /** @var array<int, int> $schedSumBySid */
        $schedSumBySid = [];
        /** @var array<int, int> $underDaysBySid */
        $underDaysBySid = [];
        /** @var array<int, array<int, int>> $reqByWeekdaySum [shift_type_id][0=Mo..6=So] */
        $reqByWeekdaySum = [];
        /** @var array<int, array<int, int>> $schedByWeekdaySum [shift_type_id][0=Mo..6=So] */
        $schedByWeekdaySum = [];
        $underfilled = [];

        $period = CarbonPeriod::create($from, $to);
        foreach ($period as $day) {
            /** @var CarbonImmutable $day */
            $dateStr = $day->toDateString();
            $iso = (int) $day->dayOfWeekIso;       // 1=Mon … 7=Sun
            $weekday = $iso === 7 ? 0 : $iso;      // Modell: 0=So..6=Sa
            foreach ($shiftTypes as $st) {
                $sid = (int) $st->id;
                $required = $reqByDate[$sid][$dateStr]
                    ?? $reqByWeekday[$sid][$weekday]
                    ?? $reqDefault[$sid]
                    ?? 0;
                if ($required <= 0) {
                    continue;
                }
                $scheduled = $scheduledByKey[$dateStr . '|' . $sid] ?? 0;
                $gap = $scheduled - $required;
                $reqSumBySid[$sid] = ($reqSumBySid[$sid] ?? 0) + $required;
                $schedSumBySid[$sid] = ($schedSumBySid[$sid] ?? 0) + $scheduled;
                $reqByWeekdaySum[$sid][$iso - 1] = ($reqByWeekdaySum[$sid][$iso - 1] ?? 0) + $required;
                $schedByWeekdaySum[$sid][$iso - 1] = ($schedByWeekdaySum[$sid][$iso - 1] ?? 0) + $scheduled;
                if ($gap < 0) {
                    $underDaysBySid[$sid] = ($underDaysBySid[$sid] ?? 0) + 1;
                    $underfilled[] = [
                        'date' => $dateStr,
                        'shiftType' => $st,
                        'required' => $required,
                        'scheduled' => $scheduled,
                        'gap' => $gap,
                    ];
                }
            }
        }

        $totRequired = 0;
        $totScheduled = 0;
        $totUnderDays = 0;
        $rows = [];
        $weekdayMatrix = [];
        foreach ($shiftTypes as $st) {
            $sid = (int) $st->id;
            $req = $reqSumBySid[$sid] ?? 0;
            $sched = $schedSumBySid[$sid] ?? 0;
            $under = $underDaysBySid[$sid] ?? 0;
            if ($req === 0 && $sched === 0) {
                continue;
            }
            $rows[] = [
                'shiftType' => $st,
                'required' => $req,
                'scheduled' => $sched,
                'gap' => $sched - $req,
                'fill_rate' => $req > 0 ? $sched / $req : null,
                'days_under' => $under,
            ];
            $weekdayMatrix[] = [
                'shiftType' => $st,
                'required' => $reqByWeekdaySum[$sid] ?? [],
                'scheduled' => $schedByWeekdaySum[$sid] ?? [],
            ];
            $totRequired += $req;
            $totScheduled += $sched;
            $totUnderDays += $under;
        }

        usort($underfilled, fn($a, $b) => $a['date'] <=> $b['date']);

        $totals = [
            'shift_types' => count($rows),
            'required' => $totRequired,
            'scheduled' => $totScheduled,
            'gap' => $totScheduled - $totRequired,
            'fill_rate' => $totRequired > 0 ? $totScheduled / $totRequired : null,
            'days_under' => $totUnderDays,
        ];

        return [$rows, $underfilled, $totals, $weekdayMatrix];
    }

    /**
     * @param  array<int, array{shiftType: ShiftType, required:int, scheduled:int, gap:int, fill_rate:float|null, days_under:int}>  $rows
     * @param  array{shift_types:int, required:int, scheduled:int, gap:int, fill_rate:float|null, days_under:int}  $totals
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(array $rows, array $totals, string $from, string $to, array $exportFilters, Request $request): Response {
        $filename = sprintf('coverage_%s_%s.csv', $from, $to);
        $out = [['Schichttyp', 'Soll (Personentage)', 'Ist (Personentage)', 'Differenz', 'Erfüllung %', 'Tage mit Unterdeckung']];
        foreach ($rows as $r) {
            $out[] = [
                (string) $r['shiftType']->name,
                $r['required'],
                $r['scheduled'],
                $r['gap'],
                $r['fill_rate'] !== null ? NumberHelper::toUSFormat($r['fill_rate'] * 100, 1) : '',
                $r['days_under'],
            ];
        }
        $out[] = [
            'Gesamt',
            $totals['required'],
            $totals['scheduled'],
            $totals['gap'],
            $totals['fill_rate'] !== null ? NumberHelper::toUSFormat($totals['fill_rate'] * 100, 1) : '',
            $totals['days_under'],
        ];

        return $this->csvWithMetadata($out, $filename, 'coverage', $exportFilters, $request);
    }

    /**
     * @param  array<int, array{shiftType: ShiftType, required:int, scheduled:int, gap:int, fill_rate:float|null, days_under:int}>  $rows
     * @param  array<int, array{date:string, shiftType: ShiftType, required:int, scheduled:int, gap:int}>  $underfilled
     * @param  array{shift_types:int, required:int, scheduled:int, gap:int, fill_rate:float|null, days_under:int}  $totals
     * @param  array<int, array{shiftType: ShiftType, required: array<int, int>, scheduled: array<int, int>}>  $weekdayMatrix
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $rows, array $underfilled, array $totals, array $weekdayMatrix, string $from, string $to, array $exportFilters, Request $request): SymfonyResponse {
        $filename = sprintf('coverage_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.coverage', [
            'rows' => $rows,
            'underfilled' => $underfilled,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'chart' => [
                'type' => 'heatmap',
                'title' => __('Deckungsgrad je Schichttyp und Wochentag'),
                'unit' => '%',
                'xLabel' => __('Schichttyp'),
                'rows' => $this->coverageHeatmapRows($weekdayMatrix),
                'colLabels' => $this->weekdayLabels(),
            ],
        ], $filename, request: $request, reportCode: 'coverage', filters: $exportFilters);
    }
}
