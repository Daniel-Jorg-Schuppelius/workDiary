<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SolverVehicle.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

/**
 * Fahrer samt Fahrzeug im Solver-Problem (MVP-1175): Anker, Arbeitsfenster,
 * Qualifikationen und Pausenregeln. Zeiten in Sekunden seit Mitternacht.
 */
final class SolverVehicle {
    /**
     * @param  list<int>  $qualificationIds
     * @param  list<array{after_minutes: int, required_minutes: int}>  $breakRules  aufsteigend nach `after_minutes`
     */
    public function __construct(
        public readonly int $id,
        public readonly ?int $startIndex,
        public readonly ?int $endIndex,
        public readonly int $workStart,
        public readonly int $workEnd,
        public readonly array $qualificationIds = [],
        public readonly array $breakRules = [],
        public readonly string $label = '',
    ) {}

    public function qualifiesFor(SolverJob $job): bool {
        foreach ($job->requiredQualificationIds as $required) {
            if (! in_array($required, $this->qualificationIds, true)) {
                return false;
            }
        }

        return true;
    }
}
