<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockPosting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{OwnershipType, StockMovementType, StockState};
use App\Models\{ArticleVariant, Warehouse, WarehouseBin};
use Illuminate\Database\Eloquent\Model;

/**
 * Wertobjekt einer einzelnen Lagerbuchung (Feature 048, MVP-067). `signedQty`
 * ist die signierte Menge in der Basiseinheit (Zugang positiv, Abgang negativ).
 * Wird vom {@see InventoryLedger} bzw. einem {@see \App\Contracts\Inventory\InventoryProvider}
 * verarbeitet.
 */
final class StockPosting {
    public function __construct(
        public readonly ArticleVariant $variant,
        public readonly Warehouse $warehouse,
        public readonly StockState $state,
        /** @var numeric-string */
        public readonly string $signedQty,
        public readonly StockMovementType $type,
        public readonly OwnershipType $ownership = OwnershipType::Own,
        public readonly ?string $ownerRef = null,
        public readonly ?string $idempotencyKey = null,
        /** @var numeric-string|null */
        public readonly ?string $originalQty = null,
        public readonly ?string $originalUnit = null,
        public readonly ?int $actorUserId = null,
        public readonly ?Model $source = null,
        /** @var numeric-string|null Kostensnapshot: Einzelkosten in Basiseinheit */
        public readonly ?string $costUnit = null,
        /** @var numeric-string|null Kostensnapshot: Gesamtkosten der Bewegung */
        public readonly ?string $costTotal = null,
        public readonly ?string $currency = null,
        public readonly ?int $stockLotId = null,
        public readonly ?int $stockSerialId = null,
        /** Optionaler Lagerplatz (MVP-706); muss zum Lagerort gehören. */
        public readonly ?WarehouseBin $bin = null,
    ) {}
}
