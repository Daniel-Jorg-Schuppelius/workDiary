<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HeuristicTourSolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

use App\Services\Routing\Solver\Contracts\TourSolver;

/**
 * Eigene Heuristik (MVP-1175), immer verfügbar: billigste Einfügung unter
 * Zeitfenstern, Arbeitsfenster und Pflichtpausen, danach 2-opt und Or-opt je
 * Fahrer sowie Verschieben zwischen Fahrern. Zeitfenster und Arbeitsende
 * sind harte Grenzen — was nicht passt, bleibt mit Grund unverplant.
 * Kosten = Fahrzeit plus gewichtete Wartezeit; jede Bewertung ist ein
 * Vorwärtslauf über die Sequenz, damit Pausen und Fenster exakt eingehen.
 */
final class HeuristicTourSolver implements TourSolver {
    public const WAITING_WEIGHT = 0.5;

    private const MAX_IMPROVEMENT_PASSES = 20;

    public function name(): string {
        return 'heuristic';
    }

    public function solve(TourProblem $problem): TourPlan {
        $matrix = $problem->matrix;
        /** @var array<int, SolverVehicle> $vehicles */
        $vehicles = [];
        /** @var array<int, list<SolverJob>> $sequences */
        $sequences = [];
        foreach ($problem->vehicles as $vehicle) {
            $vehicles[$vehicle->id] = $vehicle;
            $sequences[$vehicle->id] = [];
        }

        // Enge Fenster zuerst, dann hohe Priorität: so finden gebundene Aufträge noch Platz.
        $jobs = $problem->jobs;
        usort($jobs, static fn(SolverJob $a, SolverJob $b): int => [$a->windowEnd ?? PHP_INT_MAX, -$a->priority, $a->id] <=> [$b->windowEnd ?? PHP_INT_MAX, -$b->priority, $b->id]);

        $unassigned = [];
        foreach ($jobs as $job) {
            $best = null;
            $reasons = [];
            foreach ($vehicles as $vehicleId => $vehicle) {
                if (! $vehicle->qualifiesFor($job)) {
                    $reasons[] = UnassignedJob::REASON_NO_QUALIFICATION;

                    continue;
                }
                $insertion = $this->bestInsertion($vehicle, $sequences[$vehicleId], $job, $matrix);
                if ($insertion === null) {
                    $reasons[] = $this->reasonFor($vehicle, $job, $matrix);

                    continue;
                }
                if ($best === null || $insertion['delta'] < $best['delta']) {
                    $best = $insertion + ['vehicle' => $vehicleId];
                }
            }
            if ($best === null) {
                $unassigned[] = new UnassignedJob($job->id, $this->dominantReason($reasons));

                continue;
            }
            array_splice($sequences[$best['vehicle']], $best['position'], 0, [$job]);
        }

        foreach ($sequences as $vehicleId => $sequence) {
            $sequences[$vehicleId] = $this->improve($vehicles[$vehicleId], $sequence, $matrix);
        }
        if (count($vehicles) > 1) {
            $this->relocateBetweenVehicles($vehicles, $sequences, $matrix);
        }

        $routes = [];
        foreach ($sequences as $vehicleId => $sequence) {
            $route = $this->evaluate($vehicles[$vehicleId], $sequence, $matrix);
            if ($route !== null) {
                $routes[] = $route;
            }
        }

        return new TourPlan($routes, $unassigned, $this->name(), $problem->isEstimate);
    }

