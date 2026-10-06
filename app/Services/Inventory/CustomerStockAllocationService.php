<?php
/*
 * Created on   : Wed Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerStockAllocationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Article\ArticleVariant;
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockLot, StockMovement, Warehouse};
use App\Models\Material\MaterialCostAllocation;
use App\Support\DecimalQty;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verbindet Lagerentnahme und Kunden-Materialkosten: eine Entnahme (gleitender
 * Durchschnitt) wird zugleich als {@see MaterialCostAllocation} auf den Kunden
 * gebucht (Quelle = die erste {@see StockMovement} des Abgangs). Das Löschen
 * einer so entstandenen Zuordnung bucht jede Bewegung des Abgangs in ihre
 * Charge zurück (Gegenbuchung vom Typ Return zum ursprünglichen
 * Stückkostenwert — das append-only Journal bleibt intakt).
 */
class CustomerStockAllocationService {
    public function __construct(
        private readonly ValuationService $valuation,
        private readonly InventoryLedger $ledger,
    ) {}

    /**
     * Entnimmt `$qty` der Variante aus dem Lager (zum gleitenden Durchschnitt,
     * gewählte Charge oder FEFO) und bucht den Kostenwert als Materialkosten
     * auf den Kunden.
     */
    public function issueForCustomer(
        Customer $customer,
        ArticleVariant $variant,
        Warehouse $warehouse,
        string $qty,
        ?int $projectId = null,
        ?string $allocatedOn = null,
        ?int $actorUserId = null,
        ?StockLot $lot = null,
        bool $requireLot = false,
    ): MaterialCostAllocation {
        return DB::transaction(function () use ($customer, $variant, $warehouse, $qty, $projectId, $allocatedOn, $actorUserId, $lot, $requireLot): MaterialCostAllocation {
            $issue = $this->valuation->issue($variant, $warehouse, $qty, actorUserId: $actorUserId, lot: $lot, requireLot: $requireLot);
            $movement = $issue->first();
            $currency = $customer->currency->value;

            return $customer->materialCostAllocations()->create([
                'organization_id' => $customer->organization_id,
                'project_id' => $projectId,
                'source_type' => $movement->getMorphClass(),
                'source_id' => $movement->getKey(),
                'description' => $this->describe($variant, $qty),
                'allocated_amount' => $issue->costTotal(),
                'currency' => $currency,
                'allocated_on' => $allocatedOn ?? Carbon::now()->toDateString(),
                'created_by' => $actorUserId,
            ]);
        });
    }

    /**
     * Bucht eine aus einer Lagerentnahme entstandene Zuordnung zurück (Zugang
     * derselben Menge zum ursprünglichen Stückkostenwert) und entfernt die
     * Zuordnung. Zuordnungen ohne Lager-Quelle werden nur entfernt.
     */
    public function reverse(MaterialCostAllocation $allocation): void {
        DB::transaction(function () use ($allocation): void {
            $source = $allocation->source;
            $movements = $source instanceof StockMovement ? $this->ledger->issueOf($source) : [];
            foreach ($movements as $movement) {
                $variant = $movement->variant;
                $warehouse = $movement->warehouse;
                if ($variant === null || $warehouse === null) {
                    continue;
                }
                $this->valuation->returnToStock(
                    $variant,
                    $warehouse,
                    DecimalQty::positive((string) $movement->qty_base),
                    $movement->cost_unit?->getAmount() ?? '0',
                    $allocation->currency->value,
                    $allocation->created_by,
                    source: $movement,
                    lot: $movement->lot,
                );
            }

            $allocation->delete();
        });
    }

    private function describe(ArticleVariant $variant, string $qty): string {
        $name = trim((string) ($variant->article->name ?? $variant->sku ?? ''));
        $unit = (string) ($variant->article->base_unit ?? '');
        // Nur Nachkomma-Nullen: rtrim machte aus der Menge 10 eine 1.
        $qtyLabel = NumberHelper::trimTrailingZeros(NumberHelper::normalizeDecimalString($qty));

        return trim(sprintf('%s (%s %s)', $name !== '' ? $name : (string) __('customer-material.stock_item'), $qtyLabel, $unit));
    }
}
