<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Routing;

use App\Enums\Diary\{Mode, Status as DiaryStatus};
use App\Enums\Tour\TourStatus;
use App\Enums\Travel\TravelLogVehicle;
use App\Models\{DiaryEntry, Tour, TravelLog, User};
use App\Services\Travel\TravelLogService;
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * High-level orchestration around the {@see Tour} aggregate: creation,
 * assignment of {@see DiaryEntry} stops (typed via EntryType), optimisation
 * via {@see TourOptimizer} (with optional OSRM-backed routing) and the final
 * hand-off to {@see TravelLogService} which materialises the tour as actual
 * travel logs.
 */
class TourService {
    public function __construct(
        private readonly TourOptimizer $optimizer,
        private readonly OsrmRouter $router,
        private readonly TravelLogService $travelLogs,
    ) {}

    /**
     * @param  list<int>  $orderIds  service-order IDs in the desired initial order
     */
    public function createDraft(User $driver, CarbonInterface $date, array $orderIds = []): Tour {
        return DB::transaction(function () use ($driver, $date, $orderIds): Tour {
            $tour = new Tour;
            $tour->organization_id = $driver->organization_id;
            $tour->user_id = (int) $driver->id;
            $tour->tour_date = Carbon::instance($date);
            $tour->status = TourStatus::Draft;
            if ($driver->home_address !== null) {
                $tour->start_address = $driver->home_address;
                $tour->start_lat = $driver->home_lat !== null ? (string) $driver->home_lat : null;
                $tour->start_lng = $driver->home_lng !== null ? (string) $driver->home_lng : null;
                $tour->end_address = $driver->home_address;
                $tour->end_lat = $tour->start_lat;
                $tour->end_lng = $tour->start_lng;
            }
            $tour->save();

            if ($orderIds !== []) {
                $this->assignOrders($tour, $orderIds);
            }

            return $tour->refresh();
        });
    }

    /**
     * @param  list<int>  $orderIds  ordered; tour_position is assigned in this order
     */
    public function assignOrders(Tour $tour, array $orderIds): Tour {
        return DB::transaction(function () use ($tour, $orderIds): Tour {
            // Release previously assigned entries no longer in the list.
            DiaryEntry::query()
                ->where('tour_id', $tour->id)
                ->whereNotIn('id', $orderIds)
                ->update(['tour_id' => null, 'tour_position' => null, 'status' => DiaryStatus::Open->value]);

            $position = 1;
            foreach ($orderIds as $orderId) {
                $entry = DiaryEntry::query()->find($orderId);
                if (! $entry instanceof DiaryEntry) {
                    continue;
                }
                $attrs = [
                    'tour_id' => $tour->id,
                    'tour_position' => $position++,
                ];
                if ($entry->assigned_user_id === null) {
                    $attrs['assigned_user_id'] = $tour->user_id;
                }
                if ($entry->status === DiaryStatus::Open) {
                    $attrs['status'] = DiaryStatus::InProgress;
                }

                // Flex-Auftrag (deadline/window/backlog/recurring) wird beim
                // Einplanen in eine Tour fixiert: Tour-Datum gibt den Termin,
                // time_window_* oder service_minutes liefern die Uhrzeit.
                if ($entry->mode !== Mode::Fixed) {
                    $attrs += $this->fixateForTour($entry, $tour);
                }

                $entry->fill($attrs)->save();
            }

            return $tour->refresh();
        });
    }

    /**
     * Leitet aus dem Tour-Datum, time_window_* und service_minutes ein konkretes
     * start_at/end_at + scheduled_for ab. Wird nur für nicht-fixed Aufträge
     * aufgerufen — der ursprüngliche Modus (Deadline/Window/Backlog) wird auf
     * fixed promoviert, weil der Auftrag jetzt einen festen Slot hat.
     *
     * @return array<string, mixed>
     */
    private function fixateForTour(DiaryEntry $entry, Tour $tour): array {
        $date = $tour->tour_date ?? CarbonImmutable::today();
        $base = CarbonImmutable::parse($date->toDateString());

        $start = $entry->time_window_start
            ? $base->setTimeFromTimeString((string) $entry->time_window_start)
            : $base->setTime(8, 0);

        $duration = $entry->service_minutes ?? 60;
        $end = $entry->time_window_end
            ? $base->setTimeFromTimeString((string) $entry->time_window_end)
            : $start->addMinutes($duration);

        return [
            'mode' => Mode::Fixed,
            'scheduled_for' => $date,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $end->toDateTimeString(),
        ];
    }

