<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlTakeoverService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Services;

use App\Enums\Inventory\StockState;
use App\Models\{ArticleVariant, ExternalArticleMapping, JtlWarehouseMapping, Organization, Warehouse};
use App\Plugins\JtlWawi\JtlWawiPlugin;
use App\Services\Inventory\InventoryLedger;

/**
 * Übernahme-Inventur beim Moduswechsel (Feature 078, MVP-324).
 *
 * Beim Wechsel external/read_only → local werden die JTL-Bestände als
 * lokale Eröffnungs-Korrekturen übernommen: je zugeordnetem
 * Variante×Lager-Paar wird der Live-Bestand gelesen und die Differenz zum
 * lokalen Journal als `Correction` gebucht. Idempotenz über
 * `takeover:<org>:<datum>:<variante>:<lager>` — ein wiederholter Aufruf am
 * selben Tag bucht nichts doppelt. Die Übernahme-Buchungen werden vom
 * zentralen Spiegel ausgenommen (Präfix-Filter im
 * {@see \App\Services\Inventory\ExternalStockMirror}) — sie dürfen nie
 * zurück nach JTL fließen.
 *
 * Beim Wechsel local → external wird NICHT automatisch nach JTL gebucht;
 * {@see preflight()} liefert den Bericht (Zuordnungslücken, lokale
 * Restbestände) für die manuelle Übergabe.
 */
class JtlTakeoverService {
    public function __construct(
        private readonly JtlStockReader $reader,
        private readonly InventoryLedger $ledger,
    ) {}

    /** @return array{pairs: int, booked: int, unmapped_warehouses: int} */
    public function importOpeningStock(Organization $organization, ?int $actorUserId = null): array {
        $result = ['pairs' => 0, 'booked' => 0, 'unmapped_warehouses' => 0];
        $stamp = now()->format('Ymd');

        $warehouses = $this->mappedWarehouses($organization);
        if ($warehouses === []) {
            return $result;
        }

        $mappings = ExternalArticleMapping::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', JtlWawiPlugin::ID)
            ->whereNotNull('article_variant_id')
            ->get();

        foreach ($mappings as $mapping) {
            $variant = $mapping->variant;
            if (! $variant instanceof ArticleVariant) {
                continue;
            }

            foreach ($warehouses as $warehouse) {
                $result['pairs']++;

                $snapshot = $this->reader->refresh($variant, $warehouse);
                $externalTotal = $snapshot->quantity_total;
                if (! is_numeric($externalTotal)) {
                    $externalTotal = '0';
                }
                $localPhysical = $this->ledger->balance($variant, $warehouse, StockState::Physical);
                $delta = bcsub($externalTotal, $localPhysical, InventoryLedger::SCALE);

                if (bccomp($delta, '0', InventoryLedger::SCALE) === 0) {
                    continue;
                }

                $this->ledger->correction(
                    $variant,
                    $warehouse,
                    StockState::Physical,
                    $delta,
                    idempotencyKey: sprintf('takeover:%d:%s:%d:%d', $organization->id, $stamp, $variant->id, $warehouse->id),
                    actorUserId: $actorUserId,
                );
                $result['booked']++;
            }
        }

        return $result;
    }

    /**
     * Bericht vor dem Wechsel local → external: was ist (nicht) zugeordnet,
     * wo liegen lokale Restbestände.
     *
     * @return array{article_mappings: int, unmatched_open: int, mapped_warehouses: int, unmapped_jtl_warehouses: int}
     */
    public function preflight(Organization $organization): array {
        $mapped = JtlWarehouseMapping::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('warehouse_id')
            ->count();
        $unmapped = JtlWarehouseMapping::query()
            ->where('organization_id', $organization->id)
            ->whereNull('warehouse_id')
            ->where('jtl_is_active', true)
            ->count();
        $articleMappings = ExternalArticleMapping::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', JtlWawiPlugin::ID)
            ->whereNotNull('article_variant_id')
            ->count();
        $unmatchedOpen = \App\Models\IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', JtlWawiPlugin::ID)
            ->where('status', \App\Models\IntegrationInboxItem::STATUS_OPEN)
            ->count();

        return [
            'article_mappings' => $articleMappings,
            'unmatched_open' => $unmatchedOpen,
            'mapped_warehouses' => $mapped,
            'unmapped_jtl_warehouses' => $unmapped,
        ];
    }

    /** @return list<Warehouse> Lokale Lager mit mindestens einer JTL-Zuordnung. */
    private function mappedWarehouses(Organization $organization): array {
        return array_values(JtlWarehouseMapping::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('warehouse_id')
            ->with('warehouse')
            ->get()
            ->pluck('warehouse')
            ->filter(static fn ($warehouse): bool => $warehouse instanceof Warehouse)
            ->unique('id')
            ->all());
    }
}
