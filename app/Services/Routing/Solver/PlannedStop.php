<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlannedStop.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

/** Ein Stopp der Lösung mit Ankunft, Wartezeit, Servicebeginn und Abfahrt (Sekunden seit Mitternacht). */
final class PlannedStop {
    public function __construct(
        public readonly int $jobId,
        public readonly int $arrival,
        public readonly int $waitingSeconds,
        public readonly int $serviceStart,
        public readonly int $departure,
        public readonly int $drivingSeconds,
        public readonly int $distanceMeters,
    ) {}

    /** @return array<string, int> */
    public function toArray(): array {
        return ['job_id' => $this->jobId, 'arrival' => $this->arrival, 'waiting' => $this->waitingSeconds, 'service_start' => $this->serviceStart, 'departure' => $this->departure, 'driving' => $this->drivingSeconds, 'distance_m' => $this->distanceMeters];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        return new self((int) $data['job_id'], (int) $data['arrival'], (int) $data['waiting'], (int) $data['service_start'], (int) $data['departure'], (int) $data['driving'], (int) $data['distance_m']);
    }
}
