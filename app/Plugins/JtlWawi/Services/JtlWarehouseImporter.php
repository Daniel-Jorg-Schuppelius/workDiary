<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlWarehouseImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Services;

use App\Models\{JtlConnection, JtlWarehouseMapping};
use App\Plugins\JtlWawi\Api\JtlGatewayFactory;
use App\Plugins\JtlWawi\JtlWawiPlugin;

/**
 * Projiziert die JTL-Lagerstätten nach `jtl_warehouse_mappings`
 * (Feature 078, MVP-319). Die Zuordnung auf WorkDiary-Lager bleibt
 * Admin-Aufgabe — der Import aktualisiert nur die Projektionsfelder und
 * fasst `warehouse_id` nie an.
 *
 * Lagerplätze (StorageLocations) werden bewusst nicht projiziert: WorkDiary
 * führt keine Lagerplätze; `storageLocationId` ist laut Vertrag nur für
 * WMS-Lagertypen nötig (Abweichungsregister MVP-316).
 */
class JtlWarehouseImporter {
    public function __construct(private readonly JtlGatewayFactory $gateways) {}

    /** @return array{seen: int, created: int, updated: int} */
    public function import(JtlConnection $connection): array {
        $gateway = $this->gateways->for($connection);
        $pageSize = (int) config('plugins.' . JtlWawiPlugin::ID . '.page_size', 100);
        $budget = (int) config('plugins.' . JtlWawiPlugin::ID . '.sync_page_budget', 20);

        $seen = $created = $updated = 0;
        $page = 1;

        do {
            $envelope = $gateway->warehouses($page, $pageSize);

            foreach ((array) ($envelope['items'] ?? []) as $row) {
                $jtlWarehouseId = trim((string) ($row['id'] ?? ''));
                if ($jtlWarehouseId === '') {
                    continue;
                }

                $mapping = JtlWarehouseMapping::query()->updateOrCreate(
                    [
                        'organization_id' => $connection->organization_id,
                        'jtl_warehouse_id' => $jtlWarehouseId,
                    ],
                    [
                        'name' => (string) ($row['name'] ?? $jtlWarehouseId),
                        'code' => isset($row['code']) && trim((string) $row['code']) !== '' ? (string) $row['code'] : null,
                        'warehouse_type' => (string) data_get($row, 'type.name') ?: null,
                        'jtl_is_active' => (bool) ($row['isActive'] ?? true),
                        'lock_for_shipment' => (bool) ($row['lockForShipment'] ?? false),
                        'lock_for_availability' => (bool) ($row['lockForAvailability'] ?? false),
                        'last_seen_at' => now(),
                    ],
                );

                $seen++;
                $mapping->wasRecentlyCreated ? $created++ : $updated++;
            }

            $hasNext = (bool) ($envelope['hasNextPage'] ?? false);
            $page++;
        } while ($hasNext && $page <= $budget);

        return ['seen' => $seen, 'created' => $created, 'updated' => $updated];
    }
}
