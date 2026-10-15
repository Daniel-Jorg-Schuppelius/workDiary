<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourPlan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

/** Ergebnis eines Solver-Laufs (MVP-1175). */
final class TourPlan {
    /**
     * @param  list<PlannedRoute>  $routes
     * @param  list<UnassignedJob>  $unassigned
     */
    public function __construct(
        public readonly array $routes,
        public readonly array $unassigned,
        public readonly string $solver,
        public readonly bool $isEstimate,
    ) {}

    public function route(int $vehicleId): ?PlannedRoute {
        foreach ($this->routes as $route) {
            if ($route->vehicleId === $vehicleId) {
                return $route;
            }
        }

        return null;
    }

    public function distanceMeters(): int {
        return (int) array_sum(array_map(static fn(PlannedRoute $r): int => $r->distanceMeters, $this->routes));
    }

    public function drivingSeconds(): int {
        return (int) array_sum(array_map(static fn(PlannedRoute $r): int => $r->drivingSeconds, $this->routes));
    }

    public function waitingSeconds(): int {
        return (int) array_sum(array_map(static fn(PlannedRoute $r): int => $r->waitingSeconds, $this->routes));
    }

    public function stopCount(): int {
        return (int) array_sum(array_map(static fn(PlannedRoute $r): int => count($r->stops), $this->routes));
    }

    /** Mit weiteren Unverplanten (z. B. ohne Koordinaten) ergänzt. */
    public function withUnassigned(UnassignedJob ...$more): self {
        return new self($this->routes, array_values([...$this->unassigned, ...$more]), $this->solver, $this->isEstimate);
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'solver' => $this->solver,
            'is_estimate' => $this->isEstimate,
            'routes' => array_map(static fn(PlannedRoute $r): array => $r->toArray(), $this->routes),
            'unassigned' => array_map(static fn(UnassignedJob $u): array => $u->toArray(), $this->unassigned),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        return new self(
            array_map(static fn(array $r): PlannedRoute => PlannedRoute::fromArray($r), array_values((array) ($data['routes'] ?? []))),
            array_map(static fn(array $u): UnassignedJob => UnassignedJob::fromArray($u), array_values((array) ($data['unassigned'] ?? []))),
            (string) ($data['solver'] ?? 'heuristic'),
            (bool) ($data['is_estimate'] ?? false),
        );
    }
}