    /**
     * Runs the optimizer and persists distance/duration/geometry on the tour.
     * Tries OSRM first; on failure falls back to a haversine matrix so planning
     * stays available offline (geometry will be `null` in that case).
     *
     * @return array{order: list<int>, distance_km: float, duration_minutes: int}
     */
    public function recalculate(Tour $tour): array {
        return DB::transaction(function () use ($tour): array {
            /** @var list<DiaryEntry> $stops */
            $stops = $tour->orderedStops()->get()->all();
            $coords = [];
            $orderIds = [];
            foreach ($stops as $stop) {
                if (! $stop->hasCoordinates()) {
                    continue;
                }
                $coords[] = new Coordinate((float) $stop->address_lat, (float) $stop->address_lng);
                $orderIds[] = (int) $stop->id;
            }

            $start = ($tour->start_lat !== null && $tour->start_lng !== null)
                ? new Coordinate((float) $tour->start_lat, (float) $tour->start_lng)
                : null;
            $end = ($tour->end_lat !== null && $tour->end_lng !== null)
                ? new Coordinate((float) $tour->end_lat, (float) $tour->end_lng)
                : null;

            if ($coords === []) {
                $tour->planned_distance_km = '0.00';
                $tour->planned_duration_minutes = 0;
                $tour->route_geometry = null;
                $tour->save();

                return ['order' => [], 'distance_km' => 0.0, 'duration_minutes' => 0];
            }

            $matrix = $this->buildMatrix($coords, $start, $end);
            $result = $this->optimizer->optimize($coords, $matrix, $start !== null, $end !== null);

            // Persist optimal stop order.
            $reordered = [];
            foreach ($result['order'] as $idx) {
                $reordered[] = $orderIds[$idx];
            }
            $this->assignOrders($tour, $reordered);

            // Try OSRM for a real geometry + duration.
            $distanceMeters = (int) $result['distance'];
            $durationSeconds = (int) $result['duration_estimate'];
            $geometry = null;

            $routePoints = [];
            if ($start !== null) {
                $routePoints[] = $start;
            }
            foreach ($result['order'] as $idx) {
                $routePoints[] = $coords[$idx];
            }
            if ($end !== null) {
                $routePoints[] = $end;
            }

            try {
                if (count($routePoints) >= 2) {
                    $coordPairs = array_map(static fn(Coordinate $c) => [$c->lng, $c->lat], $routePoints);
                    $route = $this->router->route($coordPairs);
                    $distanceMeters = (int) round($route->distanceMeters);
                    $durationSeconds = $route->durationSeconds;
                    $geometry = $route->geometry !== [] ? JsonHelper::encode($route->geometry) : null;
                }
            } catch (RoutingException) {
                // Offline / OSRM down → keep haversine fallback.
            }

            // Add service time of every stop to the planned duration.
            $serviceSeconds = 0;
            foreach ($stops as $stop) {
                $serviceSeconds += (int) $stop->service_minutes * 60;
            }
            $totalSeconds = $durationSeconds + $serviceSeconds;

            $tour->planned_distance_km = (string) round($distanceMeters / 1000, 2);
            $tour->planned_duration_minutes = (int) ceil($totalSeconds / 60);
            $tour->route_geometry = $geometry;
            $tour->save();

            return [
                'order' => $reordered,
                'distance_km' => (float) $tour->planned_distance_km,
                'duration_minutes' => $tour->planned_duration_minutes,
            ];
        });
    }

    /**
     * Builds the optimizer distance matrix. Tries OSRM's `/table` service for
     * real road distances; on failure falls back to the haversine matrix so
     * planning stays available offline. The index layout (0..N-1 stops,
     * N=start, N+1=end) is identical for both sources.
     *
     * @param  array<int, Coordinate>  $coords  ordered stop coordinates
     * @return array<int, array<int, float>>
     */
    private function buildMatrix(array $coords, ?Coordinate $start, ?Coordinate $end): array {
        $points = $coords;
        if ($start !== null) {
            $points[] = $start;
        }
        if ($end !== null) {
            $points[] = $end;
        }

        if (count($points) >= 2) {
            try {
                $coordPairs = array_map(static fn(Coordinate $c) => [$c->lng, $c->lat], $points);

                return $this->router->table($coordPairs);
            } catch (RoutingException) {
                // OSRM down / offline → fall through to haversine.
            }
        }

        return $this->optimizer->haversineMatrix($coords, $start, $end);
    }

    public function plan(Tour $tour): Tour {
        $this->transitionTo($tour, TourStatus::Planned, [TourStatus::Draft]);

        return $tour->refresh();
    }