    /**
     * Vorwärtslauf über eine Sequenz: Ankunft, Pause, Warten auf das Fenster,
     * Service, Abfahrt. `null`, wenn ein Fenster oder das Arbeitsende reißt
     * oder ein Punkt unerreichbar ist.
     *
     * @param  list<SolverJob>  $sequence
     */
    public function evaluate(SolverVehicle $vehicle, array $sequence, CostMatrix $matrix): ?PlannedRoute {
        $time = $vehicle->workStart;
        $previous = $vehicle->startIndex;
        $stops = [];
        $breaks = [];
        $distance = 0;
        $driving = 0;
        $waiting = 0;
        $breakTaken = 0;

        foreach ($sequence as $job) {
            $drive = $previous !== null ? $matrix->duration($previous, $job->index) : 0.0;
            if (! is_finite($drive)) {
                return null;
            }
            $leg = $previous !== null ? (int) round($matrix->distance($previous, $job->index)) : 0;
            $driveSeconds = (int) round($drive);
            $arrival = $time + $driveSeconds;

            $serviceStart = max($arrival, $job->windowStart ?? $arrival);
            if ($job->windowEnd !== null && $serviceStart > $job->windowEnd) {
                return null;
            }
            $wait = $serviceStart - $arrival;
            $departure = $serviceStart + $job->serviceSeconds;

            // Pflichtpause (ArbZG §4) spätestens an der Grenze der Regel: bei einem
            // langen Einsatz mitten im Service, sonst vor dem nächsten Beginn.
            foreach ($vehicle->breakRules as $rule) {
                $required = $rule['required_minutes'] * 60;
                $limit = $vehicle->workStart + $rule['after_minutes'] * 60;
                if ($breakTaken < $required && $departure > $limit) {
                    $start = max($serviceStart, $limit);
                    $breaks[] = new PlannedBreak($job->id, $start, $start + ($required - $breakTaken));
                    $departure += $required - $breakTaken;
                    $breakTaken = $required;
                }
            }

            $stops[] = new PlannedStop($job->id, $arrival, $wait, $serviceStart, $departure, $driveSeconds, $leg);
            $distance += $leg;
            $driving += $driveSeconds;
            $waiting += $wait;
            $time = $departure;
            $previous = $job->index;
        }

        $endAt = $time;
        if ($vehicle->endIndex !== null && $previous !== null) {
            $drive = $matrix->duration($previous, $vehicle->endIndex);
            if (! is_finite($drive)) {
                return null;
            }
            $endAt += (int) round($drive);
            $driving += (int) round($drive);
            $distance += (int) round($matrix->distance($previous, $vehicle->endIndex));
        }
        if ($endAt > $vehicle->workEnd) {
            return null;
        }

        return new PlannedRoute($vehicle->id, $stops, $breaks, $vehicle->workStart, $endAt, $distance, $driving, $waiting);
    }

    /**
     * @param  list<SolverJob>  $sequence
     * @return array{position: int, delta: float}|null
     */
    private function bestInsertion(SolverVehicle $vehicle, array $sequence, SolverJob $job, CostMatrix $matrix): ?array {
        $base = $this->evaluate($vehicle, $sequence, $matrix)?->cost() ?? 0.0;
        $best = null;
        for ($position = 0; $position <= count($sequence); $position++) {
            $candidate = $sequence;
            array_splice($candidate, $position, 0, [$job]);
            $route = $this->evaluate($vehicle, $candidate, $matrix);
            if ($route === null) {
                continue;
            }
            $delta = $route->cost() - $base;
            if ($best === null || $delta < $best['delta']) {
                $best = ['position' => $position, 'delta' => $delta];
            }
        }

        return $best;
    }

