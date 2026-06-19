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

use App\Models\{ArticleVariant, StockLevelSetting, Warehouse};
use Illuminate\Support\Collection;

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
     * @return Collection<int, array{setting: StockLevelSetting, available: numeric-string, shortfall: numeric-string}>
     */
    public function belowReorder(Warehouse $warehouse): Collection {
        return StockLevelSetting::query()
            ->where('warehouse_id', $warehouse->id)
            ->with('variant')
            ->get()
            ->map(function (StockLevelSetting $setting) use ($warehouse): ?array {
                $variant = $setting->variant;
                if ($variant === null) {
                    return null;
                }
                $available = $this->ledger->available($variant, $warehouse);
                if (bccomp($available, $setting->reorder_point, 4) >= 0) {
                    return null;
                }

                return [
                    'setting' => $setting,
                    'available' => $available,
                    'shortfall' => bcsub($setting->reorder_point, $available, 4),
                ];
            })
            ->filter()
            ->values();
    }
}