    public function start(Tour $tour): Tour {
        $this->transitionTo($tour, TourStatus::InProgress, [TourStatus::Draft, TourStatus::Planned]);
        DiaryEntry::query()
            ->where('tour_id', $tour->id)
            ->where('status', DiaryStatus::Open->value)
            ->update(['status' => DiaryStatus::InProgress->value]);

        return $tour->refresh();
    }

    public function complete(Tour $tour): Tour {
        $this->transitionTo($tour, TourStatus::Completed, [TourStatus::InProgress, TourStatus::Planned]);
        DiaryEntry::query()
            ->where('tour_id', $tour->id)
            ->whereIn('status', [DiaryStatus::Open->value, DiaryStatus::InProgress->value])
            ->update(['status' => DiaryStatus::Done->value]);

        return $tour->refresh();
    }

    public function cancel(Tour $tour): Tour {
        $this->transitionTo($tour, TourStatus::Cancelled, [TourStatus::Draft, TourStatus::Planned, TourStatus::InProgress]);

        return $tour->refresh();
    }

    /**
     * Creates one TravelLog per leg of the tour. The first leg goes from the
     * tour's start anchor (driver home) to the first stop, then between
     * subsequent stops, finally optionally back to the end anchor.
     *
     * @return list<TravelLog>
     */
    public function materializeToTravelLogs(Tour $tour): array {
        /** @var list<DiaryEntry> $stops */
        $stops = $tour->orderedStops()->get()->all();
        if ($stops === []) {
            return [];
        }

        $vehicleKind = TravelLogVehicle::Private_->value;
        if ($tour->vehicle_id !== null) {
            $vehicle = $tour->vehicle()->first();
            $vehicleKind = $vehicle?->isRental() === true
                ? TravelLogVehicle::Rental->value
                : TravelLogVehicle::Company->value;
        }

        $legs = [];
        $previous = null;
        $previousAddress = null;
        if ($tour->start_lat !== null && $tour->start_lng !== null) {
            $previous = [(float) $tour->start_lat, (float) $tour->start_lng];
            $previousAddress = $tour->start_address;
        }
        foreach ($stops as $stop) {
            if (! $stop->hasCoordinates()) {
                continue;
            }
            $to = [(float) $stop->address_lat, (float) $stop->address_lng];
            if ($previous !== null) {
                $legs[] = [
                    'from' => $previous,
                    'from_address' => $previousAddress,
                    'to' => $to,
                    'to_address' => trim((string) ($stop->address_line ?? '') . ' ' . ($stop->address_city ?? '')),
                    'stop' => $stop,
                ];
            }
            $previous = $to;
            $previousAddress = $stop->address_line;
        }
        if ($previous !== null && $tour->end_lat !== null && $tour->end_lng !== null) {
            $legs[] = [
                'from' => $previous,
                'from_address' => $previousAddress,
                'to' => [(float) $tour->end_lat, (float) $tour->end_lng],
                'to_address' => $tour->end_address,
                'stop' => null,
            ];
        }

        $logs = [];
        foreach ($legs as $leg) {
            $distance = $this->haversineKm($leg['from'][0], $leg['from'][1], $leg['to'][0], $leg['to'][1]);
            /** @var DiaryEntry|null $stop */
            $stop = $leg['stop'];
            $logs[] = $this->travelLogs->create([
                'organization_id' => $tour->organization_id,
                'user_id' => $tour->user_id,
                'project_id' => $stop?->project_id,
                'customer_id' => $stop?->customer_id,
                'vehicle_id' => $tour->vehicle_id,
                'date' => $tour->tour_date,
                'from_address' => $leg['from_address'],
                'to_address' => $leg['to_address'],
                'from_lat' => $leg['from'][0],
                'from_lng' => $leg['from'][1],
                'to_lat' => $leg['to'][0],
                'to_lng' => $leg['to'][1],
                'distance_km' => round($distance, 2),
                'vehicle' => $vehicleKind,
                'purpose' => $stop !== null
                    ? __('travel.tour_leg_purpose', ['title' => $stop->title])
                    : __('travel.tour_return_purpose'),
                'round_trip' => false,
                'reimbursable' => true,
            ]);
        }

        return $logs;
    }

    /**
     * @param  list<TourStatus>  $allowedFrom
     */
    private function transitionTo(Tour $tour, TourStatus $target, array $allowedFrom): void {
        if (! in_array($tour->status, $allowedFrom, true)) {
            throw new RuntimeException(sprintf(
                'Cannot transition tour from "%s" to "%s".',
                $tour->status->value,
                $target->value
            ));
        }
        $tour->status = $target;
        $tour->save();
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earth = 6_371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1.0, sqrt($h)));
    }
}