    /**
     * 2-opt (Segment umkehren) und Or-opt (Kette von 1–3 Stopps versetzen),
     * bis kein Zug mehr die Kosten senkt; nur zulässige Sequenzen zählen.
     *
     * @param  list<SolverJob>  $sequence
     * @return list<SolverJob>
     */
    private function improve(SolverVehicle $vehicle, array $sequence, CostMatrix $matrix): array {
        $n = count($sequence);
        $best = $this->evaluate($vehicle, $sequence, $matrix);
        if ($n < 2 || $best === null) {
            return $sequence;
        }

        for ($pass = 0; $pass < self::MAX_IMPROVEMENT_PASSES; $pass++) {
            $improved = false;
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $candidate = $sequence;
                    array_splice($candidate, $i, $j - $i + 1, array_reverse(array_slice($candidate, $i, $j - $i + 1)));
                    $route = $this->evaluate($vehicle, $candidate, $matrix);
                    if ($route !== null && $route->cost() + 1e-6 < $best->cost()) {
                        $sequence = $candidate;
                        $best = $route;
                        $improved = true;
                    }
                }
            }
            for ($length = 1; $length <= min(3, $n - 1); $length++) {
                for ($i = 0; $i + $length <= $n; $i++) {
                    $chain = array_slice($sequence, $i, $length);
                    $rest = $sequence;
                    array_splice($rest, $i, $length);
                    for ($position = 0; $position <= count($rest); $position++) {
                        if ($position === $i) {
                            continue;
                        }
                        $candidate = $rest;
                        array_splice($candidate, $position, 0, $chain);
                        $route = $this->evaluate($vehicle, $candidate, $matrix);
                        if ($route !== null && $route->cost() + 1e-6 < $best->cost()) {
                            $sequence = $candidate;
                            $best = $route;
                            $improved = true;
                            break 2;
                        }
                    }
                }
            }
            if (! $improved) {
                break;
            }
        }

        return $sequence;
    }

    /**
     * Verschiebt einzelne Stopps zu einem anderen Fahrer, wenn die Gesamtkosten sinken.
     *
     * @param  array<int, SolverVehicle>  $vehicles
     * @param  array<int, list<SolverJob>>  $sequences
     */
    private function relocateBetweenVehicles(array $vehicles, array &$sequences, CostMatrix $matrix): void {
        for ($pass = 0; $pass < 3; $pass++) {
            $improved = false;
            foreach ($sequences as $fromId => $sequence) {
                foreach ($sequence as $i => $job) {
                    $fromRoute = $this->evaluate($vehicles[$fromId], $sequence, $matrix);
                    $without = $sequence;
                    array_splice($without, $i, 1);
                    $fromWithout = $this->evaluate($vehicles[$fromId], $without, $matrix);
                    if ($fromRoute === null || $fromWithout === null) {
                        continue;
                    }
                    $saving = $fromRoute->cost() - $fromWithout->cost();
                    foreach ($vehicles as $toId => $to) {
                        if ($toId === $fromId || ! $to->qualifiesFor($job)) {
                            continue;
                        }
                        $insertion = $this->bestInsertion($to, $sequences[$toId], $job, $matrix);
                        if ($insertion !== null && $saving > $insertion['delta'] + 1e-6) {
                            $sequences[$fromId] = $without;
                            array_splice($sequences[$toId], $insertion['position'], 0, [$job]);
                            $improved = true;

                            continue 3;
                        }
                    }
                }
            }
            if (! $improved) {
                break;
            }
        }
    }

    /** Warum passt der Auftrag bei diesem Fahrer in keine Position? */
    private function reasonFor(SolverVehicle $vehicle, SolverJob $job, CostMatrix $matrix): string {
        $from = $vehicle->startIndex ?? $job->index;
        if (! is_finite($matrix->duration($from, $job->index))) {
            return UnassignedJob::REASON_UNREACHABLE;
        }
        if ($this->evaluate($vehicle, [$job], $matrix) === null) {
            return $job->hasWindow() ? UnassignedJob::REASON_TIME_WINDOW : UnassignedJob::REASON_DAY_FULL;
        }

        return UnassignedJob::REASON_DAY_FULL;
    }

    /**
     * Der aussagekräftigste Grund über alle Fahrer: „Tag voll“ schlägt
     * „Zeitfenster“ schlägt „unerreichbar“ schlägt „Qualifikation“.
     *
     * @param  list<string>  $reasons
     */
    private function dominantReason(array $reasons): string {
        foreach ([UnassignedJob::REASON_DAY_FULL, UnassignedJob::REASON_TIME_WINDOW, UnassignedJob::REASON_UNREACHABLE] as $reason) {
            if (in_array($reason, $reasons, true)) {
                return $reason;
            }
        }

        return UnassignedJob::REASON_NO_QUALIFICATION;
    }
}
