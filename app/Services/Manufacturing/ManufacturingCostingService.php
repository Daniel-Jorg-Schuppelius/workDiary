<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingCostingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Models\{Article, ArticleVariant, ManufacturingOrder, ManufacturingOrderMaterial};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Nachkalkulation eines Fertigungsauftrags (Feature 047/048, E7): stellt die
 * Plan-Materialkosten (Sollmenge) den Ist-Materialkosten (Verbrauch) gegenüber
 * und leitet die Stückkosten je Gutmenge ab. Kostenbasis je Position:
 * Material-Kostensnapshot → Einkaufspreis der Variante → Standard-Einkaufspreis
 * des Artikels.
 *
 * Die Lohnkosten ergeben sich aus den dem Auftrag zugeordneten Zeitbuchungen
 * (Minuten × interner Stundensatz). Gesamtkosten = Ist-Material + Lohn.
 *
 * Artikelübergreifend (MVP-715, Vollscan G14): {@see self::costingForArticle}
 * aggregiert die abgeschlossenen Aufträge eines Artikels im Zeitraum — Plan/
 * Ist-Material, Plan/Ist-Zeit, Stückkosten Ø/min/max, Abweichung absolut und
 * in Prozent. Summen laufen skalar über bc (kein SUM() auf Nachkommastellen —
 * SQLite rechnet dort in float).
 *
 * @phpstan-type Costing array{planned: numeric-string, actual: numeric-string, labor: numeric-string, total: numeric-string, good: numeric-string, unit_cost: numeric-string}
 * @phpstan-type ArticleCostingOrder array{order_id: int, number: string, completed_at: ?CarbonImmutable, planned_material: numeric-string, actual_material: numeric-string, labor: numeric-string, total: numeric-string, planned_minutes: int, actual_minutes: int, good: numeric-string, scrap: numeric-string, unit_cost: numeric-string, deviation_abs: numeric-string, deviation_pct: ?numeric-string}
 * @phpstan-type ArticleCosting array{orders: list<ArticleCostingOrder>, order_count: int, planned_material: numeric-string, actual_material: numeric-string, labor: numeric-string, total: numeric-string, planned_minutes: int, actual_minutes: int, good: numeric-string, scrap: numeric-string, unit_cost_avg: numeric-string, unit_cost_min: ?numeric-string, unit_cost_max: ?numeric-string, deviation_abs: numeric-string, deviation_pct: ?numeric-string, minutes_deviation: int, quality: array{produced: numeric-string, good: numeric-string, scrap: numeric-string, rework: numeric-string, yield: numeric-string, scrap_rate: numeric-string, rework_rate: numeric-string}}
 */
class ManufacturingCostingService {
    public const SCALE = 4;

    public function __construct(private readonly ManufacturingQualityService $quality) {}

    /**
     * Nachkalkulation je Artikel über die im Zeitraum abgeschlossenen Aufträge
     * (`completed_at` in [from, to]). Stückkosten Ø = Gesamtkosten / Gutmenge
     * über alle Aufträge (mengengewichtet), min/max je Auftrag mit Gutmenge.
     * Abweichung = Ist-Material − Plan-Material (positiv = teurer als geplant).
     * Ausschuss/Yield kommen aus den Qualitätskennzahlen des Artikels daneben
     * (alle Rückmeldungen, nicht zeitraumbegrenzt — bewusst dieselbe Quelle
     * wie die Fertigungsplanung).
     *
     * @return ArticleCosting
     */
    public function costingForArticle(int $articleId, CarbonImmutable $from, CarbonImmutable $to): array {
        $orders = ManufacturingOrder::query()
            ->where('article_id', $articleId)
            ->where('status', ManufacturingOrderStatus::Completed->value)
            ->whereBetween('completed_at', [$from, $to])
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get();

        $rows = [];
        $plannedMaterial = '0';
        $actualMaterial = '0';
        $labor = '0';
        $total = '0';
        $plannedMinutes = 0;
        $actualMinutes = 0;
        $good = '0';
        $scrap = '0';
        $unitMin = null;
        $unitMax = null;

        foreach ($orders as $order) {
            $costing = $this->costing($order);
            $orderMinutes = 0;
            foreach ($order->timeEntries()->pluck('minutes') as $minutes) {
                $orderMinutes += (int) $minutes;
            }
            $orderScrap = $this->num($order->scrapTotal());
            $deviation = bcsub($costing['actual'], $costing['planned'], self::SCALE);

            $rows[] = [
                'order_id' => (int) $order->id,
                'number' => (string) $order->number,
                'completed_at' => $order->completed_at !== null ? CarbonImmutable::parse((string) $order->completed_at) : null,
                'planned_material' => $costing['planned'],
                'actual_material' => $costing['actual'],
                'labor' => $costing['labor'],
                'total' => $costing['total'],
                'planned_minutes' => (int) ($order->planned_minutes ?? 0),
                'actual_minutes' => $orderMinutes,
                'good' => $costing['good'],
                'scrap' => $orderScrap,
                'unit_cost' => $costing['unit_cost'],
                'deviation_abs' => $deviation,
                'deviation_pct' => $this->percent($deviation, $costing['planned']),
            ];

            $plannedMaterial = bcadd($plannedMaterial, $costing['planned'], self::SCALE);
            $actualMaterial = bcadd($actualMaterial, $costing['actual'], self::SCALE);
            $labor = bcadd($labor, $costing['labor'], self::SCALE);
            $total = bcadd($total, $costing['total'], self::SCALE);
            $plannedMinutes += (int) ($order->planned_minutes ?? 0);
            $actualMinutes += $orderMinutes;
            $good = bcadd($good, $costing['good'], self::SCALE);
            $scrap = bcadd($scrap, $orderScrap, self::SCALE);

            // min/max nur über Aufträge mit Gutmenge — 0/0 ist keine Stückkostenaussage.
            if (bccomp($costing['good'], '0', self::SCALE) > 0) {
                $unitMin = $unitMin === null || bccomp($costing['unit_cost'], $unitMin, self::SCALE) < 0 ? $costing['unit_cost'] : $unitMin;
                $unitMax = $unitMax === null || bccomp($costing['unit_cost'], $unitMax, self::SCALE) > 0 ? $costing['unit_cost'] : $unitMax;
            }
        }

        $deviationAbs = bcsub($actualMaterial, $plannedMaterial, self::SCALE);

        return [
            'orders' => $rows,
            'order_count' => count($rows),
            'planned_material' => $plannedMaterial,
            'actual_material' => $actualMaterial,
            'labor' => $labor,
            'total' => $total,
            'planned_minutes' => $plannedMinutes,
            'actual_minutes' => $actualMinutes,
            'good' => $good,
            'scrap' => $scrap,
            'unit_cost_avg' => NumberHelper::divideOrDefault($total, $good, self::SCALE, '0.0000'),
            'unit_cost_min' => $unitMin,
            'unit_cost_max' => $unitMax,
            'deviation_abs' => $deviationAbs,
            'deviation_pct' => $this->percent($deviationAbs, $plannedMaterial),
            'minutes_deviation' => $actualMinutes - $plannedMinutes,
            'quality' => $this->quality->metricsForArticle($articleId),
        ];
    }

