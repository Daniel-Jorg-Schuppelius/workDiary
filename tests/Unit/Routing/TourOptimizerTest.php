<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourOptimizerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Routing;

use App\Services\Routing\Coordinate;
use App\Services\Routing\TourOptimizer;
use Tests\TestCase;

class TourOptimizerTest extends TestCase {
    public function test_returns_empty_for_no_stops(): void {
        $opt = new TourOptimizer;
        $result = $opt->optimize([], []);
        $this->assertSame([], $result['order']);
        $this->assertSame(0, $result['distance']);
    }

    public function test_orders_stops_along_a_line_via_nearest_neighbor(): void {
        $opt = new TourOptimizer;
        // Five stops along an east-west line; haversine distance grows monotonically.
        $stops = [
            new Coordinate(52.5, 13.0), // 0
            new Coordinate(52.5, 13.4), // 1
            new Coordinate(52.5, 13.1), // 2
            new Coordinate(52.5, 13.3), // 3
            new Coordinate(52.5, 13.2), // 4
        ];
        $matrix = $opt->haversineMatrix($stops);
        $result = $opt->optimize($stops, $matrix);

        $this->assertSame([0, 2, 4, 3, 1], $result['order']);
        $this->assertGreaterThan(0, $result['distance']);
    }

    public function test_two_opt_finds_a_better_route_than_naive_order(): void {
        $opt = new TourOptimizer;
        // Square layout — a poor starting order should be improved by 2-opt.
        $stops = [
            new Coordinate(0.0, 0.0), // 0
            new Coordinate(0.0, 1.0), // 1
            new Coordinate(1.0, 1.0), // 2
            new Coordinate(1.0, 0.0), // 3
        ];
        $matrix = $opt->haversineMatrix($stops);

        $result = $opt->optimize($stops, $matrix);
        // Expected: walk perimeter (0→1→2→3 or its reverse).
        $this->assertCount(4, $result['order']);
        $this->assertSame([0, 1, 2, 3], $result['order']);
    }

    public function test_respects_start_anchor(): void {
        $opt = new TourOptimizer;
        $stops = [
            new Coordinate(0.0, 5.0),
            new Coordinate(0.0, 1.0),
            new Coordinate(0.0, 3.0),
        ];
        $start = new Coordinate(0.0, 0.0);
        $matrix = $opt->haversineMatrix($stops, $start);

        $result = $opt->optimize($stops, $matrix, hasStart: true);

        $this->assertSame([1, 2, 0], $result['order']);
    }
}
