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

use CommonToolkit\Helper\Geo\GeoHelper;

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

        $order = $hasStart
            ? $this->nearestNeighbor($n, $distanceMatrix, $n)
            : $this->bestOpenNearestNeighbor($n, $distanceMatrix, $hasEnd);
        if ($n > 1) {
            $order = $this->twoOpt($order, $distanceMatrix, $hasStart, $hasEnd, $n);
        }
        $distance = $this->totalDistance($order, $distanceMatrix, $hasStart, $hasEnd, $n);
        $distance = is_finite($distance) ? $distance : 0.0;

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
                $row[] = $i === $j ? 0.0 : GeoHelper::haversineMeters($points[$i]->lat, $points[$i]->lng, $points[$j]->lat, $points[$j]->lng);
            }
            $matrix[] = $row;
        }

        return $matrix;
    }

    /**
     * Nearest neighbor from a row of the matrix: either the start anchor (row
     * N) or a stop that becomes the first element of the order.
     *
     * @param  array<int, array<int, float>>  $matrix
     * @return list<int>
     */
    private function nearestNeighbor(int $n, array $matrix, int $from): array {
        $visited = array_fill(0, $n, false);
        $order = [];
        if ($from < $n) {
            $order[] = $from;
            $visited[$from] = true;
        }

        $current = $from;
        while (count($order) < $n) {
            $best = -1;
            $bestDist = INF;
            for ($i = 0; $i < $n; $i++) {
                if (! $visited[$i] && $matrix[$current][$i] < $bestDist) {
                    $bestDist = $matrix[$current][$i];
                    $best = $i;
                }
            }
            if ($best === -1) {
                // Unerreichbare Reststopps (INF) in Eingabereihenfolge anhängen.
                for ($i = 0; $i < $n; $i++) {
                    if (! $visited[$i]) {
                        $order[] = $i;
                        $visited[$i] = true;
                    }
                }
                break;
            }
            $order[] = $best;
            $visited[$best] = true;
            $current = $best;
        }

        return $order;
    }

    /**
     * Ohne Startanker ist jeder Stopp ein möglicher Anfang: Nearest Neighbor
     * ab jedem Stopp, die kürzeste Kette gewinnt (statt willkürlich Stopp 0).
     *
     * @param  array<int, array<int, float>>  $matrix
     * @return list<int>
     */
    private function bestOpenNearestNeighbor(int $n, array $matrix, bool $hasEnd): array {
        $best = null;
        $bestDist = INF;
        for ($s = 0; $s < $n; $s++) {
            $candidate = $this->nearestNeighbor($n, $matrix, $s);
            $dist = $this->totalDistance($candidate, $matrix, false, $hasEnd, $n);
            if ($best === null || $dist < $bestDist) {
                $best = $candidate;
                $bestDist = $dist;
            }
        }

        return $best ?? [];
    }

    /**
     * 2-opt mit Delta-Bewertung: je Zug werden nur die beiden äußeren Kanten
     * und das umgekehrte Segment gerechnet (korrekt auch für asymmetrische
     * Matrizen), nicht die ganze Tour. Läuft bis kein Zug mehr verbessert.
     *
     * @param  list<int>  $order
     * @param  array<int, array<int, float>>  $matrix
     * @return list<int>
     */
    private function twoOpt(array $order, array $matrix, bool $hasStart, bool $hasEnd, int $n): array {
        $size = count($order);
        $startIdx = $hasStart ? $n : null;
        $endIdx = $hasEnd ? ($hasStart ? $n + 1 : $n) : null;

        $improved = true;
        while ($improved) {
            $improved = false;
            for ($i = 0; $i < $size - 1; $i++) {
                for ($j = $i + 1; $j < $size; $j++) {
                    if ($this->reversalDelta($order, $i, $j, $matrix, $startIdx, $endIdx) < -1e-6) {
                        $segment = array_reverse(array_slice($order, $i, $j - $i + 1));
                        array_splice($order, $i, $j - $i + 1, $segment);
                        $improved = true;
                    }
                }
            }
        }

        return $order;
    }

    /**
     * Distanzänderung, wenn order[i..j] umgekehrt wird (negativ = kürzer).
     *
     * @param  list<int>  $order
     * @param  array<int, array<int, float>>  $m
     */
    private function reversalDelta(array $order, int $i, int $j, array $m, ?int $startIdx, ?int $endIdx): float {
        $size = count($order);
        $prev = $i > 0 ? $order[$i - 1] : $startIdx;
        $next = $j < $size - 1 ? $order[$j + 1] : $endIdx;

        $old = 0.0;
        $new = 0.0;
        if ($prev !== null) {
            $old += $m[$prev][$order[$i]];
            $new += $m[$prev][$order[$j]];
        }
        if ($next !== null) {
            $old += $m[$order[$j]][$next];
            $new += $m[$order[$i]][$next];
        }
        for ($k = $i; $k < $j; $k++) {
            $old += $m[$order[$k]][$order[$k + 1]];
            $new += $m[$order[$k + 1]][$order[$k]];
        }

        return $new - $old;
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
}
