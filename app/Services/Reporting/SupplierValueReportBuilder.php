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

use App\Models\{LexofficeVoucher, Supplier};
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
 * Ausgaben = Lexoffice-Beleg-Spiegel (Einkaufsbelege je Lieferant,
 * Gutschriften negativ) — dieselbe Quelle wie {@see SupplierAnalysisReportBuilder},
 * ohne Lager-Modul nutzbar. Erst-/Letztbeleg werden org-weit und UNGEFILTERT
 * bestimmt (Lieferantenfakten); nur die Zeitraum-Kennzahlen folgen dem Filter.
 */
class SupplierValueReportBuilder {
    /** HHI-Ampelschwellen (Marktkonzentrations-Konvention, wie Kundenwert). */
    public const HHI_MODERATE = 1500;

    public const HHI_HIGH = 2500;

    /** Einkaufsbeleg-Typen im Lexoffice-Spiegel (supplier_id gesetzt). */
    private const EXPENSE_TYPES = ['purchaseinvoice', 'purchasecreditnote', 'voucher'];

    /** Gutschriften mindern die Ausgaben (negatives Vorzeichen). */
    private const CREDIT_TYPES = ['purchasecreditnote'];

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
        $rScores = $this->quintileScores(
            $active->mapWithKeys(fn(Supplier $s): array => [(int) $s->id => (float) ($recency[(int) $s->id] ?? 0)])->all(),
            higherIsBetter: false,
        );
        $fScores = $this->quintileScores(
            $active->mapWithKeys(fn(Supplier $s): array => [(int) $s->id => (float) ($voucherDays[(int) $s->id] ?? 0)])->all(),
            higherIsBetter: true,
        );
        $mScores = $this->quintileScores(
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
            $segment = $this->segment($freq, $r, $f, $m, $firstActivity[$sid] ?? null, $from);
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
     * Monatliche Ausgaben der letzten zwölf Monate je Lieferant —
     * Sparkline-Reihe der Risikoliste.
     *
     * @param  list<int>  $supplierIds
     * @return array<int, list<float>> supplierId → 12 Monatswerte (alt → neu)
     */
    public function monthlySpendSeries(array $supplierIds, CarbonImmutable $to): array {
        if ($supplierIds === []) {
            return [];
        }

        $start = $to->subMonthsNoOverflow(11)->startOfMonth();
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $months[] = $start->addMonthsNoOverflow($i)->format('Y-m');
        }

        $bySupplier = array_fill_keys($supplierIds, array_fill_keys($months, 0.0));
        LexofficeVoucher::query()
            ->whereIn('supplier_id', $supplierIds)
            ->where('archived', false)
            ->whereNotNull('voucher_date')
            ->whereBetween('voucher_date', [$start->toDateString(), $to->toDateString()])
            ->whereIn('voucher_type', self::EXPENSE_TYPES)
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->get(['supplier_id', 'voucher_type', 'voucher_date', 'total_amount'])
            ->each(function (LexofficeVoucher $voucher) use (&$bySupplier): void {
                $sid = (int) $voucher->supplier_id;
                $month = $voucher->voucher_date?->format('Y-m');
                if ($month !== null && isset($bySupplier[$sid][$month])) {
                    $bySupplier[$sid][$month] += $this->signedAmount($voucher);
                }
            });

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

        LexofficeVoucher::query()
            ->whereNotNull('supplier_id')
            ->where('archived', false)
            ->whereNotNull('voucher_date')
            ->whereBetween('voucher_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('voucher_type', self::EXPENSE_TYPES)
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->get(['supplier_id', 'voucher_type', 'voucher_date', 'total_amount'])
            ->each(function (LexofficeVoucher $voucher) use (&$spend, &$days, &$count, &$last): void {
                $sid = (int) $voucher->supplier_id;
                $date = $voucher->voucher_date?->toDateString();
                $spend[$sid] = ($spend[$sid] ?? 0.0) + $this->signedAmount($voucher);
                $count[$sid] = ($count[$sid] ?? 0) + 1;
                if ($date !== null) {
                    $days[$sid][$date] = true;
                    if (! isset($last[$sid]) || $date > $last[$sid]) {
                        $last[$sid] = $date;
                    }
                }
            });

        $voucherDays = array_map(static fn(array $set): int => count($set), $days);

        return [$spend, $voucherDays, $count, $last];
    }

    /**
     * Erst-/Letztbeleg je Lieferant (org-weit, ungefiltert): MIN/MAX über
     * `voucher_date` der Einkaufsbelege.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function activityBounds(): array {
        $first = [];
        $last = [];

        LexofficeVoucher::query()
            ->whereNotNull('supplier_id')
            ->where('archived', false)
            ->whereNotNull('voucher_date')
            ->whereIn('voucher_type', self::EXPENSE_TYPES)
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, MIN(voucher_date) AS first_date, MAX(voucher_date) AS last_date')
            ->get()
            ->each(function ($row) use (&$first, &$last): void {
                $sid = (int) $row->getAttribute('supplier_id');
                $first[$sid] = substr((string) $row->getAttribute('first_date'), 0, 10);
                $last[$sid] = substr((string) $row->getAttribute('last_date'), 0, 10);
            });

        return [$first, $last];
    }

    /** Vorzeichenbehafteter Belegbetrag (Gutschriften negativ). */
    private function signedAmount(LexofficeVoucher $voucher): float {
        $sign = in_array($voucher->voucher_type, self::CREDIT_TYPES, true) ? -1.0 : 1.0;

        return $sign * ($voucher->total_amount?->toFloat() ?? 0.0);
    }

    /**
     * Quintil-Scores 1–5 über die Perzentil-Position des Werts; gleiche Werte
     * erhalten denselben Score (stabil bei Bindungen).
     *
     * @param  array<int, float>  $values
     * @return array<int, int>
     */
    private function quintileScores(array $values, bool $higherIsBetter): array {
        $n = count($values);
        if ($n === 0) {
            return [];
        }

        $sorted = array_values($values);
        sort($sorted);
        $scores = [];
        foreach ($values as $key => $value) {
            $below = 0;
            foreach ($sorted as $v) {
                if ($v < $value) {
                    $below++;
                } else {
                    break;
                }
            }
            $score = min(5, (int) floor($below / $n * 5) + 1);
            $scores[$key] = $higherIsBetter ? $score : 6 - $score;
        }

        return $scores;
    }

    /** Sequenzielle Segment-Zuordnung (erste zutreffende Regel gewinnt). */
    private function segment(int $frequencyDays, ?int $r, ?int $f, ?int $m, ?string $firstActivity, CarbonImmutable $from): string {
        if ($frequencyDays === 0) {
            return 'dormant';
        }
        if ($firstActivity !== null && $firstActivity >= $from->toDateString()) {
            return 'new';
        }
        if (($r ?? 0) >= 4 && ($f ?? 0) >= 4 && ($m ?? 0) >= 4) {
            return 'strategic';
        }
        if (($r ?? 0) <= 2 && ($m ?? 0) >= 4) {
            return 'lapsed';
        }
        if (($r ?? 0) <= 2) {
            return 'dormant';
        }
        if (($f ?? 0) >= 3) {
            return 'core';
        }

        return 'occasional';
    }

    /**
     * Ausgabenkonzentration (Klumpenrisiko im Einkauf).
     *
     * @param  list<array{supplierId:int, supplierName:string, recencyDays:?int, frequencyDays:int, spend:float, voucherCount:int, spendShare:float, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>  $rows
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
