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

use App\Models\{Article, ArticleVariant, ManufacturingOrder, ManufacturingOrderMaterial};
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
 * @phpstan-type Costing array{planned: numeric-string, actual: numeric-string, labor: numeric-string, total: numeric-string, good: numeric-string, unit_cost: numeric-string}
 */
class ManufacturingCostingService {
    public const SCALE = 4;

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
