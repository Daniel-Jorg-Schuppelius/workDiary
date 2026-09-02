<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierAnalysisReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\{LexofficeVoucher, PurchaseOrder, Supplier};
use App\Support\Billing\VoucherTypes;
use App\Support\ChartBucket;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Lieferantenanalyse (Feature 002, MVP-472): Ausgaben, Beschaffungsvolumen,
 * offene Verbindlichkeiten, Ausgabenkonzentration (Klumpenrisiko im Einkauf)
 * und Ausgabentrend je Lieferant.
 *
 * Bewusst OHNE Lager-Modul nutzbar: die Ausgaben stammen aus dem
 * Lexoffice-Beleg-Spiegel ({@see LexofficeVoucher} mit `supplier_id`,
 * Einkaufsbeleg-Typen), damit alle Organisationen mit Buchhaltungsanbindung
 * profitieren. Bestell-Kennzahlen (Bestellungen, offene Bestellungen) kommen
 * NUR zusätzlich mit `module.lager` hinzu — der Aufrufer signalisiert das über
 * $withProcurement. Fehlt eine Quelle, bleibt die Kennzahl `null` (nie 0).
 *
 * Alle Quellmodelle sind org-gescopt (Global Scope BelongsToOrganization);
 * die Aggregation läuft daher immer im Mandantenkontext des aktuellen Nutzers.
 */
class SupplierAnalysisReportBuilder {
    /** HHI-Ampelschwellen (Marktkonzentrations-Konvention, wie Kundenwert). */
    public const HHI_MODERATE = 1500;

    public const HHI_HIGH = 2500;

    /** Einkaufsbeleg-Typen im Lexoffice-Spiegel (supplier_id gesetzt). */
    private const EXPENSE_TYPES = VoucherTypes::EXPENSES;

    /** Gutschriften mindern die Ausgaben (negatives Vorzeichen). */
    private const CREDIT_TYPES = VoucherTypes::EXPENSE_CREDITS;

    /** Als „offen" zählende Bestellstatus (aktuell laufend). */
    private const OPEN_ORDER_STATUSES = [
        PurchaseOrderStatus::Draft->value,
        PurchaseOrderStatus::Ordered->value,
        PurchaseOrderStatus::PartiallyReceived->value,
    ];

    /**
     * @return array{
     *   rows: list<array{supplierId:int, supplierName:string, spend:float, voucherCount:int,
     *     avgVoucher:float, openAmount:float, recencyDays:?int, lastVoucher:?string,
     *     spendPrev:float, trendPct:?float, orderCount:?int, openOrderCount:?int}>,
     *   concentration: array{totalSpend:float, top5Share:?float, top10Share:?float, hhi:?int, activeSuppliers:int},
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, bool $withProcurement = false): array {
        $period = $this->voucherAggregates($from, $to);

        // Vergleichszeitraum gleicher Länge unmittelbar davor (Ausgabentrend).
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $prevTo = $from->subDay()->endOfDay();
        $prevFrom = $from->subDays($days)->startOfDay();
        $previous = $this->voucherAggregates($prevFrom, $prevTo);

        $orders = $withProcurement ? $this->orderAggregates($from, $to) : [];
        $openOrders = $withProcurement ? $this->openOrderCounts() : [];

        // Vereinigung aller Lieferanten mit Aktivität (Belege im Zeitraum oder
        // Bestellungen) — reine Stammdaten blähen die Auswertung nicht auf.
        $supplierIds = collect(array_keys($period))
            ->merge(array_keys($orders))
            ->merge(array_keys($openOrders))
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Supplier> $suppliers */
        $suppliers = Supplier::query()
            ->whereIn('id', $supplierIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = [];
        foreach ($suppliers as $supplier) {
            $sid = (int) $supplier->id;
            $agg = $period[$sid] ?? ['spend' => 0.0, 'open' => 0.0, 'count' => 0, 'last' => null];
            $spend = round($agg['spend'], 2);
            $count = $agg['count'];
            $spendPrev = round(($previous[$sid]['spend'] ?? 0.0), 2);
            $lastVoucher = $agg['last'];

            $rows[] = [
                'supplierId' => $sid,
                'supplierName' => (string) $supplier->name,
                'spend' => $spend,
                'voucherCount' => $count,
                'avgVoucher' => $count > 0 ? round($spend / $count, 2) : 0.0,
                'openAmount' => round($agg['open'], 2),
                'recencyDays' => $lastVoucher !== null
                    ? (int) max(0, CarbonImmutable::parse($lastVoucher)->diffInDays($to, false))
                    : null,
                'lastVoucher' => $lastVoucher,
                'spendPrev' => $spendPrev,
                'trendPct' => $spendPrev > 0 ? round(($spend - $spendPrev) / $spendPrev * 100, 1) : null,
                'orderCount' => $withProcurement ? (int) ($orders[$sid] ?? 0) : null,
                'openOrderCount' => $withProcurement ? (int) ($openOrders[$sid] ?? 0) : null,
            ];
        }

        return [
            'rows' => $rows,
            'concentration' => $this->concentration($rows),
        ];
    }

