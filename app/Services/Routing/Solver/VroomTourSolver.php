<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VroomTourSolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

use App\Plugins\Support\PluginHttpFactory;
use App\Services\Routing\RoutingException;
use App\Services\Routing\Solver\Contracts\TourSolver;
use GuzzleHttp\Exception\ConnectException;

/**
 * VROOM-Anbindung (MVP-1178): Fahrer werden `vehicles`, Aufträge `jobs`,
 * unsere Kostenmatrix geht als `matrices.car` mit, sodass VROOM auf
 * denselben Fahrzeiten rechnet wie die Heuristik (OSRM oder Luftlinie).
 * Pausen werden als VROOM-`breaks` mit Zeitfenster „spätestens nach
 * `after_minutes`“ übergeben. Gründe für Unverplantes liefert VROOM nicht;
 * sie werden wie bei der Heuristik hergeleitet.
 */
final class VroomTourSolver implements TourSolver {
    /** Unerreichbar: VROOM kennt kein INF, ein Tag an Sekunden reicht als Sperre. */
    private const UNREACHABLE = 86_400 * 7;

    /** @param array<string, mixed> $config base_url, timeout */
    public function __construct(
        private readonly array $config,
        private readonly PluginHttpFactory $http,
        private readonly HeuristicTourSolver $heuristic,
    ) {}

    public function name(): string {
        return 'vroom';
    }

    public function configured(): bool {
        return trim((string) ($this->config['base_url'] ?? '')) !== '';
    }

    public function solve(TourProblem $problem): TourPlan {
        if (! $this->configured()) {
            throw new RoutingException('VROOM is not configured.');
        }
        $base = rtrim((string) $this->config['base_url'], '/');
        $client = $this->http->coreClient('vroom', $base, allowPrivateNetwork: $this->mayUsePrivateTarget($base));
        $client->setTimeout((float) ($this->config['timeout'] ?? 60));

        try {
            $response = $client->postJson($base . '/', $this->request($problem));
        } catch (ConnectException $e) {
            throw new RoutingException('VROOM unreachable: ' . $e->getMessage(), 0, $e);
        }
        if (! $response->successful()) {
            throw new RoutingException('VROOM returned HTTP ' . $response->status());
        }
        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        if ((int) ($body['code'] ?? -1) !== 0 || ! isset($body['routes']) || ! is_array($body['routes'])) {
            throw new RoutingException('VROOM response invalid: ' . (string) ($body['error'] ?? ($body['code'] ?? 'unknown')));
        }

        return $this->plan($problem, $body);
    }

    /** @return array<string, mixed> */
    public function request(TourProblem $problem): array {
        $vehicles = [];
        foreach ($problem->vehicles as $vehicle) {
            $entry = [
                'id' => $vehicle->id,
                'profile' => 'car',
                'time_window' => [$vehicle->workStart, $vehicle->workEnd],
                'skills' => $vehicle->qualificationIds,
            ];
            if ($vehicle->startIndex !== null) {
                $entry['start_index'] = $vehicle->startIndex;
            }
            if ($vehicle->endIndex !== null) {
                $entry['end_index'] = $vehicle->endIndex;
            }
            $breaks = [];
            foreach ($vehicle->breakRules as $i => $rule) {
                // Pause spätestens nach `after_minutes` Arbeitszeit, frühestens zur Hälfte davon.
                $latest = $vehicle->workStart + $rule['after_minutes'] * 60;
                $breaks[] = [
                    'id' => $i + 1,
                    'service' => $rule['required_minutes'] * 60,
                    'time_windows' => [[$vehicle->workStart + intdiv($rule['after_minutes'] * 60, 2), min($latest, $vehicle->workEnd)]],
                ];
            }
            if ($breaks !== []) {
                $entry['breaks'] = $breaks;
            }
            $vehicles[] = $entry;
        }

        $jobs = [];
        foreach ($problem->jobs as $job) {
            $entry = [
                'id' => $job->id,
                'location_index' => $job->index,
                'service' => $job->serviceSeconds,
                'skills' => $job->requiredQualificationIds,
                'priority' => max(0, min(100, $job->priority)),
            ];
            if ($job->hasWindow()) {
                $entry['time_windows'] = [[$job->windowStart ?? 0, $job->windowEnd ?? 86_400]];
            }
            $jobs[] = $entry;
        }

        $toInt = static fn(array $row): array => array_map(static fn(float $v): int => is_finite($v) ? (int) round($v) : self::UNREACHABLE, $row);

        return [
            'vehicles' => $vehicles,
            'jobs' => $jobs,
            'matrices' => ['car' => [
                'durations' => array_map($toInt, $problem->matrix->durations),
                'distances' => array_map($toInt, $problem->matrix->distances),
            ]],
        ];
    }

