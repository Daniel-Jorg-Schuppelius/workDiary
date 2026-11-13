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

use App\Contracts\Inventory\InventoryValuationStrategy;
use App\Enums\Inventory\ValuationMethod;
use App\Models\Article\ArticleVariant;
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockLot, StockMovement, Warehouse};
use App\Models\Material\MaterialCostAllocation;
use App\Models\Platform\Organization;
use App\Support\DecimalQty;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verbindet Lagerentnahme und Kunden-Materialkosten: eine Entnahme wird
 * zugleich als {@see MaterialCostAllocation} auf den Kunden gebucht (Quelle =
 * die erste {@see StockMovement} des Abgangs). Bewertet wird nach dem
 * Verfahren der Variante (Durchschnitt, FIFO, FEFO; MVP-1693). Das Löschen
 * einer so entstandenen Zuordnung bucht jede Bewegung des Abgangs in ihre
 * Charge zurück (Gegenbuchung vom Typ Return zum Stückkostenwert des Abgangs:
 * ins Durchschnittsbuch bzw. als neue Schicht — das append-only Journal
 * bleibt intakt).
 */
class CustomerStockAllocationService {
    public function __construct(
        private readonly InventoryValuationManager $valuation,
        private readonly InventoryLedger $ledger,
    ) {}

    /**
     * Entnimmt `$qty` der Variante aus dem Lager (bewertet nach dem Verfahren
     * der Variante, gewählte Charge oder FEFO) und bucht den Kostenwert als
     * Materialkosten auf den Kunden.
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
        ?EnteredQuantity $entered = null,
    ): MaterialCostAllocation {
        return DB::transaction(function () use ($customer, $variant, $warehouse, $qty, $projectId, $allocatedOn, $actorUserId, $lot, $requireLot, $entered): MaterialCostAllocation {
            $issue = $this->strategyFor($variant)->issue($variant, $warehouse, $qty, actorUserId: $actorUserId, lot: $lot, requireLot: $requireLot, entered: $entered);
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
                $this->strategyFor($variant)->returnToStock(
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

    /**
     * Verfahren der Variante (Artikel vor Organisation). FIFO/FEFO führen ihre
     * Schichten, das Durchschnittsbuch steht dort still — Abgang und
     * Rückbuchung müssen dasselbe Buch treffen (MVP-1693).
     */
    private function strategyFor(ArticleVariant $variant): InventoryValuationStrategy {
        $organization = Organization::query()->find($variant->organization_id);

        return $organization instanceof Organization
            ? $this->valuation->forVariant($variant, $organization)
            : $this->valuation->strategy(ValuationMethod::MovingAverage);
    }

    private function describe(ArticleVariant $variant, string $qty): string {
        $name = trim((string) ($variant->article->name ?? $variant->sku ?? ''));
        $unit = (string) ($variant->article->base_unit ?? '');
        // Nur Nachkomma-Nullen: rtrim machte aus der Menge 10 eine 1.
        $qtyLabel = NumberHelper::trimTrailingZeros(NumberHelper::normalizeDecimalString($qty));

        return trim(sprintf('%s (%s %s)', $name !== '' ? $name : (string) __('customer-material.stock_item'), $qtyLabel, $unit));
    }
}
