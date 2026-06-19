<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\{ArticleVariant, StockLot, StockMovement, StockValuationLayer, Warehouse};
use Illuminate\Support\{Carbon, Collection};
use RuntimeException;

/**
 * Chargen-/Losverwaltung (Feature 047/048, E2): Chargen anlegen (eindeutig je
 * Variante), Wareneingang in eine Charge buchen (mit MHD für FEFO) sowie
 * Verfallsüberwachung und Sperre. Bewertung läuft über die FEFO-Schichten.
 */
class LotService {
    public const SCALE = 4;

    public function __construct(private readonly FefoValuationService $fefo) {}

    /** Legt eine Charge an oder liefert die bestehende (eindeutig je Org+Variante+lot_no). */
    public function register(ArticleVariant $variant, string $lotNo, ?string $bestBefore = null, ?string $mfgDate = null, ?string $supplierRef = null): StockLot {
        $lotNo = trim($lotNo);
        if ($lotNo === '') {
            throw new RuntimeException('Leere Chargennummer.');
        }

        return StockLot::query()->firstOrCreate(
            ['organization_id' => $variant->organization_id, 'article_variant_id' => $variant->id, 'lot_no' => $lotNo],
            ['best_before' => $bestBefore, 'mfg_date' => $mfgDate, 'supplier_ref' => $supplierRef, 'status' => StockLot::STATUS_ACTIVE],
        );
    }

    /** Wareneingang in eine Charge (legt eine FEFO-Bewertungsschicht an). */
    public function receiveIntoLot(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, StockLot $lot, string $currency = 'EUR', ?int $actorUserId = null): StockMovement {
        return $this->fefo->receiptIntoLot($variant, $warehouse, $qty, $unitCost, $lot, $currency, $actorUserId);
    }

    /** Akkurater Restbestand einer Charge über die Bewertungsschichten. @return numeric-string */
    public function onHand(StockLot $lot): string {
        $sum = (string) StockValuationLayer::query()->where('stock_lot_id', $lot->id)->sum('qty_remaining');

        return bcadd($sum, '0', self::SCALE);
    }

    public function block(StockLot $lot): StockLot {
        $lot->forceFill(['status' => StockLot::STATUS_BLOCKED])->save();

        return $lot;
    }

    /**
     * Bewertungsschichten mit Restbestand, deren MHD bis zum Stichtag fällt
     * (MHD-Überwachung, frühestes Verfallsdatum zuerst).
     *
     * @return Collection<int, StockValuationLayer>
     */
    public function expiringUntil(Carbon $date, ?ArticleVariant $variant = null): Collection {
        $variantId = $variant?->id;

        return StockValuationLayer::query()
            ->whereNotNull('best_before')
            ->where('best_before', '<=', $date)
            ->where('qty_remaining', '>', 0)
            ->when($variantId !== null, fn ($q) => $q->where('article_variant_id', $variantId))
            ->orderBy('best_before')
            ->get();
    }
}
