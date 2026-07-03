<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentImpactScanner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Gaeb\BoqItemStatus;
use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\{Article, BoqItem, BoqItemMapping, ManufacturingOrder, PurchaseOrder};
use Illuminate\Support\Str;

/**
 * Ermittelt die offenen Vorgänge, die einen Artikel referenzieren (Feature 050,
 * MVP-094): offene Bestellungen (Bestellzeilen), offene LV-Positionen (über das
 * BoQ-Mapping) und laufende Fertigungsaufträge (Materialpositionen). Das
 * Ergebnis wird als Label-Snapshot an der Abgleichwarnung gespeichert, damit
 * die Warnung auch nach Abschluss der Vorgänge nachvollziehbar bleibt.
 */
class DocumentImpactScanner {
    private const LIMIT = 20;

    /**
     * @return array{purchase_orders: list<string>, boq_items: list<string>, manufacturing_orders: list<string>}
     */
    public function scan(int $organizationId, int $articleId): array {
        $purchaseOrders = array_values(PurchaseOrder::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', [
                PurchaseOrderStatus::Draft->value,
                PurchaseOrderStatus::Ordered->value,
                PurchaseOrderStatus::PartiallyReceived->value,
            ])
            ->whereHas('lines', fn ($q) => $q->where('article_id', $articleId))
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->pluck('number')
            ->map(fn ($number): string => (string) $number)
            ->all());

        $boqItemIds = BoqItemMapping::query()
            ->where('organization_id', $organizationId)
            ->where('mappable_type', (new Article())->getMorphClass())
            ->where('mappable_id', $articleId)
            ->pluck('boq_item_id');

        $boqItems = $boqItemIds->isEmpty() ? [] : array_values(BoqItem::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $boqItemIds)
            ->whereIn('status', [
                BoqItemStatus::Draft->value,
                BoqItemStatus::Imported->value,
                BoqItemStatus::Quoted->value,
                BoqItemStatus::Ordered->value,
                BoqItemStatus::InProgress->value,
            ])
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['item_no', 'short_text'])
            ->map(fn (BoqItem $item): string => trim($item->item_no . ' ' . Str::limit((string) $item->short_text, 40)))
            ->all());

        $manufacturingOrders = array_values(ManufacturingOrder::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', [
                ManufacturingOrderStatus::Completed->value,
                ManufacturingOrderStatus::Cancelled->value,
            ])
            ->whereHas('materials', fn ($q) => $q->where('article_id', $articleId))
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['id', 'number'])
            ->map(fn (ManufacturingOrder $order): string => (string) ($order->number ?? '#' . $order->id))
            ->all());

        return [
            'purchase_orders' => $purchaseOrders,
            'boq_items' => $boqItems,
            'manufacturing_orders' => $manufacturingOrders,
        ];
    }

    /** @param array{purchase_orders: list<string>, boq_items: list<string>, manufacturing_orders: list<string>} $impacts */
    public function isEmpty(array $impacts): bool {
        return $impacts['purchase_orders'] === []
            && $impacts['boq_items'] === []
            && $impacts['manufacturing_orders'] === [];
    }
}
