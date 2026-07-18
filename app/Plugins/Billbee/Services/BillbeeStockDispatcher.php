<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeStockDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee\Services;

use App\Contracts\Inventory\ExternalInventoryDispatcher;
use App\Enums\Inventory\StockState;
use App\Models\{ArticleVariant, ExternalArticleMapping, InventoryOutboxEntry, Warehouse};
use App\Plugins\Billbee\Api\BillbeeClientFactory;
use App\Plugins\Billbee\BillbeePlugin;
use App\Services\Inventory\InventoryLedger;
use RuntimeException;

/**
 * Bestandsrückkanal nach Billbee (MVP-434): stellt gespiegelte lokale
 * Bewegungen als ABSOLUTE Stock-Updates zu (POST /products/updatestock,
 * NewQuantity je SKU). Der Absolutwert wird zum Zustellzeitpunkt aus dem
 * lokalen Journal gelesen — Wiederholungen setzen denselben Zielbestand und
 * sind dadurch von Natur aus idempotent (kein Marker-Scan nötig,
 * Unterschied zu JTL). Zielbestand = physischer Bestand der Variante im
 * bewegten Lager; Mehrlager-Summierung ist bewusste MVP-Grenze (Pilot).
 * Ohne SKU-Mapping schlägt die Zustellung mit klarer Meldung fehl —
 * nach den Queue-Versuchen entsteht der sichtbare Bestandskonflikt.
 */
class BillbeeStockDispatcher implements ExternalInventoryDispatcher {
    public function pluginId(): string {
        return BillbeePlugin::ID;
    }

    public function dispatch(InventoryOutboxEntry $entry): bool {
        $payload = (array) $entry->payload;

        $variant = ArticleVariant::query()->withoutGlobalScopes()
            ->whereHas('article', fn($q) => $q->withoutGlobalScopes()->where('organization_id', $entry->organization_id))
            ->find((int) ($payload['article_variant_id'] ?? 0));
        $warehouse = Warehouse::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->find((int) ($payload['warehouse_id'] ?? 0));
        if (! $variant instanceof ArticleVariant || ! $warehouse instanceof Warehouse) {
            return true; // Quelle existiert nicht mehr → nichts zuzustellen
        }

        $sku = $this->skuFor($entry->organization_id, $variant);
        if ($sku === null) {
            throw new RuntimeException(sprintf(
                'Billbee-Stock-Update ohne SKU-Mapping (Variante %d) — external_article_mappings pflegen (billbee:sync).',
                $variant->id,
            ));
        }

        $absolute = (float) app(InventoryLedger::class)->balance($variant, $warehouse, StockState::Physical);

        app(BillbeeClientFactory::class)
            ->for((int) $entry->organization_id)
            ->updateStock($sku, $absolute, 'workdiary:' . $entry->idempotency_key);

        return true;
    }

    /** SKU aus dem Bestandsmapping: Variante vor Artikel; external_number = SKU. */
    private function skuFor(int $organizationId, ArticleVariant $variant): ?string {
        $mapping = ExternalArticleMapping::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', BillbeePlugin::ID)
            ->where(fn($q) => $q->where('article_variant_id', $variant->id)
                ->orWhere('article_id', $variant->article_id))
            ->orderByRaw('article_variant_id IS NULL')
            ->first();

        $sku = $mapping !== null ? trim((string) $mapping->external_number) : '';
        if ($sku !== '') {
            return $sku;
        }

        // Fallback: lokale Varianten-SKU (deckungsgleiche Artikelpflege).
        $local = trim((string) $variant->sku);

        return $local !== '' ? $local : null;
    }
}
