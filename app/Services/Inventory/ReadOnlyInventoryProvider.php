<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReadOnlyInventoryProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Inventory\InventoryProvider;
use App\Enums\Inventory\{ProviderCapability, StockState};
use App\Models\{ArticleVariant, StockMovement, Warehouse};
use RuntimeException;

/**
 * Read-only-Decorator um einen externen Bestandsprovider (Feature 078,
 * MVP-319): bei `inventory_mode = read_only` liest WorkDiary die Bestände
 * des Fremdsystems, bucht aber nichts dorthin. Schreib-Capabilities werden
 * ausgeblendet, `post()` blockiert sichtbar statt still zu simulieren.
 */
class ReadOnlyInventoryProvider implements InventoryProvider {
    private const READ_CAPABILITIES = [
        ProviderCapability::ReadStock,
        ProviderCapability::CheckAvailability,
    ];

    public function __construct(private readonly InventoryProvider $inner) {}

    /** @return list<ProviderCapability> */
    public function capabilities(): array {
        return array_values(array_filter(
            $this->inner->capabilities(),
            static fn (ProviderCapability $capability): bool => in_array($capability, self::READ_CAPABILITIES, true),
        ));
    }

    public function supports(ProviderCapability $capability): bool {
        return in_array($capability, $this->capabilities(), true);
    }

    public function available(ArticleVariant $variant, Warehouse $warehouse): string {
        return $this->inner->available($variant, $warehouse);
    }

    public function balance(ArticleVariant $variant, Warehouse $warehouse, StockState $state): string {
        return $this->inner->balance($variant, $warehouse, $state);
    }

    public function post(StockPosting $posting): StockMovement {
        throw new RuntimeException('Bestandsführung ist read-only: Buchungen erfolgen im führenden externen System.');
    }
}
