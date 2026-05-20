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

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\CoverageRequirement;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Enums\Shift\ScheduledShiftStatus;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Coverage / Soll-Ist-Besetzung: vergleicht CoverageRequirement-Sollvorgaben
 * gegen ScheduledShifts pro Schichttyp und Tag.
 */
class CoverageReportController extends Controller
{
    use ResolvesGlobalDateRange;

    public function index(Request $request): View|SymfonyResponse
    {
        $authUser = Auth::user();
        $isAdmin = $authUser instanceof User && $authUser->isAdmin();
        abort_unless($isAdmin, 403);

        $range = $this->globalDateRange();
        $fromDate = Carbon::parse($range['from']->toDateString())->startOfDay();
        $toDate = Carbon::parse($range['to']->toDateString())->startOfDay();
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        // Sicherheits-Cap: maximal ~13 Monate
        $daySpan = (int) $fromDate->diffInDays($toDate, true) + 1;
        if ($daySpan > 400) {
            $toDate = $fromDate->copy()->addDays(399);
            $to = $toDate->toDateString();
            $daySpan = 400;
        }

        [$perShiftType, $underfilledDays, $totals] = $this->aggregate($fromDate, $toDate);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($perShiftType, $totals, $from, $to);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($perShiftType, $underfilledDays, $totals, $from, $to);
        }

        return view('reports.coverage', [
            'from' => $from,
            'to' => $to,
            'rows' => $perShiftType,
            'underfilled' => $underfilledDays,
            'totals' => $totals,
            'daySpan' => $daySpan,
        ]);
    }

    /**
     * @return array{
     *   0: array<int, array{shiftType: ShiftType, required:int, scheduled:int, gap:int, fill_rate:float|null, days_under:int}>,
     *   1: array<int, array{date:string, shiftType: ShiftType, required:int, scheduled:int, gap:int}>,
     *   2: array{shift_types:int, required:int, scheduled:int, gap:int, fill_rate:float|null, days_under:int}
     * }
     */
    private function aggregate(Carbon $from, Carbon $to): array
    {
        /** @var Collection<int, ShiftType> $shiftTypes */
        $shiftTypes = ShiftType::query()->orderBy('name')->get();
        if ($shiftTypes->isEmpty()) {
            return [[], [], ['shift_types' => 0, 'required' => 0, 'scheduled' => 0, 'gap' => 0, 'fill_rate' => null, 'days_under' => 0]];
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
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
            ->whereNotNull('shift_type_id')
            ->get(['date', 'shift_type_id'])
            ->each(function ($s) use (&$scheduledByKey): void {
                $key = $s->date->toDateString().'|'.$s->shift_type_id;
                $scheduledByKey[$key] = ($scheduledByKey[$key] ?? 0) + 1;
            });

        /** @var array<int, int> $reqSumBySid */
        $reqSumBySid = [];
        /** @var array<int, int> $schedSumBySid */
        $schedSumBySid = [];
        /** @var array<int, int> $underDaysBySid */
        $underDaysBySid = [];
        $underfilled = [];

        $period = CarbonPeriod::create($from, $to);
        foreach ($period as $day) {
            /** @var Carbon $day */
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
                $scheduled = $scheduledByKey[$dateStr.'|'.$sid] ?? 0;
                $gap = $scheduled - $required;
                $reqSumBySid[$sid] = ($reqSumBySid[$sid] ?? 0) + $required;
                $schedSumBySid[$sid] = ($schedSumBySid[$sid] ?? 0) + $scheduled;
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
            $totRequired += $req;
            $totScheduled += $sched;
            $totUnderDays += $under;
        }

        usort($underfilled, fn ($a, $b) => $a['date'] <=> $b['date']);

        $totals = [
            'shift_types' => count($rows),
            'required' => $totRequired,
            'scheduled' => $totScheduled,
            'gap' => $totScheduled - $totRequired,
            'fill_rate' => $totRequired > 0 ? $totScheduled / $totRequired : null,
            'days_under' => $totUnderDays,
        ];

        return [$rows, $underfilled, $totals];
    }

    /**
     * @param  array<int, array{shiftType: ShiftType, required:int, scheduled:int, gap:int, fill_rate:float|null, days_under:int}>  $rows
     * @param  array{shift_types:int, required:int, scheduled:int, gap:int, fill_rate:float|null, days_under:int}  $totals
     */
    private function exportCsv(array $rows, array $totals, string $from, string $to): Response
    {
        $filename = sprintf('coverage_%s_%s.csv', $from, $to);
        $out = [['Schichttyp', 'Soll (Personentage)', 'Ist (Personentage)', 'Differenz', 'Erfüllung %', 'Tage mit Unterdeckung']];
        foreach ($rows as $r) {
            $out[] = [
                (string) $r['shiftType']->name,
                $r['required'],
                $r['scheduled'],
                $r['gap'],
                $r['fill_rate'] !== null ? number_format($r['fill_rate'] * 100, 1, '.', '') : '',
                $r['days_under'],
            ];
        }
        $out[] = [
            'Gesamt',
            $totals['required'],
            $totals['scheduled'],
            $totals['gap'],
            $totals['fill_rate'] !== null ? number_format($totals['fill_rate'] * 100, 1, '.', '') : '',
            $totals['days_under'],
        ];

        $csv = '';
        foreach ($out as $row) {
            $csv .= implode(';', array_map(static function ($v): string {
                $s = (string) $v;
                if (str_contains($s, ';') || str_contains($s, '"') || str_contains($s, "\n")) {
                    $s = '"'.str_replace('"', '""', $s).'"';
                }

                return $s;
            }, $row))."\r\n";
        }

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  array<int, array{shiftType: ShiftType, required:int, scheduled:int, gap:int, fill_rate:float|null, days_under:int}>  $rows
     * @param  array<int, array{date:string, shiftType: ShiftType, required:int, scheduled:int, gap:int}>  $underfilled
     * @param  array{shift_types:int, required:int, scheduled:int, gap:int, fill_rate:float|null, days_under:int}  $totals
     */
    private function exportPdf(array $rows, array $underfilled, array $totals, string $from, string $to): SymfonyResponse
    {
        $filename = sprintf('coverage_%s_%s.pdf', $from, $to);
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('reports.pdf.coverage', [
            'rows' => $rows,
            'underfilled' => $underfilled,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
