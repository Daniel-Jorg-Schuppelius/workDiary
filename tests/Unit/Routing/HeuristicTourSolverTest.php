<?php

namespace Tests\Unit\Routing;

use App\Services\Routing\Solver\{CostMatrix, HeuristicTourSolver, SolverJob, SolverVehicle, TourProblem, UnassignedJob};
use Carbon\CarbonImmutable;
use Tests\TestCase;

/** MVP-1175: Zeitfenster, Arbeitsfenster, Pausen, Qualifikation und Mehrfahrer-Zuordnung im eigenen Solver. */
class HeuristicTourSolverTest extends TestCase {
    private const H = 3600;

    /** Punkte auf einer Linie, 10 Minuten je Einheit, 10 km je Einheit. */
    private function lineMatrix(array $positions): CostMatrix {
        $durations = [];
        $distances = [];
        foreach ($positions as $i => $a) {
            foreach ($positions as $j => $b) {
                $durations[$i][$j] = abs($a - $b) * 600.0;
                $distances[$i][$j] = abs($a - $b) * 10000.0;
            }
        }

        return new CostMatrix($durations, $distances);
    }

    private function problem(array $vehicles, array $jobs, CostMatrix $matrix): TourProblem {
        return new TourProblem(CarbonImmutable::parse('2026-10-19', 'Europe/Berlin'), 'Europe/Berlin', $vehicles, $jobs, $matrix);
    }

    public function test_time_window_forces_order_and_waiting(): void {
        // Index 0..2 Stopps bei 1, 2, 3; Index 3 = Depot bei 0.
        $matrix = $this->lineMatrix([1, 2, 3, 0]);
        $vehicle = new SolverVehicle(1, 3, 3, 8 * self::H, 17 * self::H);
        $jobs = [
            new SolverJob(10, 0, 1800),                                    // nah, kein Fenster
            new SolverJob(20, 1, 1800, 8 * self::H, 8 * self::H + 1800),    // mittel, muss zuerst (Fenster 8:00–8:30)
            new SolverJob(30, 2, 1800, 12 * self::H, 13 * self::H),         // fern, erst ab 12:00
        ];

        $plan = (new HeuristicTourSolver)->solve($this->problem([$vehicle], $jobs, $matrix));

        $route = $plan->route(1);
        $this->assertNotNull($route);
        $this->assertSame([20, 10, 30], $route->jobIds());
        $this->assertSame([], $plan->unassigned);
        $far = $route->stops[2];
        $this->assertSame(12 * self::H, $far->serviceStart);
        $this->assertGreaterThan(0, $far->waitingSeconds);
    }

    public function test_break_rule_inserts_a_break_after_six_hours(): void {
        $matrix = $this->lineMatrix([1, 2, 0]);
        $vehicle = new SolverVehicle(1, 2, 2, 8 * self::H, 18 * self::H, [], [['after_minutes' => 360, 'required_minutes' => 30]]);
        $jobs = [
            new SolverJob(10, 0, 6 * self::H), // 8:10–14:10
            new SolverJob(20, 1, 2 * self::H), // Ankunft 14:20 → über 6 h seit 8:00 → Pause 30 min
        ];

        $plan = (new HeuristicTourSolver)->solve($this->problem([$vehicle], $jobs, $matrix));

        $route = $plan->route(1);
        $this->assertNotNull($route);
        $this->assertCount(1, $route->breaks);
        $break = $route->breaks[0];
        $this->assertSame(1800, $break->end - $break->start);
        // Die Pause liegt genau an der Sechs-Stunden-Grenze, im Service des Stopps, der sie überschreitet.
        $this->assertSame(8 * self::H + 6 * self::H, $break->start);
        $stop = array_values(array_filter($route->stops, fn($s) => $s->jobId === $break->beforeJobId))[0];
        $this->assertGreaterThanOrEqual($stop->serviceStart, $break->start);
        $this->assertLessThanOrEqual($stop->departure, $break->end);
        $this->assertSame(1800, ($stop->departure - $stop->serviceStart) - ($stop->jobId === 10 ? 6 : 2) * self::H);
    }

    public function test_jobs_beyond_the_work_window_stay_unassigned_with_reason(): void {
        $matrix = $this->lineMatrix([1, 2, 3, 0]);
        $vehicle = new SolverVehicle(1, 3, 3, 8 * self::H, 12 * self::H);
        $jobs = [
            new SolverJob(10, 0, 3 * self::H),
            new SolverJob(20, 1, 3 * self::H),
            new SolverJob(30, 2, 1800, 14 * self::H, 15 * self::H), // Fenster nach Arbeitsende
        ];

        $plan = (new HeuristicTourSolver)->solve($this->problem([$vehicle], $jobs, $matrix));

        $this->assertCount(1, $plan->route(1)->stops);
        $reasons = [];
        foreach ($plan->unassigned as $u) {
            $reasons[$u->jobId] = $u->reason;
        }
        $this->assertSame(UnassignedJob::REASON_DAY_FULL, $reasons[20]);
        $this->assertSame(UnassignedJob::REASON_TIME_WINDOW, $reasons[30]);
    }

    public function test_qualification_steers_assignment_between_drivers(): void {
        // Zwei Fahrer: A startet bei 0, B bei 10; Aufträge bei 1 (braucht Quali 7) und 9.
        $matrix = $this->lineMatrix([1, 9, 0, 10]);
        $a = new SolverVehicle(1, 2, 2, 8 * self::H, 17 * self::H, []);
        $b = new SolverVehicle(2, 3, 3, 8 * self::H, 17 * self::H, [7]);
        $jobs = [
            new SolverJob(10, 0, 1800, null, null, [7]), // nahe A, aber nur B qualifiziert
            new SolverJob(20, 1, 1800),                  // nahe B
        ];

        $plan = (new HeuristicTourSolver)->solve($this->problem([$a, $b], $jobs, $matrix));

        $this->assertSame([], $plan->unassigned);
        $this->assertEqualsCanonicalizing([10, 20], $plan->route(2)->jobIds());
        $this->assertSame([], $plan->route(1)->jobIds());
    }

    public function test_jobs_are_split_between_drivers_by_proximity(): void {
        $matrix = $this->lineMatrix([1, 2, 8, 9, 0, 10]);
        $a = new SolverVehicle(1, 4, 4, 8 * self::H, 17 * self::H);
        $b = new SolverVehicle(2, 5, 5, 8 * self::H, 17 * self::H);
        $jobs = [new SolverJob(10, 0, 600), new SolverJob(20, 1, 600), new SolverJob(30, 2, 600), new SolverJob(40, 3, 600)];

        $plan = (new HeuristicTourSolver)->solve($this->problem([$a, $b], $jobs, $matrix));

        $this->assertEqualsCanonicalizing([10, 20], $plan->route(1)->jobIds());
        $this->assertEqualsCanonicalizing([30, 40], $plan->route(2)->jobIds());
        $this->assertSame(0, $plan->waitingSeconds());
    }

    public function test_missing_qualification_everywhere_is_reported(): void {
        $matrix = $this->lineMatrix([1, 0]);
        $vehicle = new SolverVehicle(1, 1, 1, 8 * self::H, 17 * self::H, [3]);
        $plan = (new HeuristicTourSolver)->solve($this->problem([$vehicle], [new SolverJob(10, 0, 600, null, null, [7])], $matrix));

        $this->assertSame(UnassignedJob::REASON_NO_QUALIFICATION, $plan->unassigned[0]->reason);
    }
}
