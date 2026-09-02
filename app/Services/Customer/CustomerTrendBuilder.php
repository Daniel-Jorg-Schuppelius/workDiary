<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerTrendBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Customer;

use App\Models\{Customer, Invoice, LexofficeVoucher, TimeEntry, User};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Gate};

/**
 * Kompakte Monats-Trends (letzte 12 Monate bis Anker) für die Kundenakte:
 * Zeiteinsatz (abrechenbar / nicht abrechenbar) und tatsächlich fakturierter
 * Umsatz (Lexoffice-Belege + lokale Rechnungen) inkl. Vorjahresvergleich.
 * Aus CustomerController::show() extrahiert (Vollscan 2026-08-23, B21);
 * aggregiert per SQL je Monat statt Einzelzeilen zu hydrieren (A9).
 */
class CustomerTrendBuilder {
    /** Gleiche Typ-/Statuslogik wie die Umsatz-KPI im CustomerDetailAssembler. */
    public const INVOICE_TYPES = ['invoice', 'salesinvoice', 'purchaseinvoice'];

    public const VOID_STATUSES = ['voided', 'cancelled'];

    /**
     * @param  array<int>  $projectIds
     * @return array{hours: list<array<string, float|string>>, revenue: list<array<string, float|string>>}
     */
    public function build(Customer $customer, array $projectIds, CarbonImmutable $anchor, User $user): array {
        $start = $anchor->startOfMonth()->subMonthsNoOverflow(11);
        $end = $anchor->endOfMonth();

        /** @var array<string, CarbonImmutable> $months */
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->addMonthsNoOverflow($i);
            $months[$m->format('Y-m')] = $m;
        }

        // Vorjahresvergleich: dieselben Monate ein Jahr früher als compare-Linie/-Spalte.
        $prevStart = $start->subYearsNoOverflow(1);
        /** @var array<string, string> $prevKeyOf  aktueller Y-m => Vorjahres-Y-m */
        $prevKeyOf = [];
        foreach ($months as $ym => $m) {
            $prevKeyOf[$ym] = $m->subYearsNoOverflow(1)->format('Y-m');
        }
        $prevMinutes = array_fill_keys(array_values($prevKeyOf), 0);
        $prevRevenue = array_fill_keys(array_values($prevKeyOf), 0.0);

        $minutes = array_fill_keys(array_keys($months), 0);
        $billableMinutes = array_fill_keys(array_keys($months), 0);
        if ($projectIds !== []) {
            [$yearExpr, $monthExpr] = $this->yearMonthExprs('date');
            /** @var iterable<int, object{y: int|string, m: int|string, mins: int|string, billable_mins: int|string}> $rows */
            $rows = TimeEntry::query()
                ->whereIn('project_id', $projectIds)
                ->whereBetween('date', DateRange::days($prevStart, $end))
                ->toBase()
                ->selectRaw("{$yearExpr} as y, {$monthExpr} as m, COALESCE(SUM(minutes), 0) as mins, COALESCE(SUM(CASE WHEN billable = 1 THEN minutes ELSE 0 END), 0) as billable_mins")
                ->groupBy('y', 'm')
                ->get();
            foreach ($rows as $row) {
                $ym = sprintf('%04d-%02d', (int) $row->y, (int) $row->m);
                if (isset($minutes[$ym])) {
                    $minutes[$ym] = (int) $row->mins;
                    $billableMinutes[$ym] = (int) $row->billable_mins;
                } elseif (isset($prevMinutes[$ym])) {
                    $prevMinutes[$ym] = (int) $row->mins;
                }
            }
        }

        // Fakturierter Umsatz je Monat — gleiche Typ-/Statuslogik wie die Umsatz-KPI.
        $revenue = array_fill_keys(array_keys($months), 0.0);

        [$yearExpr, $monthExpr] = $this->yearMonthExprs('voucher_date');
        /** @var iterable<int, object{y: int|string, m: int|string, amount: float|int|string}> $voucherRows */
        $voucherRows = LexofficeVoucher::query()
            ->where('customer_id', $customer->getKey())
            ->where('archived', false)
            ->whereIn('voucher_type', self::INVOICE_TYPES)
            ->whereNotIn('voucher_status', self::VOID_STATUSES)
            ->whereBetween('voucher_date', [$prevStart->startOfDay(), $end->endOfDay()])
            ->toBase()
            ->selectRaw("{$yearExpr} as y, {$monthExpr} as m, COALESCE(SUM(total_amount), 0) as amount")
            ->groupBy('y', 'm')
            ->get();
        foreach ($voucherRows as $row) {
            $ym = sprintf('%04d-%02d', (int) $row->y, (int) $row->m);
            if (isset($revenue[$ym])) {
                $revenue[$ym] += (float) $row->amount;
            } elseif (isset($prevRevenue[$ym])) {
                $prevRevenue[$ym] += (float) $row->amount;
            }
        }

