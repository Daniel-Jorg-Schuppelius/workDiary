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
use App\Models\Article\ArticleSupply;
use App\Models\Material\MaterialUsage;
use App\Models\Plugins\Lexoffice\{LexofficePostingCategory, LexofficeVoucher, LexofficeVoucherCategory};
use App\Models\Procurement\PurchaseOrder;
use App\Models\Supplier\Supplier;
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
     * Ausgabenbrücke Vorperiode → Zeitraum (MVP-888): die größten Veränderungen
     * je Lieferant als Schritte, der Rest als „Übrige“. Start + Schritte = Ende.
     *
     * @param  list<array{supplierId:int, supplierName:string, spend:float, spendPrev:float}>  $rows
     * @return array{start: float, end: float, steps: list<array{x: string, y: float, supplierId: ?int}>}
     */
    public function spendBridge(array $rows, int $top = 5): array {
        $deltas = array_map(static fn (array $r): array => ['x' => $r['supplierName'], 'y' => round($r['spend'] - $r['spendPrev'], 2), 'supplierId' => $r['supplierId']], $rows);
        $deltas = array_values(array_filter($deltas, static fn (array $d): bool => $d['y'] != 0.0));
        usort($deltas, static fn (array $a, array $b): int => abs($b['y']) <=> abs($a['y']));

        $steps = array_slice($deltas, 0, $top);
        $rest = round(array_sum(array_column(array_slice($deltas, $top), 'y')), 2);
        if ($rest != 0.0) {
            $steps[] = ['x' => (string) __('reporting.supplier_bridge.others'), 'y' => $rest, 'supplierId' => null];
        }

        return [
            'start' => round(array_sum(array_column($rows, 'spendPrev')), 2),
            'end' => round(array_sum(array_column($rows, 'spend')), 2),
            'steps' => $steps,
        ];
    }

    /**
     * Materialverbrauch je Lieferant (MVP-904): Verbrauch aus den Stundenzetteln
     * im Zeitraum, über das verknüpfte Material → Artikel → bevorzugte
     * Lieferquelle. Verbrauch ohne Artikelbezug zählt nur in `unlinkedValue`.
     *
     * @return array{rows: list<array{supplierId: int, supplierName: string, materials: int, usages: int, value: float, quantities: array<string, float>}>, unlinkedValue: float}
     */
    public function materialUsageBySupplier(CarbonImmutable $from, CarbonImmutable $to): array {
        $usages = MaterialUsage::query()
            ->with('material:id,article_id')
            ->whereHas('timesheet', fn ($q) => $q->where('work_date', '>=', $from->toDateString())->where('work_date', '<', DateRange::dayAfter($to)))
            ->get();

        $articleIds = $usages->map(fn (MaterialUsage $u): ?int => $u->material?->article_id)->filter()->unique()->values()->all();
        $supplierByArticle = ArticleSupply::query()
            ->whereIn('article_id', $articleIds)
            ->orderByDesc('is_preferred')
            ->orderBy('id')
            ->get(['article_id', 'supplier_id'])
            ->unique('article_id')
            ->pluck('supplier_id', 'article_id')
            ->all();
        $names = Supplier::query()->whereIn('id', array_values($supplierByArticle))->pluck('name', 'id')->all();

        $rows = [];
        $unlinked = 0.0;
        foreach ($usages as $usage) {
            $value = $usage->line_total_net?->toFloat() ?? 0.0;
            $supplierId = $supplierByArticle[$usage->material->article_id ?? 0] ?? null;
            if ($supplierId === null) {
                $unlinked += $value;

                continue;
            }
            $row = $rows[$supplierId] ?? ['supplierId' => (int) $supplierId, 'supplierName' => (string) ($names[$supplierId] ?? '—'), 'materials' => [], 'usages' => 0, 'value' => 0.0, 'quantities' => []];
            $row['materials'][(int) $usage->material_id] = true;
            $row['usages']++;
            $row['value'] += $value;
            $unit = (string) ($usage->unit ?? '');
            $row['quantities'][$unit] = ($row['quantities'][$unit] ?? 0.0) + ($usage->quantity?->getValue()->toFloat() ?? 0.0);
            $rows[$supplierId] = $row;
        }

        $rows = array_map(static fn (array $row): array => ['materials' => count($row['materials'])] + $row, array_values($rows));
        usort($rows, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return ['rows' => $rows, 'unlinkedValue' => round($unlinked, 2)];
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
     * Ausgaben je Buchungskategorie (MVP-905) aus den Kategoriezeilen der
     * Einkaufsbelege, netto, Gutschriften mindernd. Die vier größten
     * Kategorien als eigene Bänder, der Rest als „Übrige“ (Diagramm trägt
     * höchstens fünf Bänder). `pending` = Belege ohne geladene Kategorien.
     *
     * @return array{series: list<array<string, float|string>>, bands: list<array{key: string, label: string}>, pending: int}
     */
    public function spendByCategorySeries(CarbonImmutable $from, CarbonImmutable $to, string $unit): array {
        [$granularity, $buckets] = $this->spendAxis($from, $to, $unit);
        $vouchers = LexofficeVoucher::query()
            ->whereNotNull('supplier_id')
            ->where('archived', false)
            ->whereNotNull('voucher_date')
            ->whereBetween('voucher_date', DateRange::days($from, $to))
            ->whereIn('voucher_type', self::EXPENSE_TYPES)
            ->whereNotIn('voucher_status', ['draft', 'voided']);
        $pending = (clone $vouchers)->whereNull('categories_synced_at')->count();

        $names = LexofficePostingCategory::query()->pluck('name', 'external_id')->all();
        $cells = [];
        $totals = [];
        LexofficeVoucherCategory::query()
            ->with('voucher:id,voucher_type,voucher_date')
            ->whereIn('voucher_id', (clone $vouchers)->select('id'))
            ->get()
            ->each(function (LexofficeVoucherCategory $row) use (&$cells, &$totals, $names, $granularity): void {
                $date = $row->voucher->voucher_date;
                if ($date === null) {
                    return;
                }
                $category = (string) ($names[(string) $row->category_external_id] ?? __('reporting.supplier_category.unknown'));
                $amount = $row->net_amount->toFloat() * (in_array($row->voucher->voucher_type, self::CREDIT_TYPES, true) ? -1 : 1);
                $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse($date->toDateString()))[0];
                $cells[$key][$category] = ($cells[$key][$category] ?? 0.0) + $amount;
                $totals[$category] = ($totals[$category] ?? 0.0) + $amount;
            });

        arsort($totals);
        $top = array_slice(array_keys($totals), 0, 4);
        $bands = [];
        foreach ($top as $index => $category) {
            $bands[] = ['key' => 'c' . $index, 'label' => $category];
        }
        if (count($totals) > count($top)) {
            $bands[] = ['key' => 'rest', 'label' => (string) __('reporting.supplier_category.rest')];
        }

        $series = [];
        foreach ($buckets as $bucket) {
            $point = ['x' => $bucket['label']];
            foreach ($bands as $band) {
                $point[$band['key']] = 0.0;
            }
            foreach ($cells[$bucket['key']] ?? [] as $category => $amount) {
                $index = array_search($category, $top, true);
                $key = $index === false ? 'rest' : 'c' . $index;
                $point[$key] = round((float) $point[$key] + $amount, 2);
            }
            $series[] = $point;
        }

        return ['series' => $totals === [] ? [] : $series, 'bands' => $bands, 'pending' => $pending];
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
