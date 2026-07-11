<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlWawiInventoryProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Services;

use App\Contracts\Inventory\InventoryProvider;
use App\Enums\Inventory\{ProviderCapability, StockState};
use App\Models\{ArticleVariant, Organization, StockMovement, Warehouse};
use App\Services\Inventory\{InventoryLedger, StockPosting};

/**
 * JTL-Wawi-Bestandsprovider (Feature 078, MVP-319) — löst den Scaffold aus
 * MVP-073 ab. Lesen läuft live/snapshot-gecacht gegen die JTL-API
 * ({@see JtlStockReader}); Buchungen schreibt der Provider ins lokale
 * append-only Journal (Übergabenachweis, {@see InventoryLedger}) — die
 * externe Zustellung übernimmt der zentrale Spiegel über die
 * `inventory_outbox` ({@see \App\Services\Inventory\ExternalStockMirror}).
 * Vater-/Kindartikel werden über {@see JtlMappingResolver} aufgelöst;
 * Bestände und Buchungen laufen ausschließlich gegen den Kindartikel.
 */
class JtlWawiInventoryProvider implements InventoryProvider {
    public const PLUGIN_ID = 'jtl_wawi';

    public function __construct(
        private readonly Organization $organization,
        private readonly JtlStockReader $reader,
        private readonly InventoryLedger $ledger,
    ) {}

    /** @return list<ProviderCapability> */
    public function capabilities(): array {
        return [
            ProviderCapability::ReadStock,
            ProviderCapability::CheckAvailability,
            ProviderCapability::PostConsumption,
            ProviderCapability::PostReceipt,
            ProviderCapability::PostReturn,
            ProviderCapability::PostCorrection,
            ProviderCapability::ReceiveFinishedGood,
        ];
    }

    public function supports(ProviderCapability $capability): bool {
        return in_array($capability, $this->capabilities(), true);
    }

    public function available(ArticleVariant $variant, Warehouse $warehouse): string {
        return $this->reader->available($variant, $warehouse);
    }

    public function balance(ArticleVariant $variant, Warehouse $warehouse, StockState $state): string {
        return $this->reader->balance($variant, $warehouse, $state);
    }

    public function post(StockPosting $posting): StockMovement {
        return $this->ledger->post($posting);
    }

    public function organization(): Organization {
        return $this->organization;
    }
}
