<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockLevelService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLevelSetting, Warehouse};

/**
 * Mindest-/Meldebestand je Variante und Lagerort (Feature 048, MVP-068) und
 * Erkennung von Beschaffungsbedarf: verfügbare Menge unter Meldebestand.
 */
class StockLevelService {
    public function __construct(private readonly InventoryLedger $ledger) {}

    public function setLevels(ArticleVariant $variant, Warehouse $warehouse, string $minStock, string $reorderPoint): StockLevelSetting {
        /** @var StockLevelSetting $setting */
        $setting = StockLevelSetting::query()->updateOrCreate(
            ['article_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id],
            [
                'organization_id' => $variant->organization_id,
                'min_stock' => $minStock,
                'reorder_point' => $reorderPoint,
            ],
        );

        return $setting;
    }

    /**
     * Variante/Lagerort-Kombinationen, deren verfügbare Menge den Meldebestand
     * unterschreitet (Beschaffungsbedarf).
     *
     * Liste statt Collection: die Mengen gehen als numeric-string in die
     * bc-Rechnung der Bestellvorschläge, und Collection-Pipelines geben diesen
     * Typ nicht weiter.
     *
     * @return list<array{setting: StockLevelSetting, available: numeric-string, shortfall: numeric-string}>
     */
    public function belowReorder(Warehouse $warehouse): array {
        $balances = $this->ledger->balancesByVariant($warehouse);
        $rows = [];

        $settings = StockLevelSetting::query()->where('warehouse_id', $warehouse->id)->with('variant')->get();
        foreach ($settings as $setting) {
            $variant = $setting->variant;
            if ($variant === null) {
                continue;
            }
            $available = InventoryLedger::availableFromBalances($balances[(int) $variant->id] ?? []);
            if (bccomp($available, $setting->reorder_point, 4) >= 0) {
                continue;
            }
            $rows[] = [
                'setting' => $setting,
                'available' => $available,
                'shortfall' => bcsub($setting->reorder_point, $available, 4),
            ];
        }

        return $rows;
    }
}
