<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierScorecardService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Claims\ClaimRecourseStatus;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Isms\IncidentSeverity;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Claims\{ClaimCase, ClaimFinancialOutcome, ClaimSupplierRecourse};
use App\Models\Inventory\StockMovement;
use App\Models\Isms\IsmsSupplierAssessment;
use App\Models\Procurement\{PurchaseOrder, PurchaseOrderLine};
use App\Models\Supplier\Supplier;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Collection;

/**
 * Lieferantenperformance-Scorecards (Bauturbo Welle D): aggregiert je Lieferant
 * über einen Zeitraum die verteilt vorhandenen Datenpunkte aus Einkauf/Lager
 * (Termintreue, Preisentwicklung), Reklamation (Feature 072) und ISMS-
 * Lieferantenbewertung (Feature 044) zu einer transparent gewichteten
 * Gesamtbewertung mit versionierter Kennzahldefinition ({@see self::METRIC_VERSION}).
 *
 * Grundsatz „nur aus vorhandenen Daten": fehlt für eine Kennzahl die Quelle
 * (keine bewerteten Wareneingänge, keine Bestellungen, keine Preishistorie,
 * keine ISMS-Bewertung), wird die Kennzahl als „keine Daten" (available=false,
 * goodness=null) ausgewiesen und NICHT mit 0 in den Gesamt-Score gerechnet —
 * die Gewichte werden über die tatsächlich verfügbaren Kennzahlen
 * re-normalisiert.
 *
 * Alle Quellmodelle sind org-gescopt (Global Scope BelongsToOrganization);
 * die Aggregation läuft daher immer im Mandantenkontext des aktuellen Nutzers.
 */
class SupplierScorecardService {
    /** Formeländerungen erhöhen die Version (Nachweis/Reproduzierbarkeit). */
    public const METRIC_VERSION = 2;

    /**
     * Dokumentierte Standardgewichte des Gesamt-Scores. Konfigurierbar je
     * Organisation über Settings (`scorecard.weight.*`); Summe ist nicht
     * bindend, da über die verfügbaren Kennzahlen re-normalisiert wird.
     *
     * @var array<string, float>
     */
    public const DEFAULT_WEIGHTS = [
        'ontime' => 0.35,
        'complaints' => 0.30,
        'quality' => 0.20,
        'price' => 0.15,
        // MVP-886/887: Regressverhalten (Anerkennung, Antwortfrist, Rückfluss).
        'recourse' => 0.15,
    ];

    /** Als „ordered" (tatsächlich bestellt) zählende Bestellstatus. */
    private const ORDERED_STATUSES = [
        PurchaseOrderStatus::Ordered->value,
        PurchaseOrderStatus::PartiallyReceived->value,
        PurchaseOrderStatus::Received->value,
    ];

    /**
     * Gewichtungen aus den Settings (mit dokumentierten Defaults).
     *
     * @return array<string, float>
     */
    public function weights(): array {
        $out = [];
        foreach (self::DEFAULT_WEIGHTS as $key => $default) {
            $out[$key] = max(0.0, (float) Setting::get('scorecard.weight.' . $key, $default));
        }

        return $out;
    }

    /**
     * Ranking aller Lieferanten mit Einkaufsbezug, sortiert nach Gesamt-Score
     * (absteigend, „keine Daten" ans Ende). Für die Übersicht ohne Chart-Serien.
     *
     * @return list<array<string, mixed>>
     */
    public function ranking(CarbonImmutable $from, CarbonImmutable $to): array {
        // Nur Lieferanten mit Aktivität (Bestellung, Reklamation oder
        // ISMS-Bewertung) — reine Stammdaten blähen das Ranking nicht auf.
        $supplierIds = collect()
            ->merge(PurchaseOrder::query()->distinct()->pluck('supplier_id'))
            ->merge(ClaimCase::query()->whereNotNull('supplier_id')->distinct()->pluck('supplier_id'))
            ->merge(ClaimSupplierRecourse::query()->distinct()->pluck('supplier_id'))
            ->merge(IsmsSupplierAssessment::query()->whereNotNull('supplier_id')->distinct()->pluck('supplier_id'))
            ->filter()
            ->map(static fn($v): int => (int) $v)
            ->unique()
            ->all();

        $suppliers = Supplier::query()->whereIn('id', $supplierIds)->get();

        $rows = $suppliers
            ->map(fn(Supplier $supplier): array => $this->summaryRow($this->scorecard($supplier, $from, $to, withSeries: false)))
            ->all();

        // Sortierung: Score absteigend, null-Scores zuletzt, dann Name.
        usort($rows, static function (array $a, array $b): int {
            $sa = $a['overall'];
            $sb = $b['overall'];
            if ($sa === null && $sb === null) {
                return strcasecmp((string) $a['supplier_name'], (string) $b['supplier_name']);
            }
            if ($sa === null) {
                return 1;
            }
            if ($sb === null) {
                return -1;
            }
            return $sb <=> $sa ?: strcasecmp((string) $a['supplier_name'], (string) $b['supplier_name']);
        });

        return $rows;
    }