    /**
     * Adaptive Zeitachse für die Trend-Charts: Granularität aus der Header-
     * Einheit ({@see ChartBucket}); 'hour' wird auf 'day' reduziert, da
     * Belege datumsgenau sind.
     *
     * @return array{0: 'day'|'week'|'month'|'quarter', 1: list<array{key: string, label: string}>}
     */
    private function spendAxis(CarbonImmutable $from, CarbonImmutable $to, string $unit): array {
        $granularity = ChartBucket::granularity($unit, $from, $to);
        if ($granularity === 'hour') {
            $granularity = 'day';
        }
        /** @var array<string, array{key: string, label: string}> $buckets */
        $buckets = [];
        for ($cursor = $from->startOfDay(); $cursor->lte($to); $cursor = $cursor->addDay()) {
            [$key, $label] = ChartBucket::keyLabel($granularity, $cursor);
            $buckets[$key] ??= ['key' => $key, 'label' => $label];
        }

        return [$granularity, array_values($buckets)];
    }

    /**
     * Gesamtausgaben (org-weit) im Zeitraum, in der Header-Granularität —
     * Datenreihe des Ausgabentrend-Charts.
     *
     * @return list<array{x: string, y: float}>
     */
    public function monthlySpendSeries(CarbonImmutable $from, CarbonImmutable $to, string $unit): array {
        [$granularity, $buckets] = $this->spendAxis($from, $to, $unit);
        /** @var array<string, float> $sums */
        $sums = array_fill_keys(array_column($buckets, 'key'), 0.0);

        LexofficeVoucher::query()
            ->whereNotNull('supplier_id')
            ->where('archived', false)
            ->whereNotNull('voucher_date')
            ->whereBetween('voucher_date', DateRange::days($from, $to))
            ->whereIn('voucher_type', self::EXPENSE_TYPES)
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->get(['voucher_type', 'voucher_date', 'total_amount'])
            ->each(function (LexofficeVoucher $voucher) use (&$sums, $granularity): void {
                if ($voucher->voucher_date === null) {
                    return;
                }
                $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse($voucher->voucher_date->toDateString()))[0];
                if (array_key_exists($key, $sums)) {
                    $sums[$key] += $this->signedAmount($voucher);
                }
            });

        $series = [];
        foreach ($buckets as $bucket) {
            $series[] = ['x' => $bucket['label'], 'y' => round($sums[$bucket['key']], 2)];
        }

