<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlWawiInventoryProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory\External;

use App\Contracts\Inventory\InventoryProvider;
use App\Enums\Inventory\{ProviderCapability, StockState};
use App\Models\{ArticleVariant, ExternalArticleMapping, StockMovement, Warehouse};
use App\Services\Inventory\StockPosting;
use RuntimeException;

/**
 * JTL-Wawi-Bestandsprovider (Feature 048, MVP-073) – SCAFFOLD.
 *
 * Bildet die Vertragsfläche {@see InventoryProvider} ab und deklariert die
 * geplanten Fähigkeiten. Vater-/Kindartikel werden über
 * {@see ExternalArticleMapping} (plugin_id = {@see self::PLUGIN_ID}, gegen den
 * Kindartikel) aufgelöst. Die eigentlichen API-Aufrufe stehen unter Pilot mit
 * einer unterstützten Kundenschnittstelle aus; bis dahin werfen die Datenmethoden
 * bewusst eine klare Ausnahme, statt erfundene Werte zu liefern.
 */
class JtlWawiInventoryProvider implements InventoryProvider {
    public const PLUGIN_ID = 'jtl_wawi';

    /** @return list<ProviderCapability> */
    public function capabilities(): array {
        return [
            ProviderCapability::ReadStock,
            ProviderCapability::CheckAvailability,
            ProviderCapability::PostConsumption,
            ProviderCapability::PostReceipt,
            ProviderCapability::PostReturn,
            ProviderCapability::ReceiveFinishedGood,
        ];
    }

    public function supports(ProviderCapability $capability): bool {
        return in_array($capability, $this->capabilities(), true);
    }

    public function available(ArticleVariant $variant, Warehouse $warehouse): string {
        throw $this->pilotPending();
    }

    public function balance(ArticleVariant $variant, Warehouse $warehouse, StockState $state): string {
        throw $this->pilotPending();
    }

    public function post(StockPosting $posting): StockMovement {
        throw $this->pilotPending();
    }

    /** Löst die externe (JTL-)Kennung des Kindartikels auf. */
    public function externalIdFor(ArticleVariant $variant): ?string {
        $mapping = ExternalArticleMapping::query()
            ->where('plugin_id', self::PLUGIN_ID)
            ->where('article_variant_id', $variant->id)
            ->first();

        return $mapping?->external_id;
    }

    private function pilotPending(): RuntimeException {
        return new RuntimeException('JTL-Wawi-Anbindung: API-Implementierung steht unter Pilot aus (MVP-073).');
    }
}