    /**
     * Vollständige Scorecard eines Lieferanten (inkl. Chart-Serien, sofern
     * $withSeries).
     *
     * @return array<string, mixed>
     */
    public function scorecard(Supplier $supplier, CarbonImmutable $from, CarbonImmutable $to, bool $withSeries = true): array {
        $ontime = $this->onTimeMetric($supplier, $from, $to, $withSeries);
        $complaints = $this->complaintMetric($supplier, $from, $to, $withSeries);
        $price = $this->priceMetric($supplier, $from, $to, $withSeries);
        $quality = $this->qualityMetric($supplier);
        $recourse = $this->recourseMetric($supplier, $from, $to);
        $leadTime = $this->leadTimeMetric($supplier, $from, $to);

        $goodness = [
            'ontime' => $ontime['goodness'],
            'complaints' => $complaints['goodness'],
            'quality' => $quality['goodness'],
            'price' => $price['goodness'],
            'recourse' => $recourse['goodness'],
        ];

        return [
            'supplier' => $supplier,
            'from' => $from,
            'to' => $to,
            'metric_version' => self::METRIC_VERSION,
            'computed_at' => CarbonImmutable::now(),
            'weights' => $this->weights(),
            'ontime' => $ontime,
            'complaints' => $complaints,
            'price' => $price,
            'quality' => $quality,
            'recourse' => $recourse,
            'lead_time' => $leadTime,
            'overall' => $this->overallScore($goodness),
        ];
    }

    /**
     * Verdichtet eine volle Scorecard auf eine Ranking-Zeile.
     *
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function summaryRow(array $card): array {
        /** @var Supplier $supplier */
        $supplier = $card['supplier'];

