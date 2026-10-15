<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourProblem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

use Carbon\CarbonImmutable;

/**
 * Eingabe eines Solver-Laufs (MVP-1175): Fahrer, Aufträge und Kostenmatrix
 * eines Plantags. `isEstimate` kennzeichnet eine Luftlinien-Matrix;
 * `unlocatedJobIds` nennt Aufträge ohne Koordinaten, die nicht planbar sind.
 */
final class TourProblem {
    /**
     * @param  list<SolverVehicle>  $vehicles
     * @param  list<SolverJob>  $jobs
     * @param  list<int>  $unlocatedJobIds
     */
    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly string $timezone,
        public readonly array $vehicles,
        public readonly array $jobs,
        public readonly CostMatrix $matrix,
        public readonly bool $isEstimate = false,
        public readonly array $unlocatedJobIds = [],
    ) {}

    public function job(int $id): ?SolverJob {
        foreach ($this->jobs as $job) {
            if ($job->id === $id) {
                return $job;
            }
        }

        return null;
    }

    public function vehicle(int $id): ?SolverVehicle {
        foreach ($this->vehicles as $vehicle) {
            if ($vehicle->id === $id) {
                return $vehicle;
            }
        }

        return null;
    }

    /** Sekunden seit Mitternacht (Ortszeit) → Zeitpunkt am Plantag in UTC. */
    public function at(int $seconds): CarbonImmutable {
        return self::atSeconds($this->date, $this->timezone, $seconds);
    }

    public static function atSeconds(CarbonImmutable $date, string $timezone, int $seconds): CarbonImmutable {
        return CarbonImmutable::parse($date->toDateString(), $timezone)->startOfDay()->addSeconds($seconds)->utc();
    }

    /** Zeitpunkt → Sekunden seit Mitternacht des Plantags (Ortszeit). */
    public function secondsOf(\DateTimeInterface $at): int {
        $midnight = CarbonImmutable::parse($this->date->toDateString(), $this->timezone)->startOfDay();

        return (int) $midnight->diffInSeconds(CarbonImmutable::instance($at)->setTimezone($this->timezone), false);
    }

    /** Anzeige- und Nachvollzieh-Daten ohne Matrix (für `tour_proposals.problem`). */
    /** @return array<string, mixed> */
    public function summary(): array {
        return [
            'date' => $this->date->toDateString(),
            'timezone' => $this->timezone,
            'is_estimate' => $this->isEstimate,
            'vehicles' => array_map(static fn(SolverVehicle $v): array => ['id' => $v->id, 'label' => $v->label, 'work_start' => $v->workStart, 'work_end' => $v->workEnd, 'has_start' => $v->startIndex !== null, 'has_end' => $v->endIndex !== null], $this->vehicles),
            'jobs' => array_map(static fn(SolverJob $j): array => ['id' => $j->id, 'label' => $j->label, 'service' => $j->serviceSeconds, 'window_start' => $j->windowStart, 'window_end' => $j->windowEnd, 'priority' => $j->priority], $this->jobs),
            'unlocated_job_ids' => $this->unlocatedJobIds,
        ];
    }
}