    /** @param array<string, mixed> $body */
    private function plan(TourProblem $problem, array $body): TourPlan {
        $routes = [];
        foreach ($body['routes'] as $route) {
            if (! is_array($route)) {
                continue;
            }
            $vehicleId = (int) ($route['vehicle'] ?? 0);
            $vehicle = $problem->vehicle($vehicleId);
            $stops = [];
            $breaks = [];
            $startAt = $vehicle !== null ? $vehicle->workStart : 0;
            $endAt = $startAt;
            $previousDeparture = null;
            $previousIndex = $vehicle?->startIndex;
            $driving = 0;
            $distance = 0;
            $waiting = 0;
            foreach ((array) ($route['steps'] ?? []) as $step) {
                if (! is_array($step)) {
                    continue;
                }
                $arrival = (int) ($step['arrival'] ?? 0);
                switch ($step['type'] ?? '') {
                    case 'start':
                        $startAt = $arrival;
                        $previousDeparture = $arrival;
                        break;
                    case 'break':
                        $breaks[] = new PlannedBreak(null, $arrival, $arrival + (int) ($step['service'] ?? 0));
                        $previousDeparture = $arrival + (int) ($step['service'] ?? 0) + (int) ($step['waiting_time'] ?? 0);
                        break;
                    case 'job':
                        $job = $problem->job((int) ($step['id'] ?? 0));
                        if ($job === null) {
                            break;
                        }
                        $wait = (int) ($step['waiting_time'] ?? 0);
                        $service = (int) ($step['service'] ?? $job->serviceSeconds);
                        $drive = $previousIndex !== null ? (int) round($problem->matrix->duration($previousIndex, $job->index)) : 0;
                        $leg = $previousIndex !== null ? (int) round($problem->matrix->distance($previousIndex, $job->index)) : 0;
                        if ($breaks !== [] && $breaks[array_key_last($breaks)]->beforeJobId === null) {
                            $last = $breaks[array_key_last($breaks)];
                            $breaks[array_key_last($breaks)] = new PlannedBreak($job->id, $last->start, $last->end);
                        }
                        $stops[] = new PlannedStop($job->id, $arrival, $wait, $arrival + $wait, $arrival + $wait + $service, $drive, $leg);
                        $driving += $drive;
                        $distance += $leg;
                        $waiting += $wait;
                        $previousDeparture = $arrival + $wait + $service;
                        $previousIndex = $job->index;
                        break;
                    case 'end':
                        $endAt = $arrival;
                        if ($vehicle?->endIndex !== null && $previousIndex !== null) {
                            $driving += (int) round($problem->matrix->duration($previousIndex, $vehicle->endIndex));
                            $distance += (int) round($problem->matrix->distance($previousIndex, $vehicle->endIndex));
                        }
                        break;
                }
            }
            if ($endAt < ($previousDeparture ?? $startAt)) {
                $endAt = $previousDeparture ?? $startAt;
            }
            $routes[] = new PlannedRoute($vehicleId, $stops, $breaks, $startAt, $endAt, $distance, $driving, $waiting);
        }
        foreach ($problem->vehicles as $vehicle) {
            if (! array_filter($routes, static fn(PlannedRoute $r): bool => $r->vehicleId === $vehicle->id)) {
                $routes[] = new PlannedRoute($vehicle->id, [], [], $vehicle->workStart, $vehicle->workStart, 0, 0, 0);
            }
        }

        $unassigned = [];
        foreach ((array) ($body['unassigned'] ?? []) as $item) {
            $job = is_array($item) ? $problem->job((int) ($item['id'] ?? 0)) : null;
            if ($job !== null) {
                $unassigned[] = new UnassignedJob($job->id, $this->reasonFor($problem, $job));
            }
        }

        return new TourPlan($routes, $unassigned, $this->name(), $problem->isEstimate);
    }

    private function reasonFor(TourProblem $problem, SolverJob $job): string {
        $qualified = array_filter($problem->vehicles, static fn(SolverVehicle $v): bool => $v->qualifiesFor($job));
        if ($qualified === []) {
            return UnassignedJob::REASON_NO_QUALIFICATION;
        }
        foreach ($qualified as $vehicle) {
            if ($this->heuristic->evaluate($vehicle, [$job], $problem->matrix) !== null) {
                return UnassignedJob::REASON_DAY_FULL;
            }
        }
        foreach ($qualified as $vehicle) {
            if (is_finite($problem->matrix->duration($vehicle->startIndex ?? $job->index, $job->index))) {
                return $job->hasWindow() ? UnassignedJob::REASON_TIME_WINDOW : UnassignedJob::REASON_DAY_FULL;
            }
        }

        return UnassignedJob::REASON_UNREACHABLE;
    }

    /** Wie beim OSRM-Router: Betreiber-Vorgabe darf ins private Netz, eine Org-URL nur mit Opt-in. */
    private function mayUsePrivateTarget(string $base): bool {
        $operatorDefault = rtrim((string) config('routing.vroom.base_url', ''), '/');
        if ($operatorDefault !== '' && rtrim($base, '/') === $operatorDefault) {
            return true;
        }

        return (bool) \App\Support\Setting::get('routing.allow_private_network', false);
    }
}
