<?php

namespace Tests\Unit\Routing;

use App\Services\Routing\RoutingException;
use App\Services\Routing\Solver\{CostMatrix, FallbackTourSolver, HeuristicTourSolver, SolverJob, SolverVehicle, TourProblem, UnassignedJob, VroomTourSolver};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Psr\Http\Message\RequestInterface;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** MVP-1178: VROOM bekommt Fahrer, Aufträge und unsere Matrix; die Antwort wird zum Plan; ohne Dienst rechnet die Heuristik. */
class VroomTourSolverTest extends TestCase {
    private const H = 3600;

    private function problem(): TourProblem {
        $matrix = new CostMatrix(
            [[0, 600, 1200], [600, 0, 600], [1200, 600, 0]],
            [[0, 5000, 10000], [5000, 0, 5000], [10000, 5000, 0]],
        );
        $vehicle = new SolverVehicle(7, 2, 2, 8 * self::H, 17 * self::H, [3], [['after_minutes' => 360, 'required_minutes' => 30]], 'Fahrer 7');
        $jobs = [
            new SolverJob(10, 0, 1800, 9 * self::H, 10 * self::H, [3], 10, 'A'),
            new SolverJob(20, 1, 900, null, null, [], 0, 'B'),
        ];

        return new TourProblem(CarbonImmutable::parse('2026-10-19', 'Europe/Berlin'), 'Europe/Berlin', [$vehicle], $jobs, $matrix);
    }

    private function solver(string $baseUrl = 'http://vroom.test'): VroomTourSolver {
        Config::set('routing.vroom.base_url', $baseUrl);

        return new VroomTourSolver(['base_url' => $baseUrl, 'timeout' => 5], app(\App\Plugins\Support\PluginHttpFactory::class), new HeuristicTourSolver);
    }

    public function test_request_carries_vehicles_jobs_and_matrices_and_response_becomes_a_plan(): void {
        $fake = FakePluginHttp::fake([
            'http://vroom.test/*' => FakePluginHttp::response([
                'code' => 0,
                'summary' => ['cost' => 1, 'unassigned' => 0],
                'unassigned' => [],
                'routes' => [[
                    'vehicle' => 7,
                    'steps' => [
                        ['type' => 'start', 'arrival' => 8 * self::H],
                        ['type' => 'job', 'id' => 20, 'arrival' => 8 * self::H + 600, 'waiting_time' => 0, 'service' => 900],
                        ['type' => 'job', 'id' => 10, 'arrival' => 8 * self::H + 2100, 'waiting_time' => 1500, 'service' => 1800],
                        ['type' => 'end', 'arrival' => 9 * self::H + 2400],
                    ],
                ]],
            ]),
        ]);

        $plan = $this->solver()->solve($this->problem());

        $this->assertSame('vroom', $plan->solver);
        $route = $plan->route(7);
        $this->assertNotNull($route);
        $this->assertSame([20, 10], $route->jobIds());
        $this->assertSame(9 * self::H, $route->stops[1]->serviceStart);
        $this->assertSame(1500, $route->stops[1]->waitingSeconds);
        $this->assertSame(600 + 600 + 1200, $route->drivingSeconds);
        $this->assertSame(5000 + 5000 + 10000, $route->distanceMeters);
        $this->assertSame([], $plan->unassigned);

        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                && ($body['vehicles'][0]['id'] ?? null) === 7
                && ($body['vehicles'][0]['start_index'] ?? null) === 2
                && ($body['vehicles'][0]['skills'] ?? null) === [3]
                && ($body['vehicles'][0]['breaks'][0]['service'] ?? null) === 1800
                && ($body['jobs'][0]['location_index'] ?? null) === 0
                && ($body['jobs'][0]['time_windows'][0] ?? null) === [9 * self::H, 10 * self::H]
                && isset($body['matrices']['car']['durations'][0][1])
                && $body['matrices']['car']['durations'][0][1] === 600;
        });
    }

    public function test_unassigned_jobs_get_a_reason(): void {
        FakePluginHttp::fake([
            'http://vroom.test/*' => FakePluginHttp::response([
                'code' => 0,
                'unassigned' => [['id' => 10], ['id' => 20]],
                'routes' => [],
            ]),
        ]);

        $plan = $this->solver()->solve($this->problem());

        $reasons = [];
        foreach ($plan->unassigned as $u) {
            $reasons[$u->jobId] = $u->reason;
        }
        // Beide passen allein in den Tag → „Tag voll“ ist der plausibelste Grund, den VROOM nicht nennt.
        $this->assertSame(UnassignedJob::REASON_DAY_FULL, $reasons[10]);
        $this->assertSame(UnassignedJob::REASON_DAY_FULL, $reasons[20]);
        $this->assertCount(1, $plan->routes, 'Fahrer ohne Stopps erscheint als leere Route.');
    }

    public function test_error_code_is_a_routing_exception(): void {
        FakePluginHttp::fake(['http://vroom.test/*' => FakePluginHttp::response(['code' => 2, 'error' => 'Input error'])]);

        $this->expectException(RoutingException::class);
        $this->solver()->solve($this->problem());
    }

    public function test_fallback_uses_heuristic_without_configuration_and_on_failure(): void {
        $unconfigured = new FallbackTourSolver($this->solver(''), new HeuristicTourSolver);
        $this->assertSame('heuristic', $unconfigured->name());
        $this->assertSame('heuristic', $unconfigured->solve($this->problem())->solver);

        FakePluginHttp::fake(['http://vroom.test/*' => FakePluginHttp::response(['code' => 3, 'error' => 'Routing error'])]);
        $failing = new FallbackTourSolver($this->solver(), new HeuristicTourSolver);
        $this->assertSame('vroom', $failing->name());
        $plan = $failing->solve($this->problem());
        $this->assertSame('heuristic', $plan->solver);
        $this->assertSame([20, 10], $plan->route(7)?->jobIds());
    }
}