    /** @return Costing */
    public function costing(ManufacturingOrder $order): array {
        $planned = '0';
        $actual = '0';

        foreach ($order->materials()->where('is_tool', false)->get() as $material) {
            $unit = $this->unitCost($material);
            $planned = bcadd($planned, bcmul($this->num($material->target_qty), $unit, self::SCALE), self::SCALE);

            // Echte Ist-Kosten (beim Verbrauch erfasst) bevorzugen; sonst auf
            // Menge × Stammkosten zurückfallen.
            $captured = $this->num($material->actual_cost?->getAmount());
            $materialActual = bccomp($captured, '0', self::SCALE) > 0
                ? $captured
                : bcmul($this->num($material->consumed_qty), $unit, self::SCALE);
            $actual = bcadd($actual, $materialActual, self::SCALE);
        }

        $labor = '0';
        foreach ($order->timeEntries()->get() as $entry) {
            $hours = bcdiv((string) $entry->minutes, '60', 6);
            $labor = bcadd($labor, bcmul($hours, $this->num($entry->internal_rate?->getAmount()), self::SCALE), self::SCALE);
        }

        $total = bcadd($actual, $labor, self::SCALE);
        $good = $order->goodTotal();
        $unitCost = NumberHelper::divideOrDefault($total, $good, self::SCALE, '0.0000');

        return ['planned' => $planned, 'actual' => $actual, 'labor' => $labor, 'total' => $total, 'good' => $good, 'unit_cost' => $unitCost];
    }

    /**
     * Abweichung in Prozent der Planbasis; ohne Planbasis keine Aussage (null).
     *
     * @param  numeric-string  $deviation
     * @param  numeric-string  $base
     * @return numeric-string|null
     */
    private function percent(string $deviation, string $base): ?string {
        if (bccomp($base, '0', self::SCALE) <= 0) {
            return null;
        }

        return bcdiv(bcmul($deviation, '100', self::SCALE), $base, 1);
    }

    /** @return numeric-string */
    private function unitCost(ManufacturingOrderMaterial $material): string {
        $snapshot = $this->num($material->cost_snapshot?->getAmount());
        if (bccomp($snapshot, '0', self::SCALE) > 0) {
            return $snapshot;
        }

        if ($material->article_variant_id !== null) {
            $variant = ArticleVariant::query()->find($material->article_variant_id);
            if ($variant instanceof ArticleVariant) {
                $price = $this->num($variant->purchase_price?->getAmount());
                if (bccomp($price, '0', self::SCALE) > 0) {
                    return $price;
                }
            }
        }

        $article = Article::query()->find($material->article_id);
        if ($article instanceof Article) {
            return $this->num($article->default_purchase_price?->getAmount());
        }

        return '0';
    }

    /** @return numeric-string */
    private function num(mixed $value): string {
        $value = (string) $value;

        return is_numeric($value) ? $value : '0';
    }
}
