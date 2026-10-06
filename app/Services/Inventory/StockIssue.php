<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockIssue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\{StockLot, StockMovement};
use ArrayIterator;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Decimal;
use Countable;
use IteratorAggregate;

/**
 * Ergebnis eines Abgangs (Feature 048, E3): eine Bewegung je zugeteilter
 * Charge, der Rest ohne Charge als eigene Bewegung. Mengen und Kosten gelten
 * für den ganzen Abgang.
 *
 * @implements IteratorAggregate<int, StockMovement>
 */
final class StockIssue implements Countable, IteratorAggregate {
    /** @param non-empty-list<StockMovement> $movements */
    public function __construct(public readonly array $movements) {}

    public function first(): StockMovement {
        return $this->movements[0];
    }

    /**
     * Entnommene Menge in Basiseinheit (positiv).
     *
     * @return numeric-string
     */
    public function qty(): string {
        return Decimal::sum(
            array_map(static fn (StockMovement $movement): Decimal => Decimal::of((string) $movement->qty_base, InventoryLedger::SCALE), $this->movements),
            InventoryLedger::SCALE,
        )->abs()->getValue();
    }

    /** @return numeric-string */
    public function costTotal(): string {
        return Decimal::sum(
            array_map(static fn (StockMovement $movement): Decimal => Decimal::of($movement->cost_total?->getAmount() ?? '0', InventoryLedger::SCALE), $this->movements),
            InventoryLedger::SCALE,
        )->getValue();
    }

    /**
     * Stückkosten über alle Teile.
     *
     * @return numeric-string
     */
    public function costUnit(): string {
        return NumberHelper::divideOrDefault($this->costTotal(), $this->qty(), InventoryLedger::SCALE);
    }

    /**
     * Die gebuchten Chargen in Buchungsfolge, ohne Wiederholung.
     *
     * @return list<StockLot>
     */
    public function lots(): array {
        $lots = [];
        foreach ($this->movements as $movement) {
            $lot = $movement->lot;
            if ($lot instanceof StockLot) {
                $lots[$lot->id] ??= $lot;
            }
        }

        return array_values($lots);
    }

    /** @return ArrayIterator<int, StockMovement> */
    public function getIterator(): ArrayIterator {
        return new ArrayIterator($this->movements);
    }

    public function count(): int {
        return count($this->movements);
    }
}
