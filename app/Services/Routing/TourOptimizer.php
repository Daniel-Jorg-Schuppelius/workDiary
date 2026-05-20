<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourOptimizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Routing;

/**
 * Nearest-Neighbor + 2-opt tour optimizer.
 *
 * The optimizer works on an arbitrary square distance matrix (meters). It
 * never talks to OSRM directly — callers either pass an OSRM `/table`
 * matrix or the fallback haversine matrix produced by {@see haversineMatrix()}.
 *
 * Output indices are zero-based and refer to the input `$stops` order.
 * `$start`/`$end` are NOT part of the index space; they are only used as
 * fixed anchors when building the route geometry afterwards.
 */
class TourOptimizer {
    public const MAX_TWO_OPT_ITERATIONS = 50;

    /**
     * @param  array<int, Coordinate>  $stops  service-order coordinates
     * @param  array<int, array<int, float>>  $distanceMatrix  square matrix in meters
     *                                                         indices: 0..N-1 stops, N=start, N+1=end (if both given);
     *                                                         fewer rows if start/end omitted
     * @param  bool  $hasStart  whether row N exists
     * @param  bool  $hasEnd  whether the last row (N or N+1) is the end anchor
     * @return array{order: list<int>, distance: int, duration_estimate: int}
     */
    public function optimize(array $stops, array $distanceMatrix, bool $hasStart = false, bool $hasEnd = false): array {
        $n = count($stops);
        if ($n === 0) {
            return ['order' => [], 'distance' => 0, 'duration_estimate' => 0];
        }
        if ($n === 1) {
            $distance = $this->totalDistance([0], $distanceMatrix, $hasStart, $hasEnd, $n);

            return ['order' => [0], 'distance' => (int) round($distance), 'duration_estimate' => $this->estimateDurationSeconds($distance)];
        }

        $order = $this->nearestNeighbor($n, $distanceMatrix, $hasStart);
        $order = $this->twoOpt($order, $distanceMatrix, $hasStart, $hasEnd, $n);
        $distance = $this->totalDistance($order, $distanceMatrix, $hasStart, $hasEnd, $n);

        return [
            'order' => $order,
            'distance' => (int) round($distance),
            'duration_estimate' => $this->estimateDurationSeconds($distance),
        ];
    }

    /**
     * Builds an N×N haversine distance matrix (meters) for offline planning
     * when OSRM is unreachable or only coarse coordinates are available.
     *
     * If `$start`/`$end` are provided, they are appended after the stops in
     * that order, producing an (N+s+e)×(N+s+e) matrix.
     *
     * @param  array<int, Coordinate>  $stops
     * @return array<int, array<int, float>>
     */
    public function haversineMatrix(array $stops, ?Coordinate $start = null, ?Coordinate $end = null): array {
        $points = $stops;
        if ($start !== null) {
            $points[] = $start;
        }
        if ($end !== null) {
            $points[] = $end;
        }

        $m = count($points);
        $matrix = [];
        for ($i = 0; $i < $m; $i++) {
            $row = [];
            for ($j = 0; $j < $m; $j++) {
                $row[] = $i === $j ? 0.0 : $this->haversine($points[$i], $points[$j]);
            }
            $matrix[] = $row;
        }

        return $matrix;
    }

    /**
     * @param  array<int, array<int, float>>  $matrix
     * @return list<int>
     */
    private function nearestNeighbor(int $n, array $matrix, bool $hasStart): array {
        $visited = array_fill(0, $n, false);
        $order = [];

        // Pick the stop closest to the start anchor (or stop #0 if no start).
        if ($hasStart) {
            $startIdx = $n;
            $best = 0;
            $bestDist = INF;
            for ($i = 0; $i < $n; $i++) {
                if ($matrix[$startIdx][$i] < $bestDist) {
                    $bestDist = $matrix[$startIdx][$i];
                    $best = $i;
                }
            }
            $order[] = $best;
            $visited[$best] = true;
        } else {
            $order[] = 0;
            $visited[0] = true;
        }

        while (count($order) < $n) {
            $current = $order[count($order) - 1];
            $best = -1;
            $bestDist = INF;
            for ($i = 0; $i < $n; $i++) {
                if (! $visited[$i] && $matrix[$current][$i] < $bestDist) {
                    $bestDist = $matrix[$current][$i];
                    $best = $i;
                }
            }
            if ($best === -1) {
                break;
            }
            $order[] = $best;
            $visited[$best] = true;
        }

        return $order;
    }

    /**
     * @param  list<int>  $order
     * @param  array<int, array<int, float>>  $matrix
     * @return list<int>
     */
    private function twoOpt(array $order, array $matrix, bool $hasStart, bool $hasEnd, int $n): array {
        $best = $order;
        $bestDist = $this->totalDistance($best, $matrix, $hasStart, $hasEnd, $n);
        $size = count($best);

        $maxIter = (int) setting('routing.tour_optimizer.iterations', self::MAX_TWO_OPT_ITERATIONS);
        for ($iter = 0; $iter < $maxIter; $iter++) {
            $improved = false;
            for ($i = 0; $i < $size - 1; $i++) {
                for ($j = $i + 1; $j < $size; $j++) {
                    $candidate = $best;
                    // Reverse segment [i, j]
                    $segment = array_slice($candidate, $i, $j - $i + 1);
                    $segment = array_reverse($segment);
                    array_splice($candidate, $i, $j - $i + 1, $segment);

                    $candidateDist = $this->totalDistance($candidate, $matrix, $hasStart, $hasEnd, $n);
                    if ($candidateDist + 1e-6 < $bestDist) {
                        $best = $candidate;
                        $bestDist = $candidateDist;
                        $improved = true;
                    }
                }
            }
            if (! $improved) {
                break;
            }
        }

        return $best;
    }

    /**
     * @param  list<int>  $order
     * @param  array<int, array<int, float>>  $matrix
     */
    private function totalDistance(array $order, array $matrix, bool $hasStart, bool $hasEnd, int $n): float {
        if ($order === []) {
            return 0.0;
        }
        $sum = 0.0;
        if ($hasStart) {
            $sum += $matrix[$n][$order[0]];
        }
        for ($i = 0; $i < count($order) - 1; $i++) {
            $sum += $matrix[$order[$i]][$order[$i + 1]];
        }
        if ($hasEnd) {
            $endIdx = $hasStart ? $n + 1 : $n;
            $sum += $matrix[$order[count($order) - 1]][$endIdx];
        }

        return $sum;
    }

    /**
     * Crude duration heuristic (40 km/h average) when no OSRM duration is
     * available — actual durations come from the `/route` call.
     */
    private function estimateDurationSeconds(float $distanceMeters): int {
        return (int) round($distanceMeters / (40_000 / 3600));
    }

    private function haversine(Coordinate $a, Coordinate $b): float {
        $earth = 6_371_000.0; // meters
        $lat1 = deg2rad($a->lat);
        $lat2 = deg2rad($b->lat);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($b->lng - $a->lng);
        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1.0, sqrt($h)));
    }
}
