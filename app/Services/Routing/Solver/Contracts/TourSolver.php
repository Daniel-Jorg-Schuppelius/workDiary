<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourSolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver\Contracts;

use App\Services\Routing\Solver\{TourPlan, TourProblem};

/**
 * Löst ein Tourenproblem (MVP-1175). Implementierungen: eigene Heuristik
 * (immer verfügbar) und VROOM (wenn konfiguriert, MVP-1178). Der Aufrufer
 * kennt nur den Vertrag; welcher Solver läuft, entscheidet die Konfiguration.
 */
interface TourSolver {
    public function name(): string;

    public function solve(TourProblem $problem): TourPlan;
}
