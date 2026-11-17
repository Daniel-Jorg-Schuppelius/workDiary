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
use App\Models\Article\ArticleVariant;
use App\Models\Claims\ClaimRmaReturn;
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockDelivery, StockMovement, Warehouse};
use App\Models\Platform\{Organization, User};
use App\Services\Claims\Contracts\RmaStockHandler;
use App\Services\Inventory\{InventoryLedger, InventoryValuationManager, LotService, SerialService, StockCorrection, StockPosting};
use App\Services\Licensing\ModuleStatusResolver;
use App\Support\MorphMap;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\{Decimal, Money};

/** Reklamationsrückläufer im Lagerkern (MVP-250): Quarantäne, Wiedereinlagerung, Verschrottung, Rücksendung. */
final class InventoryRmaStockHandler implements RmaStockHandler {
    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly SerialService $serials,
        private readonly LotService $lots,
        private readonly ModuleStatusResolver $modules,
        private readonly StockCorrection $corrections,
        private readonly InventoryValuationManager $valuation,
    ) {}

    public function wasShippedTo(int $organizationId, string $serialNo, Customer $customer): bool {
        return $this->serials->wasShippedTo($organizationId, $serialNo, $customer);
    }

    public function bookReturn(ClaimRmaReturn $rma, string $state, User $actor): bool {
        if (! $this->stockActive($rma)) {
            return false;
        }
        $booked = false;
        $qtyRaw = (string) $rma->qty;
        $qty = is_numeric($qtyRaw) ? NumberHelper::roundPrecise($qtyRaw, 4) : '0.0000';
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
            $booked = true;
        }
        if ($rma->stockSerial !== null) {
            $this->serials->returnSerial($rma->stockSerial, $rma->warehouse);
        }

        return $booked;
    }

    public function applyDisposition(ClaimRmaReturn $rma, ClaimRmaDisposition $disposition, User $actor): bool {
        if (! $this->stockActive($rma)) {
            return false;
        }
        $variant = $rma->articleVariant;
        $warehouse = $rma->warehouse;
        $qtyRaw = (string) $rma->qty;
        $qtyIn = is_numeric($qtyRaw) ? NumberHelper::roundPrecise($qtyRaw, 4) : '0.0000';
        $qtyOut = NumberHelper::negatePrecise($qtyIn);
        $state = $rma->stock_state ?? StockState::Quality;
        $hasStock = $variant !== null && $warehouse !== null && (float) $qtyIn > 0;

        switch ($disposition) {
            case ClaimRmaDisposition::Restock:
                if ($hasStock) {
                    // Erst der Zugang in den freien Bestand trägt einen Wert (MVP-1728): der Quarantäne-Zustand steht nicht im Bewertungsbuch.
                    $organization = $rma->organization;
                    $unitCost = $organization instanceof Organization ? $this->restockUnitCost($rma, $variant, $warehouse, $organization) : null;
                    // Quarantäne → frei verfügbar (zwei Korrekturzeilen); in eine gesperrte Charge bleibt die Ware gesperrt.
                    $this->lots->holdArrival($rma->stockLot, function () use ($variant, $warehouse, $state, $qtyOut, $qtyIn, $rma, $actor, $unitCost): StockMovement {
                        $this->ledger->post(new StockPosting($variant, $warehouse, $state, $qtyOut, StockMovementType::Correction, OwnershipType::Own, idempotencyKey: 'claim-rma:' . $rma->id . ':restock-out', actorUserId: $actor->id, source: $rma, stockLotId: $rma->stock_lot_id, stockSerialId: $rma->stock_serial_id));

                        return $this->corrections->book(new StockPosting($variant, $warehouse, StockState::Physical, $qtyIn, StockMovementType::Correction, OwnershipType::Own, idempotencyKey: 'claim-rma:' . $rma->id . ':restock-in', actorUserId: $actor->id, source: $rma, stockLotId: $rma->stock_lot_id, stockSerialId: $rma->stock_serial_id), $unitCost);
                    });
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
                    // Unbewertet: der Rückläufer kam ohne Kostenwert in den Prüfbestand, das Bewertungsbuch führt ihn nicht.
                    $this->ledger->post(new StockPosting($variant, $warehouse, $state, $qtyOut, StockMovementType::Issue, OwnershipType::Own, idempotencyKey: 'claim-rma:' . $rma->id . ':rts', actorUserId: $actor->id, source: $rma, stockLotId: $rma->stock_lot_id, stockSerialId: $rma->stock_serial_id));
                }
                break;
            case ClaimRmaDisposition::Repair:
                // bleibt in Quarantäne; Maßnahme (MVP-251) steuert weiter
                return false;
        }

        return $hasStock;
    }

    /**
     * Stückwert der Wiedereinlagerung (MVP-1728): der des ursprünglichen Abgangs, wenn die Rücksendung ihn kennt —
     * über ihre Auslieferung (Portal, MVP-1737), sonst die Auslieferung ihrer Seriennummer; deren Abgang trägt die
     * Auslieferung als Beleg —, sonst der Durchschnitt des Lagers. Ohne beides entscheidet die Preisfolge des
     * manuellen Eingangs.
     */
    private function restockUnitCost(ClaimRmaReturn $rma, ArticleVariant $variant, Warehouse $warehouse, Organization $organization): ?Money {
        // Ohne Abgang der Variante an der ersten Auslieferung (etwa wenn der Wareneingang die Variante korrigiert hat) zählt die nächste.
        foreach (array_unique(array_filter([$rma->stock_delivery_id, $rma->stockSerial?->stock_delivery_id])) as $deliveryId) {
            $issued = StockMovement::query()->withoutGlobalScopes()
                ->where('organization_id', $rma->organization_id)
                ->where('source_type', MorphMap::alias(StockDelivery::class))
                ->where('source_id', $deliveryId)
                ->where('article_variant_id', $variant->id)
                ->where('movement_type', StockMovementType::Issue->value)
                ->whereNotNull('cost_total')
                ->get();
            $costs = $issued->map(static fn (StockMovement $movement): ?Money => $movement->cost_total)->filter()->values()->all();
            $qty = Decimal::sum($issued->map(static fn (StockMovement $movement): Decimal => Decimal::of((string) $movement->qty_base, InventoryLedger::SCALE)->abs())->all(), InventoryLedger::SCALE);
            if ($costs !== [] && $qty->isPositive()) {
                return Money::sum($costs, scale: InventoryLedger::SCALE)->dividedBy($qty->getValue());
            }
        }

        return $this->valuation->forVariant($variant, $organization)->averageCost($variant, $warehouse);
    }

    /** Ohne Lager-Modul der Organisation bleibt der Rückläufer reine Dokumentation. */
    private function stockActive(ClaimRmaReturn $rma): bool {
        $organization = $rma->organization;

        return $organization !== null && $this->modules->isActiveFor($organization, 'module.lager');
    }
}
