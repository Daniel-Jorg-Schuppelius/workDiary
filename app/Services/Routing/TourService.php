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

use App\Enums\Diary\{DispatchStatus, Mode, Status as DiaryStatus};
use App\Enums\Tour\TourStatus;
use App\Enums\Travel\TravelLogVehicle;
use App\Events\Tour\TourStarted;
use App\Exceptions\TourAlreadyMaterializedException;
use App\Models\Diary\{DiaryEntry, Tour, TourStop};
use App\Models\Platform\{Organization, User};
use App\Models\Travel\TravelLog;
use App\Services\AssetCompliance\Contracts\InspectionTourPlanner;
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Dispatch\DispatchStatusResolver;
use App\Services\Licensing\ModuleStatusResolver;
use App\Services\Routing\Contracts\TravelLogRecorder;
use App\Support\Tz;
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\Geo\GeoHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * High-level orchestration around the {@see Tour} aggregate: creation,
 * assignment of {@see DiaryEntry} stops (typed via EntryType), optimisation
 * via {@see TourOptimizer} (with optional OSRM-backed routing) and the final
 * hand-off to {@see TravelLogRecorder} which materialises the tour as actual
 * travel logs.
 */
class TourService implements InspectionTourPlanner {
    use AssertsStatusTransition;
    public function __construct(
        private readonly TourOptimizer $optimizer,
        private readonly OsrmRouter $router,
        private readonly TravelLogRecorder $travelLogs,
        private readonly ModuleStatusResolver $modules,
    ) {}

    public function available(Organization $organization): bool {
        return $this->modules->isActiveFor($organization, 'module.planung');
    }

    /** Prüfertour (MVP-918): Entwurf anlegen und die Reihenfolge optimieren. */
    public function planTour(User $driver, CarbonInterface $date, array $orderIds): Tour {
        $tour = $this->createDraft($driver, $date, $orderIds);
        $this->recalculate($tour);

        return $tour->refresh();
    }

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
            $this->releaseEntries(DiaryEntry::query()->where('tour_id', $tour->id)->whereNotIn('id', $orderIds));

