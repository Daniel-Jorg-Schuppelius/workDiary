<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryValuationManager.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Inventory\InventoryValuationStrategy;
use App\Enums\Inventory\ValuationMethod;
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\Warehouse;
use App\Models\Platform\Organization;

/**
 * Wählt die aktive Bewertungsstrategie je Organisation (Feature 048, E3):
 * gleitender Durchschnitt ({@see ValuationService}) oder FIFO
 * ({@see FifoValuationService}).
 */
class InventoryValuationManager {
    public function __construct(
        private readonly ValuationService $average,
        private readonly FifoValuationService $fifo,
        private readonly FefoValuationService $fefo,
        private readonly ValuationMethodResolver $resolver,
    ) {}

    public function strategy(ValuationMethod $method): InventoryValuationStrategy {
        return match ($method) {
            ValuationMethod::MovingAverage => $this->average,
            ValuationMethod::Fifo => $this->fifo,
            ValuationMethod::Fefo => $this->fefo,
        };
    }

    public function for(Organization $organization): InventoryValuationStrategy {
        return $this->strategy($this->resolver->methodFor($organization));
    }

    /** Aktive Strategie je Variante (Artikel-Override vor Org-Verfahren). */
    public function forVariant(ArticleVariant $variant, Organization $organization): InventoryValuationStrategy {
        return $this->strategy($this->resolver->methodForVariant($variant, $organization));
    }

    /**
     * Bewertung aller Varianten eines Lagers nach dem wirksamen Verfahren der
     * Variante: FIFO/FEFO aus den Schichten, sonst aus dem Durchschnittsbuch —
     * das steht still, solange Schichten führen.
     *
     * @return array<int, array{avg: numeric-string, value: numeric-string}>
     */
    public function summariesForWarehouse(Warehouse $warehouse, Organization $organization): array {
        $average = $this->average->summariesForWarehouse($warehouse);
        $layered = $this->fifo->summariesForWarehouse($warehouse);
        $variantIds = array_values(array_unique([...array_keys($average), ...array_keys($layered)]));
        if ($variantIds === []) {
            return [];
        }

        $out = [];
        foreach (ArticleVariant::query()->with('article:id,valuation_method')->whereIn('id', $variantIds)->get(['id', 'article_id']) as $variant) {
            $summary = $this->resolver->methodForVariant($variant, $organization) === ValuationMethod::MovingAverage
                ? $average[(int) $variant->id] ?? null
                : $layered[(int) $variant->id] ?? null;
            if ($summary !== null) {
                $out[(int) $variant->id] = $summary;
            }
        }

        return $out;
    }
}
