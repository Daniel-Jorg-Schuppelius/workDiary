<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenMasterdataService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\OpenMasterdata;

use App\Enums\Procurement\CatalogItemStatus;
use App\Models\Supplier\{SupplierCatalogImport, SupplierCatalogItem, SupplierCatalogSource};
use App\Services\Procurement\{CatalogImportDispatcher, CatalogItemUpserter};
use App\Services\Procurement\OpenMasterdata\Exceptions\OpenMasterdataException;
use CommonToolkit\Helper\Data\JsonHelper;

/**
 * Open Masterdata als Katalogquelle (MVP-1072): Artikel gezielt beim
 * Großhändler nachschlagen und in den Katalog übernehmen; Preise und
 * Verfügbarkeit der geführten Artikel abgleichen — auf Knopfdruck und im
 * fälligen Abruflauf. Verknüpfen und Übernehmen in den Artikelstamm laufen
 * danach wie bei jeder anderen Quelle.
 */
class OpenMasterdataService {
    public const BY_SUPPLIER_PID = 'pid';

    public const BY_GTIN = 'gtin';

    public const BY_MANUFACTURER = 'manufacturer';

    /** Datenpakete des Preisabgleichs — ohne Texte, Bilder und Dokumente. */
    private const PRICE_PACKAGES = ['basic', 'additional', 'prices', 'logistics'];

    public function __construct(
        private readonly OpenMasterdataClient $client,
        private readonly OpenMasterdataProductMapper $mapper,
        private readonly CatalogItemUpserter $upserter,
        private readonly CatalogImportDispatcher $dispatcher,
    ) {}

    /**
     * @return array{product: OpenMasterdataProduct, record: array<string, mixed>, item: SupplierCatalogItem|null}
     *
     * @throws OpenMasterdataException
     */
    public function lookup(SupplierCatalogSource $source, string $by, string $value, ?string $manufacturerId = null, ?string $manufacturerIdType = null): array {
        $product = match ($by) {
            self::BY_GTIN => $this->client->byGtin($source, $value),
            self::BY_MANUFACTURER => $this->client->byManufacturerData($source, (string) $manufacturerId, (string) ($manufacturerIdType ?: 'GLN'), $value),
            default => $this->client->bySupplierPid($source, $value),
        };
        $record = $this->mapper->toRecord($product);

        return [
            'product' => $product,
            'record' => $record,
            'item' => $product->supplierPid() !== '' ? $source->items()->where('external_no', $product->supplierPid())->first() : null,
        ];
    }

    /** Artikel nachschlagen und als Katalogartikel anlegen bzw. nachführen. */
    public function adopt(SupplierCatalogSource $source, string $by, string $value, ?string $manufacturerId = null, ?string $manufacturerIdType = null): SupplierCatalogItem {
        $lookup = $this->lookup($source, $by, $value, $manufacturerId, $manufacturerIdType);
        $product = $lookup['product'];
        if ($product->supplierPid() === '') {
            throw new OpenMasterdataException(OpenMasterdataException::INVALID_RESPONSE);
        }

        $this->upserter->persist($source, [$lookup['record']], JsonHelper::encode($product->data), snapshot: false);

        return $source->items()->where('external_no', $product->supplierPid())->firstOrFail();
    }

    /**
     * Preise und Verfügbarkeit der geführten Artikel nachfragen, die am
     * längsten nicht abgeglichen wurden. Nicht mehr gelistete Artikel werden
     * abgekündigt (verknüpfte als Konflikt), abgelehnte Zugangsdaten brechen
     * den Lauf ab.
     *
     * @return array{rows: int, created: int, updated: int, unchanged: int, price_changed: int, discontinued: int, failed: int}
     *
     * @throws OpenMasterdataException
     */
    public function refreshPrices(SupplierCatalogSource $source, int $limit = 100, string $trigger = SupplierCatalogImport::TRIGGER_MANUAL): array {
        $summary = ['rows' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'price_changed' => 0, 'discontinued' => 0, 'failed' => 0];
        $items = $source->items()
            ->whereNotIn('status', [CatalogItemStatus::Ignored->value])
            ->orderBy('last_seen_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        foreach ($items as $item) {
            try {
                $product = $this->client->bySupplierPid($source, (string) $item->external_no, self::PRICE_PACKAGES);
            } catch (OpenMasterdataException $e) {
                if ($e->isAuthFailure()) {
                    $this->dispatcher->recordFailure($source, $trigger, $e->getMessage());

                    throw $e;
                }
                if ($e->reason === OpenMasterdataException::NOT_FOUND) {
                    $summary['rows']++;
                    $summary['discontinued'] += $this->discontinue($item) ? 1 : 0;

                    continue;
                }
                $summary['failed']++;

                continue;
            }

            $record = $this->mapper->toPriceRecord($product);
            // Alternativ- oder Nachfolgeartikel: Preis gehört zu einem anderen Artikel — nur vermerken.
            if ($product->supplierPid() !== (string) $item->external_no) {
                $summary['rows']++;
                $this->discontinue($item, $product);

                continue;
            }
            $record['external_no'] = (string) $item->external_no;
            $result = $this->upserter->persist($source, [$record], JsonHelper::encode($product->data), snapshot: false);
            foreach (['rows', 'updated', 'unchanged', 'price_changed'] as $key) {
                $summary[$key] += $result[$key];
            }
        }

        $this->dispatcher->recordRun($source, $trigger, $summary);

        return $summary;
    }

    private function discontinue(SupplierCatalogItem $item, ?OpenMasterdataProduct $replacement = null): bool {
        $extra = (array) ($item->extra_attributes ?? []);
        if ($replacement !== null) {
            $extra[$replacement->isFollowup() ? 'omd_successor' : 'omd_alternative'] = $replacement->supplierPid();
        }
        $status = $item->article_id !== null ? CatalogItemStatus::Conflict : CatalogItemStatus::Discontinued;
        $changed = $item->status !== $status;
        $item->forceFill(['status' => $status, 'extra_attributes' => $extra, 'last_seen_at' => now()])->save();

        return $changed;
    }
}