            $position = 1;
            foreach ($orderIds as $orderId) {
                $entry = DiaryEntry::query()->find($orderId);
                // Aufträge einer anderen Tour bleiben dort (Sicherheitsaudit 2026-09-17, authz-tour-1).
                if (! $entry instanceof DiaryEntry || ($entry->tour_id !== null && (int) $entry->tour_id !== (int) $tour->id)) {
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

            $this->syncStops($tour);

            return $tour->refresh();
        });
    }

    /**
     * Stoppzeilen (MVP-1174) folgen der Zuweisung: eine je Auftrag der Tour in
     * Positionsreihenfolge; weggefallene Stopps verschwinden, geplante Zeiten
     * bleiben an den übrigen stehen.
     */
    public function syncStops(Tour $tour): void {
        $entries = DiaryEntry::query()->where('tour_id', $tour->id)
            ->orderByRaw('tour_position IS NULL')->orderBy('tour_position')->orderBy('id')
            ->get(['id', 'tour_position']);
        $ids = $entries->pluck('id')->map(static fn($id): int => (int) $id)->all();
        TourStop::query()->where('tour_id', $tour->id)->whereNotIn('diary_entry_id', $ids)->delete();
        foreach ($entries as $position => $entry) {
            TourStop::query()->updateOrCreate(
                ['tour_id' => $tour->id, 'diary_entry_id' => (int) $entry->id],
                ['organization_id' => $tour->organization_id, 'position' => $position + 1],
            );
        }
    }

    /**
     * Löst Aufträge aus einer Tour. Zurückgesetzt wird nur der Status, den die
     * Tour selbst gesetzt hat (In Bearbeitung → Offen); abgenommene,
     * abgerechnete oder erledigte Aufträge behalten ihren Stand
     * (Sicherheitsaudit 2026-09-17, authz-tour-1).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<DiaryEntry>  $entries
     */
    public function releaseEntries(\Illuminate\Database\Eloquent\Builder $entries): void {
        (clone $entries)->where('status', DiaryStatus::InProgress->value)->update(['status' => DiaryStatus::Open->value]);
        $entries->update(['tour_id' => null, 'tour_position' => null]);
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
        // Zeitfenster sind Ortszeit, start_at/end_at UTC (UI-Fuzz 2026-09-21).
        $organization = $entry->organization ?? $tour->organization;
        $base = CarbonImmutable::parse($date->toDateString(), $organization !== null ? Tz::ofOrganization($organization) : Tz::current());

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
            'start_at' => $start->utc()->toDateTimeString(),
            'end_at' => $end->utc()->toDateTimeString(),
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
            $stops = $tour->orderedStops()->with('customer:id,address_lat,address_lng')->get()->all();
            $coords = [];
            $orderIds = [];
            $unlocated = [];
            foreach ($stops as $stop) {
                $point = $stop->planningCoordinates();
                if ($point === null) {
                    $unlocated[] = (int) $stop->id;

                    continue;
                }
                $coords[] = new Coordinate($point['lat'], $point['lng']);
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
                $tour->route_legs = null;
                $tour->save();

                return ['order' => $unlocated, 'distance_km' => 0.0, 'duration_minutes' => 0];
            }

            $matrix = $this->buildMatrix($coords, $start, $end);
            $result = $this->optimizer->optimize($coords, $matrix, $start !== null, $end !== null);

            // Optimierte Reihenfolge; Stopps ohne Koordinaten bleiben am Ende in der Tour (MVP-1170).
            $reordered = [];
            foreach ($result['order'] as $idx) {
                $reordered[] = $orderIds[$idx];
            }
            $reordered = [...$reordered, ...$unlocated];
            $this->assignOrders($tour, $reordered);

            // Try OSRM for a real geometry + duration.
            $distanceMeters = (int) $result['distance'];
            $durationSeconds = (int) $result['duration_estimate'];
            $geometry = null;
            $legs = null;

            $routePoints = [];
            $pointKeys = [];
            if ($start !== null) {
                $routePoints[] = $start;
                $pointKeys[] = 'start';
            }
            foreach ($result['order'] as $idx) {
                $routePoints[] = $coords[$idx];
                $pointKeys[] = (string) $orderIds[$idx];
            }
            if ($end !== null) {
                $routePoints[] = $end;
                $pointKeys[] = 'end';
            }

            try {
                if (count($routePoints) >= 2) {
                    $coordPairs = array_map(static fn(Coordinate $c) => $c->toLngLat(), $routePoints);
                    $route = $this->router->route($coordPairs);
                    $distanceMeters = (int) round($route->distanceMeters);
                    $durationSeconds = $route->durationSeconds;
                    $geometry = $route->geometry !== [] ? JsonHelper::encode($route->geometry) : null;
                    $legs = $this->legsFromRoute($route, $pointKeys);
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
            $tour->route_legs = $legs;
            $tour->save();

            return [
                'order' => $reordered,
                'distance_km' => (float) $tour->planned_distance_km,
                'duration_minutes' => $tour->planned_duration_minutes,
            ];
        });
    }

    /**
     * Route, Abschnitte und Kennzahlen für die aktuelle Stopp-Reihenfolge
     * nachführen, ohne neu zu sortieren (Übernahme eines Vorschlags, MVP-1176).
     * Geplante Dauer und Startzeit der Tour bleiben, wenn sie der Vorschlag
     * gesetzt hat; ohne OSRM bleibt die Geometrie leer.
     */
    public function refreshRoute(Tour $tour): void {
        /** @var list<DiaryEntry> $stops */
        $stops = $tour->orderedStops()->with('customer:id,address_lat,address_lng')->get()->all();
        $points = [];
        $keys = [];
        if ($tour->start_lat !== null && $tour->start_lng !== null) {
            $points[] = new Coordinate((float) $tour->start_lat, (float) $tour->start_lng);
            $keys[] = 'start';
        }
        foreach ($stops as $stop) {
            $point = $stop->planningCoordinates();
            if ($point === null) {
                continue;
            }
            $points[] = new Coordinate($point['lat'], $point['lng']);
            $keys[] = (string) $stop->id;
        }
        if ($tour->end_lat !== null && $tour->end_lng !== null) {
            $points[] = new Coordinate((float) $tour->end_lat, (float) $tour->end_lng);
            $keys[] = 'end';
        }

        $geometry = null;
        $legs = null;
        if (count($points) >= 2) {
            try {
                $route = $this->router->route(array_map(static fn(Coordinate $c) => $c->toLngLat(), $points));
                $geometry = $route->geometry !== [] ? JsonHelper::encode($route->geometry) : null;
                $legs = $this->legsFromRoute($route, $keys);
                $tour->planned_distance_km = (string) round($route->distanceMeters / 1000, 2);
            } catch (RoutingException) {
                // Offline → Geometrie leer, Kennzahlen des Vorschlags bleiben.
            }
        }
        $tour->route_geometry = $geometry;
        $tour->route_legs = $legs;
        $tour->save();
    }

    /**
     * Abschnitte der Route je Bein (Schlüssel 'start', Stopp-ID, 'end'), damit
     * das Fahrtenbuch Straßenkilometer je Bein bekommt (MVP-1172).
     *
     * @param  list<string>  $pointKeys
     * @return list<array{from: string, to: string, distance_m: int, duration_s: int}>|null
     */
    private function legsFromRoute(RouteResult $route, array $pointKeys): ?array {
        if ($route->legs === [] || count($route->legs) !== count($pointKeys) - 1) {
            return null;
        }
        $legs = [];
        foreach (array_values($route->legs) as $i => $leg) {
            $legs[] = [
                'from' => $pointKeys[$i],
                'to' => $pointKeys[$i + 1],
                'distance_m' => (int) round((float) ($leg['distance'] ?? 0)),
                'duration_s' => (int) round((float) ($leg['duration'] ?? 0)),
            ];
        }

        return $legs;
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
                $coordPairs = array_map(static fn(Coordinate $c) => $c->toLngLat(), $points);

                return $this->router->table($coordPairs)->distances;
            } catch (RoutingException) {
                // OSRM down / offline → fall through to haversine.
            }
        }

        return $this->optimizer->haversineMatrix($coords, $start, $end);
    }

    public function plan(Tour $tour): Tour {
        $this->changeStatus($tour, TourStatus::Planned);

        return $tour->refresh();
    }

    public function start(Tour $tour): Tour {
        $this->changeStatus($tour, TourStatus::InProgress);
        DiaryEntry::query()
            ->where('tour_id', $tour->id)
            ->where('status', DiaryStatus::Open->value)
            ->update(['status' => DiaryStatus::InProgress->value]);
        TourStarted::dispatch($tour);

        return $tour->refresh();
    }

    /**
     * Tour beenden (MVP-1183): erledigt sind nur Stopps mit Ist „erledigt“;
     * offene oder nicht angetroffene Aufträge behalten ihren Stand und bleiben
     * für die Disposition sichtbar (vorher wurden alle auf erledigt gesetzt).
     */
    public function complete(Tour $tour): Tour {
        $this->changeStatus($tour, TourStatus::Completed);

        return $tour->refresh();
    }

    /** Ankunft am Stopp (MVP-1183), manuell aus der Fahreransicht oder offline nachgereicht. */
    public function arriveAtStop(TourStop $stop, User $actor, ?CarbonInterface $at = null): TourStop {
        $at ??= Carbon::now();
        $stop->forceFill(['arrived_at' => $at, 'actual_source' => TourStop::SOURCE_MANUAL])->save();
        $entry = $stop->diaryEntry;
        if ($entry instanceof DiaryEntry) {
            if ($entry->status === DiaryStatus::Open) {
                $entry->forceFill(['status' => DiaryStatus::InProgress])->save();
            }
            app(DispatchStatusResolver::class)->transition($entry, DispatchStatus::EnRoute);
        }
        $stop->tour?->record('tour.stop_arrived', ['stop_id' => $stop->id, 'diary_entry_id' => $stop->diary_entry_id, 'at' => $at->toIso8601String()], $actor, $at);

        return $stop->refresh();
    }

    /**
     * Abfahrt vom Stopp (MVP-1183): `done` schließt den Auftrag ab, `not_met`
     * lässt ihn offen (Kunde nicht angetroffen) — beides mit Zeitstempel.
     */
    public function departStop(TourStop $stop, User $actor, string $outcome, ?CarbonInterface $at = null): TourStop {
        if (! in_array($outcome, [TourStop::OUTCOME_DONE, TourStop::OUTCOME_NOT_MET], true)) {
            throw new \InvalidArgumentException('Unbekanntes Ergebnis: ' . $outcome);
        }
        $at ??= Carbon::now();
        $stop->forceFill([
            'arrived_at' => $stop->arrived_at ?? $at,
            'departed_at' => $at,
            'actual_source' => $stop->actual_source ?? TourStop::SOURCE_MANUAL,
            'outcome' => $outcome,
        ])->save();
        $entry = $stop->diaryEntry;
        if ($entry instanceof DiaryEntry && $outcome === TourStop::OUTCOME_DONE) {
            if (in_array($entry->status, [DiaryStatus::Open, DiaryStatus::InProgress], true)) {
                $entry->forceFill(['status' => DiaryStatus::Done])->save();
            }
            app(DispatchStatusResolver::class)->transition($entry, DispatchStatus::Done);
        }
        $stop->tour?->record('tour.stop_departed', ['stop_id' => $stop->id, 'diary_entry_id' => $stop->diary_entry_id, 'outcome' => $outcome, 'at' => $at->toIso8601String()], $actor, $at);

        return $stop->refresh();
    }

    public function cancel(Tour $tour): Tour {
        $this->changeStatus($tour, TourStatus::Cancelled);

        return $tour->refresh();
    }

    /**
     * Creates one TravelLog per leg of the tour (MVP-1172): start anchor → first
     * stop → … → end anchor. Distance and duration come from the stored route
     * legs (haversine when unknown, marked in the purpose), times from the stop
     * schedule, the odometer chain from `$odometerStartKm` (logbook vehicles).
     * Runs once per tour.
     *
     * @return list<TravelLog>
     *
     * @throws TourAlreadyMaterializedException
     * @throws \App\Exceptions\LogbookViolationException
     */
    public function materializeToTravelLogs(Tour $tour, ?int $odometerStartKm = null): array {
        /** @var list<DiaryEntry> $stops */
        $stops = $tour->orderedStops()->with('customer:id,address_lat,address_lng')->get()->all();
        if ($stops === [] || ! $this->travelLogs->available()) {
            return [];
        }
        if ($tour->travelLogs()->exists()) {
            throw new TourAlreadyMaterializedException($tour);
        }

        $vehicleKind = TravelLogVehicle::Private_->value;
        if ($tour->vehicle_id !== null) {
            $vehicle = $tour->vehicle()->first();
            $vehicleKind = $vehicle?->isRental() === true
                ? TravelLogVehicle::Rental->value
                : TravelLogVehicle::Company->value;
        }

        $routeLegs = [];
        foreach ($tour->route_legs ?? [] as $leg) {
            $routeLegs[$leg['from'] . '>' . $leg['to']] = $leg;
        }

        $legs = [];
        $previous = null;
        $previousAddress = null;
        $previousKey = 'start';
        $previousDeparture = null;
        if ($tour->start_lat !== null && $tour->start_lng !== null) {
            $previous = [(float) $tour->start_lat, (float) $tour->start_lng];
            $previousAddress = $tour->start_address;
        }
        foreach ($stops as $stop) {
            $point = $stop->planningCoordinates();
            if ($point === null) {
                continue;
            }
            $to = [$point['lat'], $point['lng']];
            if ($previous !== null) {
                $legs[] = [
                    'from' => $previous,
                    'from_address' => $previousAddress,
                    'to' => $to,
                    'to_address' => trim((string) ($stop->address_line ?? '') . ' ' . ($stop->address_city ?? '')),
                    'stop' => $stop,
                    'route' => $routeLegs[$previousKey . '>' . $stop->id] ?? null,
                    'arrival' => $stop->start_at,
                    'departure' => $previousDeparture,
                ];
            }
            $previous = $to;
            $previousAddress = $stop->address_line;
            $previousKey = (string) $stop->id;
            $previousDeparture = $stop->end_at ?? $stop->start_at;
        }
        if ($previous !== null && $tour->end_lat !== null && $tour->end_lng !== null) {
            $legs[] = [
                'from' => $previous,
                'from_address' => $previousAddress,
                'to' => [(float) $tour->end_lat, (float) $tour->end_lng],
                'to_address' => $tour->end_address,
                'stop' => null,
                'route' => $routeLegs[$previousKey . '>end'] ?? null,
                'arrival' => null,
                'departure' => $previousDeparture,
            ];
        }

        $logs = [];
        $odometer = $odometerStartKm;
        $odometerExact = (float) ($odometerStartKm ?? 0);
        foreach ($legs as $leg) {
            $route = $leg['route'];
            $distanceKm = $route !== null
                ? round($route['distance_m'] / 1000, 2)
                : round(GeoHelper::haversineKm($leg['from'][0], $leg['from'][1], $leg['to'][0], $leg['to'][1]), 2);
            $durationSeconds = $route !== null ? (int) $route['duration_s'] : null;
            [$startedAt, $endedAt] = $this->legTimes($leg['arrival'], $leg['departure'], $durationSeconds);
            /** @var DiaryEntry|null $stop */
            $stop = $leg['stop'];
            $purpose = $stop !== null
                ? (string) __('travel.tour_leg_purpose', ['title' => $stop->title])
                : (string) __('travel.tour_return_purpose');
            if ($route === null) {
                $purpose .= ' ' . __('travel.tour_leg_straight_line');
            }

            $attributes = [
                'organization_id' => $tour->organization_id,
                'user_id' => $tour->user_id,
                'project_id' => $stop?->project_id,
                'customer_id' => $stop?->customer_id,
                'vehicle_id' => $tour->vehicle_id,
                'tour_id' => $tour->id,
                'date' => $tour->tour_date,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'duration_minutes' => $durationSeconds !== null ? (int) ceil($durationSeconds / 60) : 0,
                'from_address' => $leg['from_address'],
                'to_address' => $leg['to_address'],
                'from_lat' => $leg['from'][0],
                'from_lng' => $leg['from'][1],
                'to_lat' => $leg['to'][0],
                'to_lng' => $leg['to'][1],
                'distance_km' => $distanceKm,
                'vehicle' => $vehicleKind,
                'purpose' => $purpose,
                'round_trip' => false,
                'reimbursable' => true,
            ];
            if ($odometer !== null) {
                // Der Tacho zählt ganze km: Bein-Distanz = Differenz der Stände,
                // Rundung läuft über die Tour mit, damit die Kette lückenlos bleibt.
                $odometerExact += $distanceKm;
                $end = (int) round($odometerExact);
                $attributes['odometer_start_km'] = $odometer;
                $attributes['odometer_end_km'] = $end;
                $attributes['distance_km'] = (float) ($end - $odometer);
                $odometer = $end;
            }
            $logs[] = $this->travelLogs->create($attributes);
        }
        $tour->record('tour.trips_created', ['count' => count($logs), 'odometer_start_km' => $odometerStartKm], Auth::id());

        return $logs;
    }

    /**
     * Zeiten eines Beins aus der Planung: Ankunft am Stopp (start_at) oder
     * Abfahrt vom vorigen Stopp, jeweils mit der Fahrzeit des Abschnitts.
     *
     * @return array{0: CarbonInterface|null, 1: CarbonInterface|null}
     */
    private function legTimes(?CarbonInterface $arrival, ?CarbonInterface $departure, ?int $durationSeconds): array {
        if ($durationSeconds === null) {
            return [null, null];
        }
        if ($arrival !== null) {
            return [$arrival->copy()->subSeconds($durationSeconds), $arrival];
        }
        if ($departure !== null) {
            return [$departure, $departure->copy()->addSeconds($durationSeconds)];
        }

        return [null, null];
    }

    private function changeStatus(Tour $tour, TourStatus $target): void {
        $this->assertStatusTransition($tour->status, $target);
        $tour->status = $target;
        $tour->save();

        $event = match ($target) {
            TourStatus::InProgress => 'tour.started',
            TourStatus::Completed => 'tour.completed',
            TourStatus::Cancelled => 'tour.cancelled',
            default => null,
        };
        if ($event !== null) {
            $tour->record($event, [], Auth::id());
        }
    }
}
