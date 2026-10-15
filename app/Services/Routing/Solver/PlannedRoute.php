<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlannedRoute.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

/** Tour eines Fahrers in der Lösung. */
final class PlannedRoute {
    /**
     * @param  list<PlannedStop>  $stops
     * @param  list<PlannedBreak>  $breaks
     */
    public function __construct(
        public readonly int $vehicleId,
        public readonly array $stops,
        public readonly array $breaks,
        public readonly int $startAt,
        public readonly int $endAt,
        public readonly int $distanceMeters,
        public readonly int $drivingSeconds,
        public readonly int $waitingSeconds,
    ) {}

    /** @return list<int> */
    public function jobIds(): array {
        return array_map(static fn(PlannedStop $stop): int => $stop->jobId, $this->stops);
    }

    public function cost(): float {
        return $this->drivingSeconds + HeuristicTourSolver::WAITING_WEIGHT * $this->waitingSeconds;
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'vehicle_id' => $this->vehicleId,
            'stops' => array_map(static fn(PlannedStop $s): array => $s->toArray(), $this->stops),
            'breaks' => array_map(static fn(PlannedBreak $b): array => $b->toArray(), $this->breaks),
            'start_at' => $this->startAt,
            'end_at' => $this->endAt,
            'distance_m' => $this->distanceMeters,
            'driving' => $this->drivingSeconds,
            'waiting' => $this->waitingSeconds,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        return new self(
            (int) $data['vehicle_id'],
            array_map(static fn(array $s): PlannedStop => PlannedStop::fromArray($s), array_values((array) ($data['stops'] ?? []))),
            array_map(static fn(array $b): PlannedBreak => PlannedBreak::fromArray($b), array_values((array) ($data['breaks'] ?? []))),
            (int) $data['start_at'],
            (int) $data['end_at'],
            (int) $data['distance_m'],
            (int) $data['driving'],
            (int) $data['waiting'],
        );
    }
}
