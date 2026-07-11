<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlMappingResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Services;

use App\Models\{ArticleVariant, ExternalArticleMapping, JtlConnection, JtlWarehouseMapping, Warehouse};
use App\Plugins\JtlWawi\JtlWawiPlugin;
use RuntimeException;

/**
 * Löst die Zuordnungen der JTL-Anbindung auf (Feature 078):
 * Variante ↔ JTL-Kindartikel ({@see ExternalArticleMapping}) und
 * WorkDiary-Lager ↔ JTL-Lager ({@see JtlWarehouseMapping}, N:1 erlaubt —
 * mehrere JTL-Lager können auf ein WorkDiary-Lager zeigen; Salden werden
 * dann aggregiert). Fehlende Zuordnungen werfen mit klarer Ansage statt
 * still zu raten.
 */
class JtlMappingResolver {
    public function connectionFor(int $organizationId): ?JtlConnection {
        return JtlConnection::query()->where('organization_id', $organizationId)->first();
    }

    public function activeConnectionFor(int $organizationId): JtlConnection {
        $connection = $this->connectionFor($organizationId);
        if (! $connection instanceof JtlConnection || ! $connection->isActive()) {
            throw new RuntimeException('JTL-Wawi: Keine aktive Verbindung für diese Organisation.');
        }

        return $connection;
    }

    /** JTL-Kindartikel-ID der Variante (Bestände laufen nur gegen den Kindartikel). */
    public function externalItemIdFor(ArticleVariant $variant): ?string {
        $mapping = ExternalArticleMapping::query()
            ->where('plugin_id', JtlWawiPlugin::ID)
            ->where('article_variant_id', $variant->id)
            ->first();

        $externalId = trim((string) $mapping?->external_id);

        return $externalId !== '' ? $externalId : null;
    }

    public function requireExternalItemIdFor(ArticleVariant $variant): string {
        $externalId = $this->externalItemIdFor($variant);
        if ($externalId === null) {
            throw new RuntimeException(sprintf(
                'JTL-Wawi: Variante %s (SKU %s) ist keinem JTL-Artikel zugeordnet — Zuordnung in der Integrations-Inbox abschließen.',
                $variant->id,
                (string) $variant->sku,
            ));
        }

        return $externalId;
    }

    /**
     * Alle JTL-Lager-IDs, die auf dieses WorkDiary-Lager zeigen.
     *
     * @return list<string>
     */
    public function jtlWarehouseIdsFor(Warehouse $warehouse): array {
        return array_values(JtlWarehouseMapping::query()
            ->where('organization_id', $warehouse->organization_id)
            ->where('warehouse_id', $warehouse->id)
            ->pluck('jtl_warehouse_id')
            ->map(static fn ($value): string => (string) $value)
            ->all());
    }

    /** @return list<string> */
    public function requireJtlWarehouseIdsFor(Warehouse $warehouse): array {
        $ids = $this->jtlWarehouseIdsFor($warehouse);
        if ($ids === []) {
            throw new RuntimeException(sprintf(
                'JTL-Wawi: Lager „%s“ ist keinem JTL-Lager zugeordnet — Zuordnung im JTL-Admin pflegen.',
                (string) $warehouse->name,
            ));
        }

        return $ids;
    }

    /** WorkDiary-Lager zu einer JTL-Lager-ID (für eingehende Änderungen). */
    public function warehouseForJtlId(int $organizationId, string $jtlWarehouseId): ?Warehouse {
        $mapping = JtlWarehouseMapping::query()
            ->where('organization_id', $organizationId)
            ->where('jtl_warehouse_id', $jtlWarehouseId)
            ->first();

        return $mapping?->warehouse;
    }

    /** Variante zu einer JTL-Artikel-ID (für eingehende Änderungen). */
    public function variantForJtlItemId(int $organizationId, string $jtlItemId): ?ArticleVariant {
        $mapping = ExternalArticleMapping::query()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', JtlWawiPlugin::ID)
            ->where('external_id', $jtlItemId)
            ->first();

        return $mapping?->variant;
    }
}
