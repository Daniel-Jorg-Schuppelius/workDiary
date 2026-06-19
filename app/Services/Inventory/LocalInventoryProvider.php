<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocalInventoryProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Inventory\InventoryProvider;
use App\Enums\Inventory\{ProviderCapability, StockState};
use App\Models\{ArticleVariant, StockMovement, Warehouse};

/**
 * Lokaler Bestandsprovider (Feature 048, MVP-066/067): führt den Bestand selbst
 * über das append-only Journal ({@see InventoryLedger}). Unterstützt alle
 * Kern-Fähigkeiten; Chargen-/Seriennummern bleiben Folgeausbau.
 */
class LocalInventoryProvider implements InventoryProvider {
    public function __construct(private readonly InventoryLedger $ledger) {}

    /** @return list<ProviderCapability> */
    public function capabilities(): array {
        return [
            ProviderCapability::ReadStock,
            ProviderCapability::CheckAvailability,
            ProviderCapability::Reserve,
            ProviderCapability::ReleaseReservation,
            ProviderCapability::PostConsumption,
            ProviderCapability::PostReceipt,
            ProviderCapability::PostReturn,
            ProviderCapability::PostTransfer,
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
}
