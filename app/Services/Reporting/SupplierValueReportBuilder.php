<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierValueReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Models\Supplier\Supplier;
use App\Services\Billing\Contracts\ExternalPurchases;
use App\Services\Reporting\Support\ReportStatistics;
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Lieferantenwert & Portfolio (Feature 002, MVP-473): das Einkaufs-Pendant zum
 * Kundenwert ({@see CustomerValueReportBuilder}). RFM-Segmentierung auf der
 * Ausgabenseite (Recency letzter Beleg, Frequency Belegtage, Monetary
 * Ausgaben), Ausgabenkonzentration (Top-N-Anteil, Herfindahl-Hirschman-Index)
 * und Risikoliste stark abhängiger A-Lieferanten (hoher Ausgabenanteil =
 * Single-Source-Klumpenrisiko).
 *
 * Ausgaben = Einkaufsbelege der Buchhaltungsprogramme je Lieferant
 * ({@see ExternalPurchases}, MVP-1036; Gutschriften negativ) — dieselbe Quelle
 * wie {@see SupplierAnalysisReportBuilder},
 * ohne Lager-Modul nutzbar. Erst-/Letztbeleg werden org-weit und UNGEFILTERT
 * bestimmt (Lieferantenfakten); nur die Zeitraum-Kennzahlen folgen dem Filter.
 */
class SupplierValueReportBuilder {
    /** Segmentnamen dieses Reports zu den Stufen von {@see ReportStatistics::rfmSegment()}. */
    private const SEGMENTS = ['inactive' => 'dormant', 'new' => 'new', 'top' => 'strategic', 'lapsed' => 'lapsed', 'regular' => 'core', 'occasional' => 'occasional'];

    /** HHI-Ampelschwellen (Marktkonzentrations-Konvention, wie Kundenwert). */
    public const HHI_MODERATE = 1500;

    public const HHI_HIGH = 2500;

    public function __construct(private readonly ExternalPurchases $purchases) {}

    /**
     * @return array{
     *   rows: list<array{supplierId:int, supplierName:string, recencyDays:?int, frequencyDays:int,
     *     spend:float, voucherCount:int, spendShare:float, r:?int, f:?int, m:?int, segment:string,
     *     firstActivity:?string, lastActivity:?string}>,
     *   segments: array<string, int>,
     *   concentration: array{totalSpend:float, top5Share:?float, top10Share:?float, hhi:?int, activeSuppliers:int},
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array {
        [$spend, $voucherDays, $voucherCount, $lastInPeriod] = $this->periodAggregates($from, $to);
        [$firstActivity, $lastActivity] = $this->activityBounds();

        $supplierIds = collect(array_keys($spend))
            ->merge(array_keys($firstActivity))
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Supplier> $suppliers */
        $suppliers = Supplier::query()
            ->whereIn('id', $supplierIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Recency org-weit (letzter Beleg überhaupt), relativ zum Zeitraumende.
        $recency = [];
        foreach ($suppliers as $s) {
            $sid = (int) $s->id;
            $last = $lastActivity[$sid] ?? null;
            $recency[$sid] = $last !== null ? (int) max(0, CarbonImmutable::parse($last)->diffInDays($to, false)) : null;
        }

        // RFM-Quintile über die im Zeitraum aktiven Lieferanten.
        $active = $suppliers->filter(fn(Supplier $s): bool => ($voucherDays[(int) $s->id] ?? 0) > 0);
        $rScores = ReportStatistics::quintileScores(
            $active->mapWithKeys(fn(Supplier $s): array => [(int) $s->id => (float) ($recency[(int) $s->id] ?? 0)])->all(),
            higherIsBetter: false,
        );
        $fScores = ReportStatistics::quintileScores(
            $active->mapWithKeys(fn(Supplier $s): array => [(int) $s->id => (float) ($voucherDays[(int) $s->id] ?? 0)])->all(),
            higherIsBetter: true,
        );
        $mScores = ReportStatistics::quintileScores(
            $active->mapWithKeys(fn(Supplier $s): array => [(int) $s->id => (float) ($spend[(int) $s->id] ?? 0.0)])->all(),
            higherIsBetter: true,
        );

        $totalSpend = (float) collect($spend)->filter(static fn(float $v): bool => $v > 0)->sum();

        $rows = [];
        $segments = ['strategic' => 0, 'core' => 0, 'occasional' => 0, 'new' => 0, 'lapsed' => 0, 'dormant' => 0];
        foreach ($suppliers as $s) {
            $sid = (int) $s->id;
            $freq = (int) ($voucherDays[$sid] ?? 0);
            $sp = round($spend[$sid] ?? 0.0, 2);
            $r = $rScores[$sid] ?? null;
            $f = $fScores[$sid] ?? null;
            $m = $mScores[$sid] ?? null;
            $first = $firstActivity[$sid] ?? null;
            $segment = ReportStatistics::rfmSegment($freq !== 0, $first !== null && $first >= $from->toDateString(), $r, $f, $m, self::SEGMENTS);
            $segments[$segment]++;

            $rows[] = [
                'supplierId' => $sid,
                'supplierName' => (string) $s->name,
                'recencyDays' => $recency[$sid],
                'frequencyDays' => $freq,
                'spend' => $sp,
                'voucherCount' => (int) ($voucherCount[$sid] ?? 0),
                'spendShare' => $totalSpend > 0 && $sp > 0 ? round($sp / $totalSpend * 100, 1) : 0.0,
                'r' => $r,
                'f' => $f,
                'm' => $m,
                'segment' => $segment,
                'firstActivity' => $firstActivity[$sid] ?? null,
                'lastActivity' => $lastActivity[$sid] ?? null,
            ];
        }

        return [
            'rows' => $rows,
            'segments' => $segments,
            'concentration' => $this->concentration($rows),
        ];
    }

