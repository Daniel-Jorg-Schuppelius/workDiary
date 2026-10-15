<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FallbackTourSolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

use App\Services\Routing\RoutingException;
use App\Services\Routing\Solver\Contracts\TourSolver;

/**
 * Erster Solver, der liefert (L2): VROOM, wenn konfiguriert und erreichbar,
 * sonst die Heuristik. Der Plan nennt, wer gerechnet hat.
 */
final class FallbackTourSolver implements TourSolver {
    public function __construct(
        private readonly VroomTourSolver $vroom,
        private readonly HeuristicTourSolver $heuristic,
    ) {}

    public function name(): string {
        return $this->vroom->configured() ? $this->vroom->name() : $this->heuristic->name();
    }

    public function solve(TourProblem $problem): TourPlan {
        if ($this->vroom->configured()) {
            try {
                return $this->vroom->solve($problem);
            } catch (RoutingException $e) {
                report($e);
            }
        }

        return $this->heuristic->solve($problem);
    }
}
