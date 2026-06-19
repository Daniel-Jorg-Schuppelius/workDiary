<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CycleCountPlanner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\{StockValuation, Warehouse};

/**
 * Zyklische Inventurplanung per ABC-Analyse (Feature 048, E6). Klassifiziert die
 * Varianten eines Lagers nach Bestandswert (Menge × Durchschnittskosten) in
 * A/B/C (kumulativ ≤80 % = A, ≤95 % = B, sonst C) und liefert die fälligen
 * Varianten einer Klasse für eine zyklische Zählung
 * ({@see StocktakeService::openCycle()}).
 */
class CycleCountPlanner {
    public const SCALE = 4;

    /** @return array<int, string> variantId => 'A'|'B'|'C' */
    public function classify(Warehouse $warehouse): array {
        /** @var array<int, numeric-string> $values */
        $values = [];
        $total = '0';

        foreach (StockValuation::query()->where('warehouse_id', $warehouse->id)->get() as $valuation) {
            $value = bcmul($valuation->qty_on_hand, $valuation->avg_cost, self::SCALE);
            if (bccomp($value, '0', self::SCALE) <= 0) {
                continue;
            }
            $values[(int) $valuation->article_variant_id] = $value;
            $total = bcadd($total, $value, self::SCALE);
        }

        if (bccomp($total, '0', self::SCALE) <= 0) {
            return [];
        }

        uasort($values, static fn (string $a, string $b): int => bccomp($b, $a, self::SCALE));

        $classes = [];
        $cumulative = '0';
        foreach ($values as $variantId => $value) {
            $cumulative = bcadd($cumulative, $value, self::SCALE);
            $share = bcdiv(bcmul($cumulative, '100', self::SCALE), $total, self::SCALE);
            $classes[$variantId] = match (true) {
                bccomp($share, '80', self::SCALE) <= 0 => 'A',
                bccomp($share, '95', self::SCALE) <= 0 => 'B',
                default => 'C',
            };
        }

        return $classes;
    }

    /**
     * Varianten der angegebenen ABC-Klassen (fällige Menge der zyklischen Zählung).
     *
     * @param  list<string>  $classes
     * @return list<int>
     */
    public function dueVariants(Warehouse $warehouse, array $classes): array {
        $wanted = array_flip($classes);

        return array_keys(array_filter(
            $this->classify($warehouse),
            static fn (string $class): bool => isset($wanted[$class]),
        ));
    }
}
