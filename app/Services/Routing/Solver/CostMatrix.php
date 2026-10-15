<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostMatrix.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

/** Fahrzeit (Sekunden) und Distanz (Meter) zwischen allen Punkten des Problems. */
final class CostMatrix {
    /**
     * @param  array<int, array<int, float>>  $durations
     * @param  array<int, array<int, float>>  $distances
     */
    public function __construct(
        public readonly array $durations,
        public readonly array $distances,
    ) {}

    public function duration(int $from, int $to): float {
        return $this->durations[$from][$to] ?? INF;
    }

    public function distance(int $from, int $to): float {
        return $this->distances[$from][$to] ?? INF;
    }

    public function size(): int {
        return count($this->durations);
    }
}
