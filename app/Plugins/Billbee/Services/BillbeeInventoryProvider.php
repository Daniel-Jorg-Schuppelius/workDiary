<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeInventoryProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee\Services;

use App\Contracts\Inventory\InventoryProvider;
use App\Enums\Inventory\{ProviderCapability, StockState};
use App\Models\{ArticleVariant, Organization, StockMovement, Warehouse};
use App\Services\Inventory\{InventoryLedger, StockPosting};

/**
 * Billbee-Bestandsprovider (MVP-434, Muster JTL): Buchungen laufen ins
 * lokale append-only Journal ({@see InventoryLedger}), die externe
 * Zustellung übernimmt der zentrale Spiegel über die `inventory_outbox`
 * ({@see \App\Services\Inventory\ExternalStockMirror} →
 * {@see BillbeeStockDispatcher}).
 *
 * Lese-Unterschied zu JTL: Billbee ist Multichannel-VERTEILER, kein
 * Lagerführer — es kennt nur einen Absolutbestand je SKU, den workDiary
 * per updatestock nachführt. Quelle der Wahrheit für available/balance
 * bleibt deshalb das lokale Journal (kein API-Read je Aufruf).
 */
class BillbeeInventoryProvider implements InventoryProvider {
    public const PLUGIN_ID = 'billbee';

    public function __construct(
        private readonly Organization $organization,
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
        return $this->ledger->available($variant, $warehouse);
    }

    public function balance(ArticleVariant $variant, Warehouse $warehouse, StockState $state): string {
        return $this->ledger->balance($variant, $warehouse, $state);
    }

    public function post(StockPosting $posting): StockMovement {
        return $this->ledger->post($posting);
    }

    public function organization(): Organization {
        return $this->organization;
    }
}