        if (Gate::forUser($user)->allows('viewAny', Invoice::class)) {
            [$yearExpr, $monthExpr] = $this->yearMonthExprs('issued_on');
            /** @var iterable<int, object{y: int|string, m: int|string, amount: float|int|string}> $invoiceRows */
            $invoiceRows = Invoice::query()
                ->where('customer_id', $customer->getKey())
                ->whereIn('type', self::INVOICE_TYPES)
                ->whereNotIn('status', self::VOID_STATUSES)
                ->whereBetween('issued_on', DateRange::days($prevStart, $end))
                ->toBase()
                ->selectRaw("{$yearExpr} as y, {$monthExpr} as m, COALESCE(SUM(total), 0) as amount")
                ->groupBy('y', 'm')
                ->get();
            foreach ($invoiceRows as $row) {
                $ym = sprintf('%04d-%02d', (int) $row->y, (int) $row->m);
                if (isset($revenue[$ym])) {
                    $revenue[$ym] += (float) $row->amount;
                } elseif (isset($prevRevenue[$ym])) {
                    $prevRevenue[$ym] += (float) $row->amount;
                }
            }
        }

        // Materialkosten je Monat (nach allocated_on) für die Umsatz-Gegenüberstellung.
        $material = array_fill_keys(array_keys($months), 0.0);
        [$yearExpr, $monthExpr] = $this->yearMonthExprs('allocated_on');
        /** @var iterable<int, object{y: int|string, m: int|string, amount: float|int|string}> $materialRows */
        $materialRows = $customer->materialCostAllocations()
            ->whereBetween('allocated_on', DateRange::days($start, $end))
            ->toBase()
            ->selectRaw("{$yearExpr} as y, {$monthExpr} as m, COALESCE(SUM(allocated_amount), 0) as amount")
            ->groupBy('y', 'm')
            ->get();
        foreach ($materialRows as $row) {
            $ym = sprintf('%04d-%02d', (int) $row->y, (int) $row->m);
            if (isset($material[$ym])) {
                $material[$ym] += (float) $row->amount;
            }
        }
        $hasMaterial = array_sum($material) > 0.0;
        $hasPrevHours = array_sum($prevMinutes) > 0;
        $hasPrevRevenue = array_sum($prevRevenue) > 0.0;

        $hours = [];
        $revenueSeries = [];
        foreach ($months as $ym => $m) {
            $label = $m->isoFormat('MMM YY');
            $prevKey = $prevKeyOf[$ym];
            $hourRow = [
                'x' => $label,
                'billable' => round($billableMinutes[$ym] / 60, 1),
                'nonbillable' => round(max(0, $minutes[$ym] - $billableMinutes[$ym]) / 60, 1),
            ];
            // Vorjahres-Gesamtstunden als Vergleichslinie (nur wenn es Vorjahresdaten gibt).
            if ($hasPrevHours) {
                $hourRow['compare'] = round(($prevMinutes[$prevKey] ?? 0) / 60, 1);
            }
            $hours[] = $hourRow;
            $point = [
                'x' => $label,
                'y' => round($revenue[$ym], 2),
            ];
            // Zweitserie (Materialkosten) nur, wenn überhaupt zugeordnet — sonst
            // keine leere Vergleichsspalte.
            if ($hasMaterial) {
                $point['y2'] = round($material[$ym], 2);
            }
            if ($hasPrevRevenue) {
                $point['compare'] = round($prevRevenue[$prevKey] ?? 0.0, 2);
            }
            $revenueSeries[] = $point;
        }

        return ['hours' => $hours, 'revenue' => $revenueSeries];
    }

    /**
     * Jahr-/Monats-Ausdruck je DB-Treiber — strftime existiert nur in SQLite
     * (Muster wie TimeAccountPostingService::rebuildBalances()).
     *
     * @param  literal-string  $column  fester Spaltenname (nie Nutzereingabe — selectRaw)
     * @return array{0: literal-string, 1: literal-string}
     */
    private function yearMonthExprs(string $column): array {
        $driver = DB::connection()->getDriverName();

        return $driver === 'mysql'
            ? ["YEAR({$column})", "MONTH({$column})"]
            : ["CAST(strftime('%Y', {$column}) AS INTEGER)", "CAST(strftime('%m', {$column}) AS INTEGER)"];
    }
}