        return $series;
    }

    /**
     * Ausgaben EINES Lieferanten im Zeitraum, in der Header-Granularität —
     * Datenreihe für das Ausgaben-Diagramm der Lieferanten-Detailseite.
     *
     * @return list<array{x: string, y: float}>
     */
    public function supplierMonthlySpendSeries(int $supplierId, CarbonImmutable $from, CarbonImmutable $to, string $unit): array {
        [$granularity, $buckets] = $this->spendAxis($from, $to, $unit);
        /** @var array<string, float> $sums */
        $sums = array_fill_keys(array_column($buckets, 'key'), 0.0);

        LexofficeVoucher::query()
            ->where('supplier_id', $supplierId)
            ->where('archived', false)
            ->whereNotNull('voucher_date')
            ->whereBetween('voucher_date', DateRange::days($from, $to))
            ->whereIn('voucher_type', self::EXPENSE_TYPES)
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->get(['voucher_type', 'voucher_date', 'total_amount'])
            ->each(function (LexofficeVoucher $voucher) use (&$sums, $granularity): void {
                if ($voucher->voucher_date === null) {
                    return;
                }
                $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse($voucher->voucher_date->toDateString()))[0];
                if (array_key_exists($key, $sums)) {
                    $sums[$key] += $this->signedAmount($voucher);
                }
            });

        $series = [];
        foreach ($buckets as $bucket) {
            $series[] = ['x' => $bucket['label'], 'y' => round($sums[$bucket['key']], 2)];
        }

        return $series;
    }

    /**
     * Belegzahl EINES Lieferanten im Zeitraum, in der Header-Granularität —
     * Gegenstück zum Ausgaben-Diagramm der Lieferanten-Detailseite.
     *
     * @return list<array{x: string, y: int}>
     */
    public function supplierMonthlyVoucherCountSeries(int $supplierId, CarbonImmutable $from, CarbonImmutable $to, string $unit): array {
        [$granularity, $buckets] = $this->spendAxis($from, $to, $unit);
        /** @var array<string, int> $counts */
        $counts = array_fill_keys(array_column($buckets, 'key'), 0);

        LexofficeVoucher::query()
            ->where('supplier_id', $supplierId)
            ->where('archived', false)
            ->whereNotNull('voucher_date')
            ->whereBetween('voucher_date', DateRange::days($from, $to))
            ->whereIn('voucher_type', self::EXPENSE_TYPES)
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->get(['voucher_date'])
            ->each(function (LexofficeVoucher $voucher) use (&$counts, $granularity): void {
                if ($voucher->voucher_date === null) {
                    return;
                }
                $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse($voucher->voucher_date->toDateString()))[0];
                if (array_key_exists($key, $counts)) {
                    $counts[$key]++;
                }
            });

        $series = [];
        foreach ($buckets as $bucket) {
            $series[] = ['x' => $bucket['label'], 'y' => $counts[$bucket['key']]];
        }

        return $series;
    }

    /**
     * Ausgaben-Aggregate je Lieferant im Zeitraum (Beleg-Spiegel).
     *
     * @return array<int, array{spend:float, open:float, count:int, last:?string}>
     */
    private function voucherAggregates(CarbonImmutable $from, CarbonImmutable $to): array {
        /** @var array<int, array{spend:float, open:float, count:int, last:?string}> $agg */
        $agg = [];

        LexofficeVoucher::query()
            ->whereNotNull('supplier_id')
            ->where('archived', false)
            ->whereNotNull('voucher_date')
            ->whereBetween('voucher_date', DateRange::days($from, $to))
            ->whereIn('voucher_type', self::EXPENSE_TYPES)
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->get(['supplier_id', 'voucher_type', 'voucher_status', 'voucher_date', 'total_amount', 'open_amount'])
            ->each(function (LexofficeVoucher $voucher) use (&$agg): void {
                $sid = (int) $voucher->supplier_id;
                $date = $voucher->voucher_date?->toDateString();
                $agg[$sid] ??= ['spend' => 0.0, 'open' => 0.0, 'count' => 0, 'last' => null];
                $agg[$sid]['spend'] += $this->signedAmount($voucher);
                $agg[$sid]['open'] += $voucher->open_amount?->toFloat() ?? 0.0;
                $agg[$sid]['count']++;
                if ($date !== null && ($agg[$sid]['last'] === null || $date > $agg[$sid]['last'])) {
                    $agg[$sid]['last'] = $date;
                }
            });

        return $agg;
    }

    /** Vorzeichenbehafteter Belegbetrag (Gutschriften negativ). */
    private function signedAmount(LexofficeVoucher $voucher): float {
        $sign = in_array($voucher->voucher_type, self::CREDIT_TYPES, true) ? -1.0 : 1.0;

        return $sign * ($voucher->total_amount?->toFloat() ?? 0.0);
    }

    /**
     * Bestellungen je Lieferant, die im Zeitraum ausgelöst wurden (ordered_at).
     *
     * @return array<int, int>
     */
    private function orderAggregates(CarbonImmutable $from, CarbonImmutable $to): array {
        return PurchaseOrder::query()
            ->whereBetween('ordered_at', [$from, $to])
            ->selectRaw('supplier_id, COUNT(*) AS cnt')
            ->groupBy('supplier_id')
            ->get()
            ->mapWithKeys(static fn($row): array => [(int) $row->getAttribute('supplier_id') => (int) $row->getAttribute('cnt')])
            ->all();
    }

    /**
     * Aktuell offene Bestellungen je Lieferant (unabhängig vom Zeitraum —
     * „offen" ist ein Bestandsbegriff, kein Periodenwert).
     *
     * @return array<int, int>
     */
    private function openOrderCounts(): array {
        return PurchaseOrder::query()
            ->whereIn('status', self::OPEN_ORDER_STATUSES)
            ->selectRaw('supplier_id, COUNT(*) AS cnt')
            ->groupBy('supplier_id')
            ->get()
            ->mapWithKeys(static fn($row): array => [(int) $row->getAttribute('supplier_id') => (int) $row->getAttribute('cnt')])
            ->all();
    }

    /**
     * Ausgabenkonzentration (Klumpenrisiko im Einkauf): Top-N-Anteil und
     * Herfindahl-Hirschman-Index über die Ausgaben je Lieferant.
     *
     * @param  list<array{supplierId:int, supplierName:string, spend:float, voucherCount:int, avgVoucher:float, openAmount:float, recencyDays:?int, lastVoucher:?string, spendPrev:float, trendPct:?float, orderCount:?int, openOrderCount:?int}>  $rows
     * @return array{totalSpend:float, top5Share:?float, top10Share:?float, hhi:?int, activeSuppliers:int}
     */
    private function concentration(array $rows): array {
        $spends = collect($rows)->pluck('spend')->filter(static fn(float $v): bool => $v > 0)->sortDesc()->values();
        $total = (float) $spends->sum();
        $share = fn(Collection $part): ?float => $total > 0 ? round((float) $part->sum() / $total * 100, 1) : null;

        $hhi = null;
        if ($total > 0) {
            $hhi = (int) round($spends->reduce(static fn(float $carry, float $v): float => $carry + (($v / $total * 100) ** 2), 0.0));
        }

        return [
            'totalSpend' => round($total, 2),
            'top5Share' => $share($spends->take(5)),
            'top10Share' => $share($spends->take(10)),
            'hhi' => $hhi,
            'activeSuppliers' => $spends->count(),
        ];
    }
}
