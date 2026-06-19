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
use App\Models\{ArticleVariant, Organization};

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
}
