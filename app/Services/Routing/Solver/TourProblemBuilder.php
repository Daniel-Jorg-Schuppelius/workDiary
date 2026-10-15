<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourProblemBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

use App\Enums\Diary\Status as DiaryStatus;
use App\Models\Diary\{DiaryEntry, Tour, TourStop};
use App\Models\Hr\Qualification;
use App\Models\Platform\{Organization, User};
use App\Services\Routing\{Coordinate, OsrmRouter, RoutingException, ServiceDurationSuggester, TourOptimizer};
use App\Services\Schedule\{QualificationGate, WorkWindowResolver};
use App\Services\Timekeeping\BreakRuleEvaluator;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Baut das Solver-Problem aus dem Bestand (MVP-1175): Fahrer mit Anker,
 * Arbeitsfenster (Schicht, Verfügbarkeit, Vorgabe), Pausenregeln und
 * Qualifikationen; Aufträge mit Koordinaten (eigene oder Kunde), Dauer,
 * Uhrzeitfenster und Priorität; Matrix aus OSRM `/table`, sonst Luftlinie
 * mit 40 km/h (gekennzeichnet).
 */
class TourProblemBuilder {
    public const DEFAULT_SERVICE_MINUTES = 60;

    private const FALLBACK_SPEED_MPS = 40_000 / 3600;

    public function __construct(
        private readonly OsrmRouter $router,
        private readonly TourOptimizer $optimizer,
        private readonly WorkWindowResolver $workWindows,
        private readonly BreakRuleEvaluator $breaks,
        private readonly QualificationGate $qualifications,
        private readonly ServiceDurationSuggester $durations,
    ) {}

    /**
     * Problem einer Tour: ihr Fahrer mit Start-/Zielanker und ihre Stopps.
     * Mit `$remainderOnly` (MVP-1180) nur die offenen Stopps einer laufenden
     * Tour: Start ist die Plan-Position des letzten erledigten Stopps, der
     * Arbeitsbeginn frühestens jetzt — ohne Live-Ortung (E2).
     */
    public function forTour(Tour $tour, bool $remainderOnly = false): TourProblem {
        /** @var Organization $organization */
        $organization = $tour->organization;
        /** @var User $driver */
        $driver = $tour->user;
        $timezone = Tz::ofOrganization($organization);
        $date = CarbonImmutable::parse((string) $tour->tour_date?->toDateString(), $timezone);
        $midnight = CarbonImmutable::parse($date->toDateString(), $timezone)->startOfDay();
        $start = $tour->start_lat !== null && $tour->start_lng !== null ? ['lat' => (float) $tour->start_lat, 'lng' => (float) $tour->start_lng] : null;
        $end = $tour->end_lat !== null && $tour->end_lng !== null ? ['lat' => (float) $tour->end_lat, 'lng' => (float) $tour->end_lng] : null;
        $workStart = $tour->planned_start_at !== null
            ? (int) $midnight->diffInSeconds(CarbonImmutable::instance($tour->planned_start_at)->setTimezone($timezone), false)
            : null;

        $stops = $tour->orderedStops()->with(['customer:id,address_lat,address_lng', 'requiredQualifications'])->get();
        if ($remainderOnly) {
            $departed = TourStop::query()->where('tour_id', $tour->id)->whereNotNull('departed_at')->pluck('diary_entry_id')->map(static fn($id): int => (int) $id)->all();
            $isFixed = static fn(DiaryEntry $entry): bool => in_array((int) $entry->id, $departed, true)
                || in_array($entry->status, [DiaryStatus::Done, DiaryStatus::AcceptedFinal, DiaryStatus::Invoiced], true);
            $lastFixed = $stops->filter($isFixed)->last();
            if ($lastFixed instanceof DiaryEntry && ($point = $lastFixed->planningCoordinates()) !== null) {
                $start = $point;
                // Abfahrt vom letzten erledigten Stopp: Ist, sonst Plan — nur wenn sie am Tourtag liegt.
                $lastStop = TourStop::query()->where('tour_id', $tour->id)->where('diary_entry_id', $lastFixed->id)->first();
                $departure = $lastStop !== null ? ($lastStop->departed_at ?? $lastStop->planned_departure_at) : null;
                if ($departure !== null && CarbonImmutable::instance($departure)->setTimezone($timezone)->isSameDay($date)) {
                    $workStart = (int) $midnight->diffInSeconds(CarbonImmutable::instance($departure)->setTimezone($timezone), false);
                }
            }
            $now = CarbonImmutable::now($timezone);
            if ($now->isSameDay($date)) {
                $workStart = max((int) ($workStart ?? 0), (int) $midnight->diffInSeconds($now, false));
            }
            $stops = $stops->reject($isFixed)->values();
        }

        return $this->build($organization, $date, [
            ['user' => $driver, 'start' => $start, 'end' => $end, 'work_start' => $workStart, 'label' => (string) ($tour->name ?? ('#' . $tour->id))],
        ], $stops);
    }

