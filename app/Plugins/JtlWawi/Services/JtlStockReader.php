<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlStockReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Services;

use App\Enums\Inventory\StockState;
use App\Models\{ArticleVariant, JtlStockSnapshot, Warehouse};
use App\Plugins\JtlWawi\Api\JtlGatewayFactory;
use App\Plugins\JtlWawi\JtlWawiPlugin;

/**
 * Liest Bestände aus der führenden JTL-Wawi (Feature 078, MVP-319/320).
 *
 * Innerhalb der Snapshot-TTL antwortet der Reader aus `jtl_stock_snapshots`
 * (sichtbares Datenalter), danach live über `GET /v2/stocks` mit
 * anschließender Snapshot-Erneuerung. Verfügbarkeitsformel aus dem
 * Vertragsauszug (Abweichungsregister MVP-316):
 *
 *   verfügbar = Σ quantityTotal − quantityLockedForShipment
 *             − quantityLockedForAvailability − quantityInPickingLists
 *
 * (`GET /v2/availabilities` ist nur ein Status-Katalog, KEINE
 * Bestandsauskunft.) Zustands-Abbildung auf {@see StockState}:
 * Physical = quantityTotal, Reserved = lockedForShipment + inPickingLists,
 * Blocked = lockedForAvailability; Quality/Damaged/Scrap bildet die JTL-API
 * nicht ab → Saldo 0.
 */
class JtlStockReader {
    private const MAX_PAGES = 50;

    public function __construct(
        private readonly JtlGatewayFactory $gateways,
        private readonly JtlMappingResolver $mappings,
    ) {}

    /** Verfügbare Menge in Basiseinheit (Vertrag: {@see \App\Contracts\Inventory\InventoryProvider::available()}). */
    public function available(ArticleVariant $variant, Warehouse $warehouse): string {
        return $this->snapshotFor($variant, $warehouse)->quantity_available;
    }

    /** Saldo eines Bestandszustands in Basiseinheit. */
    public function balance(ArticleVariant $variant, Warehouse $warehouse, StockState $state): string {
        $snapshot = $this->snapshotFor($variant, $warehouse);

        return match ($state) {
            StockState::Physical => $snapshot->quantity_total,
            StockState::Reserved => $snapshot->quantity_reserved,
            StockState::Blocked => $snapshot->quantity_blocked,
            StockState::Quality, StockState::Damaged, StockState::Scrap => '0.0000',
        };
    }

    /** Erzwingt einen Live-Abruf und erneuert den Snapshot. */
    public function refresh(ArticleVariant $variant, Warehouse $warehouse): JtlStockSnapshot {
        $organizationId = (int) $variant->organization_id;
        $connection = $this->mappings->activeConnectionFor($organizationId);
        $itemId = $this->mappings->requireExternalItemIdFor($variant);
        $jtlWarehouseIds = $this->mappings->requireJtlWarehouseIdsFor($warehouse);

        $total = $reserved = $blocked = 0.0;
        $gateway = $this->gateways->for($connection);

        foreach ($jtlWarehouseIds as $jtlWarehouseId) {
            $page = 1;
            do {
                $envelope = $gateway->stocks($itemId, $jtlWarehouseId, $page, (int) config('plugins.' . JtlWawiPlugin::ID . '.page_size', 100));
                foreach ((array) ($envelope['items'] ?? []) as $row) {
                    $total += (float) ($row['quantityTotal'] ?? 0);
                    $reserved += (float) ($row['quantityLockedForShipment'] ?? 0) + (float) ($row['quantityInPickingLists'] ?? 0);
                    $blocked += (float) ($row['quantityLockedForAvailability'] ?? 0);
                }
                $hasNext = (bool) ($envelope['hasNextPage'] ?? false);
                $page++;
            } while ($hasNext && $page <= self::MAX_PAGES);
        }

        return JtlStockSnapshot::query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'article_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
            ],
            [
                'quantity_total' => $this->qty($total),
                'quantity_available' => $this->qty($total - $reserved - $blocked),
                'quantity_reserved' => $this->qty($reserved),
                'quantity_blocked' => $this->qty($blocked),
                'fetched_at' => now(),
            ],
        );
    }

    private function snapshotFor(ArticleVariant $variant, Warehouse $warehouse): JtlStockSnapshot {
        $ttl = (int) config('plugins.' . JtlWawiPlugin::ID . '.snapshot_ttl', 300);

        $snapshot = JtlStockSnapshot::query()
            ->where('organization_id', $variant->organization_id)
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        if ($snapshot instanceof JtlStockSnapshot && $snapshot->isFresh($ttl)) {
            return $snapshot;
        }

        return $this->refresh($variant, $warehouse);
    }

    /** @return numeric-string */
    private function qty(float $value): string {
        return number_format($value, 4, '.', '');
    }
}
