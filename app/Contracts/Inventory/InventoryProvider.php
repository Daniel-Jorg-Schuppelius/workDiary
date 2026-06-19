<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Contracts\Inventory;

use App\Enums\Inventory\{ProviderCapability, StockState};
use App\Models\{ArticleVariant, StockMovement, Warehouse};
use App\Services\Inventory\StockPosting;

/**
 * Austauschbarer Bestandsprovider (Feature 048, MVP-066). Der Kern arbeitet
 * gegen diesen Vertrag; ein Provider deklariert seine Fähigkeiten, damit die
 * Oberfläche/Service-Schicht nur unterstützte Aktionen anbietet. Lokaler
 * Provider = {@see \App\Services\Inventory\LocalInventoryProvider}; externe
 * (z. B. JTL-Wawi) folgen als Plugin.
 */
interface InventoryProvider {
    /** @return list<ProviderCapability> */
    public function capabilities(): array;

    public function supports(ProviderCapability $capability): bool;

    /** Verfügbare Menge (physisch − reserviert − gesperrt − QS) in Basiseinheit. */
    public function available(ArticleVariant $variant, Warehouse $warehouse): string;

    /** Saldo eines Bestandszustands in Basiseinheit. */
    public function balance(ArticleVariant $variant, Warehouse $warehouse, StockState $state): string;

    /**
     * Bucht eine Bewegung. Nicht unterstützte Fähigkeiten werden blockiert.
     *
     * @throws \RuntimeException bei nicht unterstützter Fähigkeit, Negativsperre o. Ä.
     */
    public function post(StockPosting $posting): StockMovement;
}