    /**
     * Problem eines Tages (MVP-1179): mehrere Fahrer ab Zuhause, Aufträge der Auswahl.
     *
     * @param  Collection<int, User>  $drivers
     * @param  Collection<int, DiaryEntry>  $entries
     */
    public function forDay(Organization $organization, CarbonImmutable $date, Collection $drivers, Collection $entries): TourProblem {
        $specs = [];
        foreach ($drivers as $driver) {
            $home = $driver->home_lat !== null && $driver->home_lng !== null ? ['lat' => (float) $driver->home_lat, 'lng' => (float) $driver->home_lng] : null;
            $specs[] = ['user' => $driver, 'start' => $home, 'end' => $home, 'work_start' => null, 'label' => (string) $driver->name];
        }

        return $this->build($organization, $date->setTimezone(Tz::ofOrganization($organization)), $specs, $entries->load(['customer:id,address_lat,address_lng', 'requiredQualifications']));
    }

    /**
     * @param  list<array{user: User, start: array{lat: float, lng: float}|null, end: array{lat: float, lng: float}|null, work_start: int|null, label: string}>  $driverSpecs
     * @param  Collection<int, DiaryEntry>  $entries
     */
    private function build(Organization $organization, CarbonImmutable $date, array $driverSpecs, Collection $entries): TourProblem {
        $timezone = Tz::ofOrganization($organization);
        $points = [];
        $jobs = [];
        $unlocated = [];
        $requiredIds = [];
        foreach ($entries as $entry) {
            $coordinates = $entry->planningCoordinates();
            if ($coordinates === null) {
                $unlocated[] = (int) $entry->id;

                continue;
            }
            $points[] = new Coordinate($coordinates['lat'], $coordinates['lng']);
            $required = array_values($entry->requiredQualifications->pluck('id')->map(static fn($id): int => (int) $id)->all());
            $requiredIds = [...$requiredIds, ...$required];
            $jobs[] = new SolverJob(
                id: (int) $entry->id,
                index: count($points) - 1,
                serviceSeconds: $this->serviceSeconds($entry),
                windowStart: $entry->time_window_start !== null ? WorkWindowResolver::seconds((string) $entry->time_window_start) : null,
                windowEnd: $entry->time_window_end !== null ? WorkWindowResolver::seconds((string) $entry->time_window_end) : null,
                requiredQualificationIds: $required,
                priority: $this->priority($entry),
                label: (string) $entry->title,
            );
        }

        $requiredQualifications = $requiredIds !== []
            ? Qualification::query()->whereIn('id', array_values(array_unique($requiredIds)))->get()
            : collect();
        $breakRules = array_values($this->breaks->rules());

        $vehicles = [];
        foreach ($driverSpecs as $spec) {
            $user = $spec['user'];
            $startIndex = null;
            $endIndex = null;
            if ($spec['start'] !== null) {
                $points[] = new Coordinate($spec['start']['lat'], $spec['start']['lng']);
                $startIndex = count($points) - 1;
            }
            if ($spec['end'] !== null) {
                $points[] = new Coordinate($spec['end']['lat'], $spec['end']['lng']);
                $endIndex = count($points) - 1;
            }
            [$workStart, $workEnd] = $this->workWindows->spanFor($user, $date);
            if ($spec['work_start'] !== null) {
                $workStart = $spec['work_start'];
                $workEnd = max($workEnd, $workStart);
            }
            $held = [];
            if ($requiredQualifications->isNotEmpty()) {
                foreach ($this->qualifications->statusFor($user, $requiredQualifications, $date) as $qualificationId => $status) {
                    if ($status !== 'missing') {
                        $held[] = (int) $qualificationId;
                    }
                }
            }
            $vehicles[] = new SolverVehicle((int) $user->id, $startIndex, $endIndex, $workStart, $workEnd, $held, $breakRules, $spec['label']);
        }

        [$matrix, $isEstimate] = $this->matrix($points);

        return new TourProblem($date, $timezone, $vehicles, $jobs, $matrix, $isEstimate, $unlocated);
    }

    /**
     * @param  list<Coordinate>  $points
     * @return array{0: CostMatrix, 1: bool}
     */
    private function matrix(array $points): array {
        if (count($points) >= 2) {
            try {
                $table = $this->router->table(array_map(static fn(Coordinate $c): array => $c->toLngLat(), $points));

                return [new CostMatrix($table->durations, $table->distances), false];
            } catch (RoutingException) {
                // OSRM nicht erreichbar → Luftlinie, gekennzeichnet.
            }
        }
        $distances = $this->optimizer->haversineMatrix($points);
        $durations = array_map(static fn(array $row): array => array_map(static fn(float $m): float => $m / self::FALLBACK_SPEED_MPS, $row), $distances);

        return [new CostMatrix($durations, $distances), true];
    }

    private function serviceSeconds(DiaryEntry $entry): int {
        $minutes = (int) ($entry->planned_minutes ?? 0);
        if ($minutes <= 0) {
            $minutes = (int) ($entry->service_minutes ?? 0);
        }
        if ($minutes <= 0) {
            // Erfahrungswert aus Ist-Verweildauern (MVP-1184), sonst Vorgabe.
            $minutes = (int) ($this->durations->suggestMinutes($entry->entry_type_id !== null ? (int) $entry->entry_type_id : null, $entry->customer_id !== null ? (int) $entry->customer_id : null) ?? 0);
        }

        return ($minutes > 0 ? $minutes : self::DEFAULT_SERVICE_MINUTES) * 60;
    }

    private function priority(DiaryEntry $entry): int {
        return match ($entry->priority?->value) {
            'urgent' => 30,
            'high' => 20,
            'normal', null => 10,
            default => 0,
        };
    }
}
