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

use App\Models\{ArticleVariant, Customer, MaterialCostAllocation, StockMovement, Warehouse};
use App\Support\DecimalQty;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verbindet Lagerentnahme und Kunden-Materialkosten: eine Entnahme (gleitender
 * Durchschnitt) wird zugleich als {@see MaterialCostAllocation} auf den Kunden
 * gebucht (Quelle = die {@see StockMovement}). Das Löschen einer so entstandenen
 * Zuordnung bucht die Entnahme wieder ins Lager zurück (Gegenbuchung vom Typ
 * Return zum ursprünglichen Stückkostenwert — das append-only Journal bleibt intakt).
 */
class CustomerStockAllocationService {
    public function __construct(private readonly ValuationService $valuation) {}

    /**
     * Entnimmt `$qty` der Variante aus dem Lager (zum gleitenden Durchschnitt)
     * und bucht den Kostenwert als Materialkosten auf den Kunden.
     */
    public function issueForCustomer(
        Customer $customer,
        ArticleVariant $variant,
        Warehouse $warehouse,
        string $qty,
        ?int $projectId = null,
        ?string $allocatedOn = null,
        ?int $actorUserId = null,
    ): MaterialCostAllocation {
        return DB::transaction(function () use ($customer, $variant, $warehouse, $qty, $projectId, $allocatedOn, $actorUserId): MaterialCostAllocation {
            $movement = $this->valuation->issue($variant, $warehouse, $qty, actorUserId: $actorUserId);

            $amount = $movement->cost_total?->getAmount() ?? '0';
            $currency = $customer->currency->value;

            return $customer->materialCostAllocations()->create([
                'organization_id' => $customer->organization_id,
                'project_id' => $projectId,
                'source_type' => $movement->getMorphClass(),
                'source_id' => $movement->getKey(),
                'description' => $this->describe($variant, $qty),
                'allocated_amount' => $amount,
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
            $movement = $allocation->source;
            $variant = $movement instanceof StockMovement ? $movement->variant : null;
            $warehouse = $movement instanceof StockMovement ? $movement->warehouse : null;
            if ($movement instanceof StockMovement && $variant !== null && $warehouse !== null) {
                $this->valuation->returnToStock(
                    $variant,
                    $warehouse,
                    DecimalQty::positive((string) $movement->qty_base),
                    $movement->cost_unit?->getAmount() ?? '0',
                    $allocation->currency->value,
                    $allocation->created_by,
                    source: $movement,
                );
            }

            $allocation->delete();
        });
    }

    private function describe(ArticleVariant $variant, string $qty): string {
        $name = trim((string) ($variant->article->name ?? $variant->sku ?? ''));
        $unit = (string) ($variant->article->base_unit ?? '');
        $qtyLabel = rtrim(rtrim(DecimalQty::sanitize($qty), '0'), '.');

        return trim(sprintf('%s (%s %s)', $name !== '' ? $name : (string) __('customer-material.stock_item'), $qtyLabel, $unit));
    }
}