        return [
            'supplier' => $supplier,
            'supplier_name' => $supplier->name,
            'overall' => $card['overall'],
            'ontime_rate' => $card['ontime']['rate'],
            'ontime_available' => $card['ontime']['available'],
            'complaint_rate' => $card['complaints']['rate'],
            'complaint_available' => $card['complaints']['available'],
            'price_trend_pct' => $card['price']['trend_pct'],
            'price_direction' => $card['price']['direction'],
            'price_available' => $card['price']['available'],
            'quality_rating' => $card['quality']['rating'],
            'quality_available' => $card['quality']['available'],
            'recourse_acceptance' => $card['recourse']['acceptance_rate'],
            'recourse_available' => $card['recourse']['available'],
            'claim_costs' => $card['recourse']['claim_costs'],
            'lead_time_median' => $card['lead_time']['median'],
            'lead_time_box' => $card['lead_time']['box'],
            'lead_time_count' => $card['lead_time']['count'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Kennzahl 1: Termintreue (Einkauf/Lager)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Anteil pünktlicher Lieferungen: je Bestellung das späteste (= komplette)
     * IST-Wareneingangsdatum aus dem Lagerjournal gegen das zugesagte
     * Lieferdatum (`expected_at`). Ausgewertet werden nur Bestellungen mit
     * zugesagtem Datum UND mindestens einem gebuchten Wareneingang, deren
     * Wareneingang im Zeitraum liegt. Fehlt beides, ist die Kennzahl „keine
     * Daten" (statt fälschlich 0).
     *
     * @return array{rate:?float, evaluated:int, on_time:int, late:int, goodness:?int, available:bool, series:list<array{x:string,y:float,url:null}>}
     */
    private function onTimeMetric(Supplier $supplier, CarbonImmutable $from, CarbonImmutable $to, bool $withSeries): array {
        $orders = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', self::ORDERED_STATUSES)
            ->whereNotNull('expected_at')
            ->get(['id', 'expected_at']);

        $deliveredAt = $this->deliveredAtByOrder($supplier);

        $onTime = 0;
        $late = 0;
        /** @var array<string, array{on:int,total:int}> $monthly */
        $monthly = [];

        foreach ($orders as $order) {
            $delivered = $deliveredAt[(int) $order->id] ?? null;
            if ($delivered === null) {
                continue; // kein Wareneingang gebucht → keine Aussage
            }
            if ($delivered->lt($from) || $delivered->gt($to)) {
                continue; // Lieferung außerhalb des Zeitraums
            }

            $expected = CarbonImmutable::parse((string) $order->expected_at);
            $isOnTime = $delivered->startOfDay()->lte($expected->endOfDay());
            $isOnTime ? $onTime++ : $late++;

            if ($withSeries) {
                $key = $delivered->format('Y-m');
                $monthly[$key] ??= ['on' => 0, 'total' => 0];
                $monthly[$key]['total']++;
                if ($isOnTime) {
                    $monthly[$key]['on']++;
                }
            }
        }

        $evaluated = $onTime + $late;
        $rate = $evaluated > 0 ? $onTime / $evaluated : null;

        $series = [];
        if ($withSeries) {
            ksort($monthly);
            foreach ($monthly as $key => $agg) {
                $series[] = [
                    'x' => $key,
                    'y' => round($agg['on'] / $agg['total'] * 100, 1),
                    'url' => null,
                ];
            }
        }

        return [
            'rate' => $rate,
            'evaluated' => $evaluated,
            'on_time' => $onTime,
            'late' => $late,
            'goodness' => $rate === null ? null : (int) round($rate * 100),
            'available' => $rate !== null,
            'series' => $series,
        ];
    }

    /**
     * Spätestes IST-Wareneingangsdatum je Bestellung des Lieferanten aus dem
     * append-only Lagerjournal: Wareneingänge referenzieren als `source` die
     * {@see PurchaseOrderLine} (siehe GoodsReceiptService). Ein Bestell-
     * abschluss = spätester Wareneingang aller Zeilen.
     *
     * @return array<int, CarbonImmutable>  purchase_order_id => letzter Wareneingang
     */
    public function deliveredAtByOrder(Supplier $supplier): array {
        /** @var Collection<int, int> $lineToOrder line_id => purchase_order_id */
        $lineToOrder = PurchaseOrderLine::query()
            ->whereHas('purchaseOrder', fn($q) => $q->where('supplier_id', $supplier->id))
            ->pluck('purchase_order_id', 'id')
            ->map(static fn($v): int => (int) $v);

        if ($lineToOrder->isEmpty()) {
            return [];
        }

        $lineMorph = (new PurchaseOrderLine())->getMorphClass();

        $movements = StockMovement::query()
            ->where('source_type', $lineMorph)
            ->whereIn('source_id', $lineToOrder->keys())
            ->where('movement_type', StockMovementType::Receipt->value)
            ->get(['source_id', 'occurred_at']);

        $out = [];
        foreach ($movements as $movement) {
            $orderId = $lineToOrder[(int) $movement->source_id] ?? null;
            if ($orderId === null) {
                continue;
            }
            $at = CarbonImmutable::parse((string) $movement->occurred_at);
            if (! isset($out[$orderId]) || $at->gt($out[$orderId])) {
                $out[$orderId] = $at;
            }
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Kennzahl 2: Reklamationsquote (Claims/Feature 072)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Reklamationen mit Lieferantenbezug (ClaimCase.supplier_id, gemeldet im
     * Zeitraum) ÷ Bestellungen des Lieferanten im Zeitraum. Ohne Bestellungen
     * als Basis ist die Quote „keine Daten".
     *
     * @return array{count:int, base:int, rate:?float, goodness:?int, available:bool, series:list<array{x:string,y:float,url:null}>}
     */
    private function complaintMetric(Supplier $supplier, CarbonImmutable $from, CarbonImmutable $to, bool $withSeries): array {
        $claims = ClaimCase::query()
            ->where('supplier_id', $supplier->id)
            ->whereBetween('reported_at', [$from, $to])
            ->get(['id', 'reported_at']);

        $base = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', self::ORDERED_STATUSES)
            ->whereNotNull('ordered_at')
            ->whereBetween('ordered_at', [$from, $to])
            ->count();

        $count = $claims->count();
        $rate = $base > 0 ? $count / $base : null;

        $series = [];
        if ($withSeries && $count > 0) {
            /** @var array<string, int> $monthly */
            $monthly = [];
            foreach ($claims as $claim) {
                $key = CarbonImmutable::parse((string) $claim->reported_at)->format('Y-m');
                $monthly[$key] = ($monthly[$key] ?? 0) + 1;
            }
            ksort($monthly);
            foreach ($monthly as $key => $n) {
                $series[] = ['x' => $key, 'y' => (float) $n, 'url' => null];
            }
        }

        return [
            'count' => $count,
            'base' => $base,
            'rate' => $rate,
            'goodness' => $rate === null ? null : (int) round(max(0.0, min(100.0, 100.0 - $rate * 100.0))),
            'available' => $rate !== null,
            'series' => $series,
        ];
    }

    /**
     * Bestell-Durchlaufzeit (MVP-888): Tage von der Bestellung bis zum letzten
     * Wareneingang, für Bestellungen mit Abschluss im Zeitraum. Nur Anzeige,
     * fließt nicht in den Gesamt-Score ein (Termintreue bewertet bereits).
     *
     * @return array{count:int, median:?float, min:?int, max:?int, box:array{min: float, q1: float, median: float, q3: float, max: float}|null, available:bool}
     */
    private function leadTimeMetric(Supplier $supplier, CarbonImmutable $from, CarbonImmutable $to): array {
        $delivered = array_filter($this->deliveredAtByOrder($supplier), static fn (CarbonImmutable $at): bool => $at->betweenIncluded($from, $to));
        $days = [];
        if ($delivered !== []) {
            foreach (PurchaseOrder::query()->whereIn('id', array_keys($delivered))->whereNotNull('ordered_at')->get(['id', 'ordered_at']) as $order) {
                $days[] = (int) CarbonImmutable::parse((string) $order->ordered_at)->startOfDay()->diffInDays($delivered[(int) $order->id]->startOfDay(), false);
            }
        }

        return [
            'count' => count($days),
            'median' => $days === [] ? null : round(NumberHelper::median($days), 1),
            'min' => $days === [] ? null : min($days),
            'max' => $days === [] ? null : max($days),
            'box' => NumberHelper::quartiles($days),
            'available' => $days !== [],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Kennzahl 2b: Regressverhalten (Reklamationsmodul, MVP-887)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Wie der Lieferant auf Regressforderungen reagiert (im Zeitraum
     * eingereicht): Anerkennungsquote (ganz/teilweise anerkannt bzw. mit
     * Rückfluss abgeschlossen) unter den beantworteten, Fristtreue der
     * Antwort, Rückflussquote (erstattet ÷ gefordert) und mittlere
     * Antwortdauer. Güte = Mittel der verfügbaren Quoten. Dazu die ausgeführten
     * Reklamationskosten der Fälle mit diesem Lieferanten (nur Anzeige).
     *
     * @return array{submitted:int, answered:int, acceptance_rate:?float, response_ontime_rate:?float, recovery_rate:?float, avg_response_days:?float, claim_costs:float, goodness:?int, available:bool}
     */
    private function recourseMetric(Supplier $supplier, CarbonImmutable $from, CarbonImmutable $to): array {
        $recourses = ClaimSupplierRecourse::query()
            ->where('supplier_id', $supplier->id)
            ->where('status', '!=', ClaimRecourseStatus::Draft->value)
            ->whereBetween('submitted_at', [$from, $to])
            ->get();
        $answered = $recourses->filter(static fn (ClaimSupplierRecourse $r): bool => $r->responded_at !== null);

        $accepted = $answered->filter(static fn (ClaimSupplierRecourse $r): bool => in_array($r->status, [ClaimRecourseStatus::Accepted, ClaimRecourseStatus::PartiallyAccepted], true)
            || ($r->status === ClaimRecourseStatus::Closed && (float) $r->amount_recovered > 0.0))->count();
        $withDue = $answered->filter(static fn (ClaimSupplierRecourse $r): bool => $r->response_due_at !== null);
        $onTime = $withDue->filter(static fn (ClaimSupplierRecourse $r): bool => $r->responded_at <= $r->response_due_at)->count();
        $claimed = (float) $recourses->sum(static fn (ClaimSupplierRecourse $r): float => (float) $r->amount_claimed);
        $recovered = (float) $recourses->sum(static fn (ClaimSupplierRecourse $r): float => (float) $r->amount_recovered);

        $acceptance = $answered->isEmpty() ? null : $accepted / $answered->count();
        $ontimeRate = $withDue->isEmpty() ? null : $onTime / $withDue->count();
        $recovery = $claimed > 0.0 ? min(1.0, $recovered / $claimed) : null;
        $rates = array_values(array_filter([$acceptance, $ontimeRate, $recovery], static fn (?float $r): bool => $r !== null));

        $caseIds = ClaimCase::query()->where('supplier_id', $supplier->id)->whereBetween('reported_at', [$from, $to])->pluck('id');
        $costs = $caseIds->isEmpty() ? 0.0 : (float) ClaimFinancialOutcome::query()
            ->whereIn('claim_case_id', $caseIds)->where('status', 'executed')->sum('amount');

        return [
            'submitted' => $recourses->count(),
            'answered' => $answered->count(),
            'acceptance_rate' => $acceptance,
            'response_ontime_rate' => $ontimeRate,
            'recovery_rate' => $recovery,
            'avg_response_days' => $answered->isEmpty() ? null : round((float) $answered->avg(static fn (ClaimSupplierRecourse $r): float => (float) $r->submitted_at?->diffInDays($r->responded_at, true)), 1),
            'claim_costs' => round($costs, 2),
            'goodness' => $rates === [] ? null : (int) round(array_sum($rates) / count($rates) * 100),
            'available' => $rates !== [],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Kennzahl 3: Preisentwicklung (Einkauf)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Trend der Einkaufspreise: je Artikel erster vs. letzter Bestellpreis
     * (nach Bestelldatum) im Zeitraum → prozentuale Veränderung; Gesamt-Trend =
     * Mittel der Artikel-Trends. Der Chart-Index (Basis 100) normalisiert über
     * unterschiedliche Artikel hinweg. Ohne mindestens einen Artikel mit ≥2
     * Preispunkten ist die Kennzahl „keine Daten".
     *
     * @return array{trend_pct:?float, direction:string, goodness:?int, available:bool, articles:list<array{article:string,first:float,last:float,pct:float}>, series:list<array{x:string,y:float,url:null}>}
     */
    private function priceMetric(Supplier $supplier, CarbonImmutable $from, CarbonImmutable $to, bool $withSeries): array {
        /** @var Collection<int, PurchaseOrderLine> $lines */
        $lines = PurchaseOrderLine::query()
            ->whereNotNull('unit_price')
            ->whereHas('purchaseOrder', function ($q) use ($supplier, $from, $to): void {
                $q->where('supplier_id', $supplier->id)
                    ->whereIn('status', self::ORDERED_STATUSES)
                    ->whereNotNull('ordered_at')
                    ->whereBetween('ordered_at', [$from, $to]);
            })
            ->with(['purchaseOrder:id,ordered_at', 'article:id,name'])
            ->get();

        // Preispunkte je Artikel: [article_id => list<['at'=>CarbonImmutable,'price'=>float,'name'=>string]>]
        /** @var array<int, list<array{at:CarbonImmutable, price:float, name:string}>> $byArticle */
        $byArticle = [];
        foreach ($lines as $line) {
            $order = $line->purchaseOrder;
            if ($order === null || $order->ordered_at === null || $line->unit_price === null) {
                continue;
            }
            $price = $line->unit_price->toFloat();
            if ($price <= 0.0) {
                continue;
            }
            $articleId = (int) $line->article_id;
            $byArticle[$articleId][] = [
                'at' => CarbonImmutable::parse((string) $order->ordered_at),
                'price' => $price,
                'name' => (string) ($line->article->name ?: ('#' . $articleId)),
            ];
        }

        $articleTrends = [];
        $pcts = [];
        foreach ($byArticle as $points) {
            if (count($points) < 2) {
                continue;
            }
            usort($points, static fn(array $a, array $b): int => $a['at'] <=> $b['at']);
            $first = $points[0]['price'];
            $last = $points[count($points) - 1]['price'];
            if ($first <= 0.0) {
                continue;
            }
            $pct = ($last - $first) / $first * 100.0;
            $pcts[] = $pct;
            $articleTrends[] = [
                'article' => $points[0]['name'],
                'first' => round($first, 4),
                'last' => round($last, 4),
                'pct' => round($pct, 1),
            ];
        }

        $trendPct = $pcts === [] ? null : round(array_sum($pcts) / count($pcts), 1);
        $direction = match (true) {
            $trendPct === null => 'none',
            $trendPct > 0.5 => 'up',
            $trendPct < -0.5 => 'down',
            default => 'flat',
        };

        // Chart: normalisierter Preisindex (Basis 100) je Monat über alle
        // qualifizierenden Artikel.
        $series = [];
        if ($withSeries && $pcts !== []) {
            $qualifying = array_filter($byArticle, static fn(array $p): bool => count($p) >= 2);
            /** @var array<string, array{sum:float,count:int}> $monthly */
            $monthly = [];
            foreach ($qualifying as $points) {
                usort($points, static fn(array $a, array $b): int => $a['at'] <=> $b['at']);
                $firstPrice = $points[0]['price'];
                if ($firstPrice <= 0.0) {
                    continue;
                }
                foreach ($points as $point) {
                    $key = $point['at']->format('Y-m');
                    $monthly[$key] ??= ['sum' => 0.0, 'count' => 0];
                    $monthly[$key]['sum'] += $point['price'] / $firstPrice * 100.0;
                    $monthly[$key]['count']++;
                }
            }
            ksort($monthly);
            foreach ($monthly as $key => $agg) {
                $series[] = ['x' => $key, 'y' => round($agg['sum'] / $agg['count'], 1), 'url' => null];
            }
        }

        // Goodness aus Käufersicht: steigende Preise verschlechtern, sinkende
        // verbessern; 0 % = neutral (50). ±20 % erreichen die Ränder.
        $goodness = $trendPct === null
            ? null
            : (int) round(max(0.0, min(100.0, 50.0 - $trendPct * 2.5)));

        return [
            'trend_pct' => $trendPct,
            'direction' => $direction,
            'goodness' => $goodness,
            'available' => $trendPct !== null,
            'articles' => $articleTrends,
            'series' => $series,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Kennzahl 4: Qualitätsbewertung (ISMS-Lieferantenbewertung)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Aktuelle ISMS-Risikoeinstufung des Lieferanten (letzte Bewertung nach
     * Review-Datum) als Qualitätsindikator. Ohne Bewertung „keine Daten".
     *
     * @return array{rating:?IncidentSeverity, status:?string, goodness:?int, available:bool, assessment:?IsmsSupplierAssessment}
     */
    private function qualityMetric(Supplier $supplier): array {
        $assessment = IsmsSupplierAssessment::query()
            ->where('supplier_id', $supplier->id)
            ->orderByRaw('last_review_on IS NULL')
            ->orderByDesc('last_review_on')
            ->orderByDesc('id')
            ->first();

        if (! $assessment instanceof IsmsSupplierAssessment) {
            return ['rating' => null, 'status' => null, 'goodness' => null, 'available' => false, 'assessment' => null];
        }

        $goodness = match ($assessment->risk_rating) {
            IncidentSeverity::Low => 100,
            IncidentSeverity::Medium => 66,
            IncidentSeverity::High => 33,
            IncidentSeverity::Critical => 0,
        };

        return [
            'rating' => $assessment->risk_rating,
            'status' => $assessment->status->value,
            'goodness' => $goodness,
            'available' => true,
            'assessment' => $assessment,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Gesamt-Score
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Gewichteter Gesamt-Score über die verfügbaren Kennzahlen. Nicht
     * verfügbare Kennzahlen (goodness=null) fließen NICHT ein; die Gewichte
     * werden über die verbleibenden re-normalisiert. Ohne jede Kennzahl null.
     *
     * @param  array<string, ?int>  $goodness
     */
    private function overallScore(array $goodness): ?float {
        $weights = $this->weights();
        $weightedSum = 0.0;
        $weightSum = 0.0;

        foreach ($goodness as $key => $value) {
            if ($value === null) {
                continue;
            }
            $w = $weights[$key] ?? 0.0;
            $weightedSum += $w * $value;
            $weightSum += $w;
        }

        return $weightSum > 0.0 ? round($weightedSum / $weightSum, 1) : null;
    }
}
