<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryRmaStockHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory\Claims;

use App\Enums\Claims\ClaimRmaDisposition;
use App\Enums\Inventory\{OwnershipType, StockMovementType, StockState};
use App\Models\Claims\ClaimRmaReturn;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Claims\Contracts\RmaStockHandler;
use App\Services\Inventory\{InventoryLedger, SerialService, StockPosting};

/** Reklamationsrückläufer im Lagerkern (MVP-250): Quarantäne, Wiedereinlagerung, Verschrottung, Rücksendung. */
final class InventoryRmaStockHandler implements RmaStockHandler {
    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly SerialService $serials,
    ) {}

    public function wasShippedTo(int $organizationId, string $serialNo, Customer $customer): bool {
        return $this->serials->wasShippedTo($organizationId, $serialNo, $customer);
    }

    public function bookReturn(ClaimRmaReturn $rma, string $state, User $actor): void {
        $qtyRaw = (string) $rma->qty;
        $qty = is_numeric($qtyRaw) ? bcadd($qtyRaw, '0', 4) : '0.0000';
        if ($rma->articleVariant !== null && $rma->warehouse !== null && (float) $qty > 0) {
            $this->ledger->post(new StockPosting(
                $rma->articleVariant,
                $rma->warehouse,
                StockState::from($state),
                $qty,
                StockMovementType::Return,
                OwnershipType::Own,
                idempotencyKey: 'claim-rma:' . $rma->id . ':receive',
                actorUserId: $actor->id,
                source: $rma,
                stockLotId: $rma->stock_lot_id,
                stockSerialId: $rma->stock_serial_id,
            ));
        }
        if ($rma->stockSerial !== null) {
            $this->serials->returnSerial($rma->stockSerial, $rma->warehouse);
        }
    }

    public function applyDisposition(ClaimRmaReturn $rma, ClaimRmaDisposition $disposition, User $actor): void {
        $variant = $rma->articleVariant;
        $warehouse = $rma->warehouse;
        $qtyRaw = (string) $rma->qty;
        $qtyIn = is_numeric($qtyRaw) ? bcadd($qtyRaw, '0', 4) : '0.0000';
        $qtyOut = bcmul($qtyIn, '-1', 4);
        $state = $rma->stock_state !== null ? StockState::from($rma->stock_state) : StockState::Quality;
        $hasStock = $variant !== null && $warehouse !== null && (float) $qtyIn > 0;

        switch ($disposition) {
            case ClaimRmaDisposition::Restock:
                if ($hasStock) {
                    // Quarantäne → frei verfügbar (zwei Korrekturzeilen).
                    $this->ledger->post(new StockPosting($variant, $warehouse, $state, $qtyOut, StockMovementType::Correction, OwnershipType::Own, idempotencyKey: 'claim-rma:' . $rma->id . ':restock-out', actorUserId: $actor->id, source: $rma, stockLotId: $rma->stock_lot_id, stockSerialId: $rma->stock_serial_id));
                    $this->ledger->post(new StockPosting($variant, $warehouse, StockState::Physical, $qtyIn, StockMovementType::Correction, OwnershipType::Own, idempotencyKey: 'claim-rma:' . $rma->id . ':restock-in', actorUserId: $actor->id, source: $rma, stockLotId: $rma->stock_lot_id, stockSerialId: $rma->stock_serial_id));
                }
                if ($rma->stockSerial !== null) {
                    $this->serials->unblock($rma->stockSerial, $warehouse);
                }
                break;
            case ClaimRmaDisposition::Scrap:
            case ClaimRmaDisposition::Dispose:
                if ($hasStock) {
                    $this->ledger->post(new StockPosting($variant, $warehouse, $state, $qtyOut, StockMovementType::Scrap, OwnershipType::Own, idempotencyKey: 'claim-rma:' . $rma->id . ':scrap', actorUserId: $actor->id, source: $rma, stockLotId: $rma->stock_lot_id, stockSerialId: $rma->stock_serial_id));
                }
                if ($rma->stockSerial !== null) {
                    $this->serials->scrap($rma->stockSerial);
                }
                break;
            case ClaimRmaDisposition::ReturnToSupplier:
                if ($hasStock) {
                    $this->ledger->post(new StockPosting($variant, $warehouse, $state, $qtyOut, StockMovementType::Issue, OwnershipType::Own, idempotencyKey: 'claim-rma:' . $rma->id . ':rts', actorUserId: $actor->id, source: $rma, stockLotId: $rma->stock_lot_id, stockSerialId: $rma->stock_serial_id));
                }
                break;
            case ClaimRmaDisposition::Repair:
                // bleibt in Quarantäne; Maßnahme (MVP-251) steuert weiter
                break;
        }
    }
}
