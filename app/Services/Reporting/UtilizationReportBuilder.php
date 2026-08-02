<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UtilizationReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Models\{Invoice, TimeEntry, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Auslastung & Realisierung (MVP-467, Feature 002).
 *
 * Definitionen bewusst deckungsgleich mit dem Zielwert-Katalog
 * ({@see \App\Enums\Reporting\ReportTargetMetric}):
 *  - Auslastung        = erfasste Zeit / Soll-Zeit (WorkBalance) in %
 *  - Abrechenbare Quote = abrechenbare / erfasste Zeit in %
 *  - Realisierung      = fakturierte / abrechenbare Zeit in % — nur bei
 *    lokaler Fakturierung (invoice_item_time_entries); sonst ehrlich null.
 *
 * Soll-Minuten kommen aus den Tages-Salden von
 * {@see WorkBalanceCalculator::range()} — keine parallele Soll-Berechnung.
 */
class UtilizationReportBuilder {
    public function __construct(private readonly WorkBalanceCalculator $balance) {}

    /**
     * @param  Collection<int, User>  $users
     * @return array{
     *   rows: list<array{userId:int, userName:string, targetMinutes:int, trackedMinutes:int,
     *     billableMinutes:int, invoicedMinutes:int, utilization:?float, billableRate:?float, realization:?float}>,
     *   monthly: list<array{month:string, utilization:?float, billableRate:?float}>,
     *   monthlyBoxes: list<array{x:string, min:float, q1:float, median:float, q3:float, max:float, n:int}>,
     *   totals: array{targetMinutes:int, trackedMinutes:int, billableMinutes:int, invoicedMinutes:int,
     *     utilization:?float, billableRate:?float, realization:?float},
     *   hasInvoiceData: bool,
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, Collection $users): array {
        $userIds = $users->pluck('id')->map(static fn($v): int => (int) $v)->all();

        // Abrechenbar/gesamt je Nutzer und Monat aus TimeEntry.
        $billableByUser = [];
        $billableByUserMonth = [];
        if ($userIds !== []) {
            TimeEntry::query()
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->whereIn('user_id', $userIds)
                ->where('billable', true)
                ->get(['user_id', 'date', 'minutes'])
                ->each(function (TimeEntry $e) use (&$billableByUser, &$billableByUserMonth): void {
                    $uid = (int) $e->user_id;
                    $month = $e->date instanceof \Carbon\CarbonInterface ? $e->date->format('Y-m') : substr((string) $e->date, 0, 7);
                    $billableByUser[$uid] = ($billableByUser[$uid] ?? 0) + (int) $e->minutes;
                    $billableByUserMonth[$uid][$month] = ($billableByUserMonth[$uid][$month] ?? 0) + (int) $e->minutes;
                });
        }

        // Fakturierte Minuten (Rechnungsposition ↔ Zeiteintrag).
        $hasInvoiceData = Invoice::query()->exists();
        $invoicedByUser = [];
        if ($hasInvoiceData && $userIds !== []) {
            TimeEntry::query()
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->whereIn('user_id', $userIds)
                ->whereIn('id', DB::table('invoice_item_time_entries')->select('time_entry_id'))
                ->get(['user_id', 'minutes'])
                ->each(function (TimeEntry $e) use (&$invoicedByUser): void {
                    $invoicedByUser[(int) $e->user_id] = ($invoicedByUser[(int) $e->user_id] ?? 0) + (int) $e->minutes;
                });
        }

        $rows = [];
        $monthlyTarget = [];
        $monthlyTracked = [];
        $monthlyBillable = [];
        $userMonthUtilization = [];
        $totalTarget = 0;
        $totalTracked = 0;

        foreach ($users as $user) {
            $uid = (int) $user->id;
            $period = $this->balance->range($user, $from, $to);

            $userMonthTarget = [];
            $userMonthTracked = [];
            foreach ($period->days as $day) {
                $month = substr($day->date, 0, 7);
                $userMonthTarget[$month] = ($userMonthTarget[$month] ?? 0) + $day->targetMinutes;
                $userMonthTracked[$month] = ($userMonthTracked[$month] ?? 0) + $day->trackedMinutes;
            }
            foreach ($userMonthTarget as $month => $minutes) {
                $monthlyTarget[$month] = ($monthlyTarget[$month] ?? 0) + $minutes;
                $monthlyTracked[$month] = ($monthlyTracked[$month] ?? 0) + ($userMonthTracked[$month] ?? 0);
                $monthlyBillable[$month] = ($monthlyBillable[$month] ?? 0) + ($billableByUserMonth[$uid][$month] ?? 0);
                if ($minutes > 0) {
                    $userMonthUtilization[$month][] = round(($userMonthTracked[$month] ?? 0) / $minutes * 100, 1);
                }
            }

            $target = $period->targetMinutes;
            $tracked = $period->trackedMinutes;
            $billable = (int) ($billableByUser[$uid] ?? 0);
            $invoiced = (int) ($invoicedByUser[$uid] ?? 0);

            if ($target === 0 && $tracked === 0) {
                continue; // weder Soll noch Ist — Zeile wäre reine Null-Optik
            }

            $totalTarget += $target;
            $totalTracked += $tracked;

            $rows[] = [
                'userId' => $uid,
                'userName' => (string) $user->name,
                'targetMinutes' => $target,
                'trackedMinutes' => $tracked,
                'billableMinutes' => $billable,
                'invoicedMinutes' => $invoiced,
                'utilization' => $target > 0 ? round($tracked / $target * 100, 1) : null,
                'billableRate' => $tracked > 0 ? round($billable / $tracked * 100, 1) : null,
                'realization' => $hasInvoiceData && $billable > 0 ? round($invoiced / $billable * 100, 1) : null,
            ];
        }

        ksort($monthlyTarget);
        $monthly = [];
        foreach (array_keys($monthlyTarget) as $month) {
            $monthly[] = [
                'month' => $month,
                'utilization' => $monthlyTarget[$month] > 0 ? round($monthlyTracked[$month] / $monthlyTarget[$month] * 100, 1) : null,
                'billableRate' => ($monthlyTracked[$month] ?? 0) > 0 ? round(($monthlyBillable[$month] ?? 0) / $monthlyTracked[$month] * 100, 1) : null,
            ];
        }

        ksort($userMonthUtilization);
        $monthlyBoxes = [];
        foreach ($userMonthUtilization as $month => $values) {
            sort($values);
            $monthlyBoxes[] = [
                'x' => $month,
                'min' => $values[0],
                'q1' => round($this->percentile($values, 0.25), 1),
                'median' => round($this->percentile($values, 0.5), 1),
                'q3' => round($this->percentile($values, 0.75), 1),
                'max' => $values[count($values) - 1],
                'n' => count($values),
            ];
        }

        $totalBillable = (int) array_sum($billableByUser);
        $totalInvoiced = (int) array_sum($invoicedByUser);

        return [
            'rows' => $rows,
            'monthly' => $monthly,
            'monthlyBoxes' => $monthlyBoxes,
            'totals' => [
                'targetMinutes' => $totalTarget,
                'trackedMinutes' => $totalTracked,
                'billableMinutes' => $totalBillable,
                'invoicedMinutes' => $totalInvoiced,
                'utilization' => $totalTarget > 0 ? round($totalTracked / $totalTarget * 100, 1) : null,
                'billableRate' => $totalTracked > 0 ? round($totalBillable / $totalTracked * 100, 1) : null,
                'realization' => $hasInvoiceData && $totalBillable > 0 ? round($totalInvoiced / $totalBillable * 100, 1) : null,
            ],
            'hasInvoiceData' => $hasInvoiceData,
        ];
    }

    /**
     * Lineare Interpolation zwischen den Rängen (Standard-Perzentil).
     *
     * @param  list<float>  $sorted  aufsteigend sortiert, nicht leer
     */
    private function percentile(array $sorted, float $p): float {
        $n = count($sorted);
        if ($n === 1) {
            return $sorted[0];
        }

        $idx = ($n - 1) * $p;
        $lo = (int) floor($idx);
        $hi = (int) ceil($idx);

        return $sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($idx - $lo);
    }
}