    /**
     * Stark abhängige A-Lieferanten: Ausgabenanteil ≥ $riskShare Prozent —
     * Single-Source-Klumpenrisiko, absteigend nach Ausgaben.
     *
     * @param  list<array{supplierId:int, supplierName:string, recencyDays:?int, frequencyDays:int, spend:float, voucherCount:int, spendShare:float, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>  $rows
     * @return list<array{supplierId:int, supplierName:string, recencyDays:?int, frequencyDays:int, spend:float, voucherCount:int, spendShare:float, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>
     */
    public function riskRows(array $rows, float $riskShare = 15.0, int $limit = 10): array {
        return array_slice(array_values(array_filter(
            $rows,
            static fn(array $row): bool => $row['spendShare'] >= $riskShare,
        )), 0, $limit);
    }

    /**
     * Ausgaben je Lieferant im Zeitraum, in der Header-Granularität —
     * Sparkline-Reihe der Risikoliste.
     *
     * @param  list<int>  $supplierIds
     * @return array<int, list<float>> supplierId → Werte je Bucket (alt → neu)
     */
    public function monthlySpendSeries(array $supplierIds, CarbonImmutable $from, CarbonImmutable $to, string $unit): array {
        if ($supplierIds === []) {
            return [];
        }

        $granularity = ChartBucket::granularity($unit, $from, $to);
        if ($granularity === 'hour') {
            $granularity = 'day';
        }
        /** @var list<string> $bucketKeys */
        $bucketKeys = [];
        for ($cursor = $from->startOfDay(); $cursor->lte($to); $cursor = $cursor->addDay()) {
            $key = ChartBucket::keyLabel($granularity, $cursor)[0];
            if (! in_array($key, $bucketKeys, true)) {
                $bucketKeys[] = $key;
            }
        }

        $bySupplier = array_fill_keys($supplierIds, array_fill_keys($bucketKeys, 0.0));
        foreach ($this->purchases->purchases($from, $to, $supplierIds) as $purchase) {
            $key = ChartBucket::keyLabel($granularity, $purchase->date)[0];
            if (isset($bySupplier[$purchase->supplierId][$key])) {
                $bySupplier[$purchase->supplierId][$key] += $purchase->amount;
            }
        }

        return array_map(
            static fn(array $series): array => array_map(static fn(float $v): float => round($v, 2), array_values($series)),
            $bySupplier,
        );
    }

    /**
     * Ausgaben, Belegtage und Belegzahl je Lieferant im Zeitraum.
     *
     * @return array{0: array<int, float>, 1: array<int, int>, 2: array<int, int>, 3: array<int, string>}
     */
    private function periodAggregates(CarbonImmutable $from, CarbonImmutable $to): array {
        /** @var array<int, float> $spend */
        $spend = [];
        /** @var array<int, array<string, true>> $days */
        $days = [];
        /** @var array<int, int> $count */
        $count = [];
        /** @var array<int, string> $last */
        $last = [];

        foreach ($this->purchases->purchases($from, $to) as $purchase) {
            $sid = $purchase->supplierId;
            $date = $purchase->date->toDateString();
            $spend[$sid] = ($spend[$sid] ?? 0.0) + $purchase->amount;
            $count[$sid] = ($count[$sid] ?? 0) + 1;
            $days[$sid][$date] = true;
            if (! isset($last[$sid]) || $date > $last[$sid]) {
                $last[$sid] = $date;
            }
        }

        $voucherDays = array_map(static fn(array $set): int => count($set), $days);

        return [$spend, $voucherDays, $count, $last];
    }

    /**
     * Erst-/Letztbeleg je Lieferant (org-weit, ungefiltert) über das
     * Belegdatum aller Einkaufsbelege.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function activityBounds(): array {
        $first = [];
        $last = [];
        foreach ($this->purchases->purchases(null, null) as $purchase) {
            $sid = $purchase->supplierId;
            $date = $purchase->date->toDateString();
            if (! isset($first[$sid]) || $date < $first[$sid]) {
                $first[$sid] = $date;
            }
            if (! isset($last[$sid]) || $date > $last[$sid]) {
                $last[$sid] = $date;
            }
        }

        return [$first, $last];
    }

    /** Sequenzielle Segment-Zuordnung (erste zutreffende Regel gewinnt). */
    /**
     * Ausgabenkonzentration (Klumpenrisiko im Einkauf).
     *
     * @param  list<array{supplierId:int, supplierName:string, recencyDays:?int, frequencyDays:int, spend:float, voucherCount:int, spendShare:float, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>  $rows
     * @return array{totalSpend:float, top5Share:?float, top10Share:?float, hhi:?int, activeSuppliers:int}
     */
    private function concentration(array $rows): array {
        $concentration = ReportStatistics::concentration(array_column($rows, 'spend'));

        return [
            'totalSpend' => $concentration['total'],
            'top5Share' => $concentration['top5Share'],
            'top10Share' => $concentration['top10Share'],
            'hhi' => $concentration['hhi'],
            'activeSuppliers' => $concentration['positive'],
        ];
    }
}
