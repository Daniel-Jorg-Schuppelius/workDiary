<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SolverJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

/**
 * Auftrag im Solver-Problem (MVP-1175). Zeiten sind Sekunden seit Mitternacht
 * des Plantags in der Zeitzone der Organisation.
 */
final class SolverJob {
    /**
     * @param  int  $index  Zeile/Spalte in der Kostenmatrix
     * @param  list<int>  $requiredQualificationIds
     */
    public function __construct(
        public readonly int $id,
        public readonly int $index,
        public readonly int $serviceSeconds,
        public readonly ?int $windowStart = null,
        public readonly ?int $windowEnd = null,
        public readonly array $requiredQualificationIds = [],
        public readonly int $priority = 0,
        public readonly string $label = '',
    ) {}

    public function hasWindow(): bool {
        return $this->windowStart !== null || $this->windowEnd !== null;
    }
}
