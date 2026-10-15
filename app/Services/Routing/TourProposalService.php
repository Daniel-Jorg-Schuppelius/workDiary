<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourProposalService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing;

use App\Enums\Diary\{Mode, Status as DiaryStatus};
use App\Enums\Tour\{TourProposalStatus, TourStatus};
use App\Jobs\Routing\ComputeTourProposal;
use App\Models\Diary\{DiaryEntry, Tour, TourProposal, TourStop};
use App\Models\Platform\{Organization, User};
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Routing\Solver\Contracts\TourSolver;
use App\Services\Routing\Solver\{PlannedRoute, TourPlan, TourProblem, TourProblemBuilder, UnassignedJob};
use App\Support\{ErrorText, Tz};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tourvorschlag (MVP-1176): anfordern (Datensatz, Berechnung im Job),
 * rechnen (Problem → Solver → Plan mit Kennzahlen), übernehmen (Reihenfolge,
 * Stopp-Zeiten, Auftragszeiten, Tour-Startzeit, Route) oder mit Grund
 * verwerfen. Nie schreibt ein Lauf ohne Übernahme in die Tour. Dazu der
 * Tagesvorschlag der Leitstelle für mehrere Fahrer (MVP-1179) und die
 * Neuplanung des Rests einer laufenden Tour (MVP-1180).
 */
class TourProposalService {
    use AssertsStatusTransition;

    public const KIND_TOUR = 'tour';

    public const KIND_REMAINDER = 'remainder';

    public const KIND_DAY = 'day';

    /** Aufträge je Tagesvorschlag. */
    public const MAX_DAY_CANDIDATES = 200;

    public function __construct(
        private readonly TourProblemBuilder $builder,
        private readonly TourSolver $solver,
        private readonly TourService $tours,
    ) {}

    public function request(Tour $tour, User $actor, bool $remainderOnly = false): TourProposal {
        if ($remainderOnly && $tour->status !== TourStatus::InProgress) {
            throw new RuntimeException((string) __('Der Rest lässt sich nur bei einer laufenden Tour neu planen.'));
        }
        $proposal = TourProposal::create([
            'organization_id' => $tour->organization_id,
            'tour_id' => $tour->id,
            'proposal_date' => $tour->tour_date,
            'problem' => ['kind' => $remainderOnly ? self::KIND_REMAINDER : self::KIND_TOUR],
            'status' => TourProposalStatus::Computing,
            'created_by' => $actor->id,
        ]);
        $tour->record('tour.proposal_requested', ['proposal_id' => $proposal->id, 'remainder' => $remainderOnly], $actor);
        ComputeTourProposal::dispatch((int) $proposal->id);

        return $proposal;
    }

    /**
     * Tagesvorschlag der Leitstelle (MVP-1179): Fahrer und Aufträge eines Tages.
     *
     * @param  list<int>  $driverIds
     * @param  list<int>  $entryIds
     */
    public function requestForDay(Organization $organization, CarbonImmutable $date, array $driverIds, array $entryIds, User $actor): TourProposal {
        if ($driverIds === [] || $entryIds === []) {
            throw new RuntimeException((string) __('Für einen Tagesvorschlag braucht es mindestens einen Fahrer und einen Auftrag.'));
        }
        $proposal = TourProposal::create([
            'organization_id' => $organization->id,
            'tour_id' => null,
            'proposal_date' => $date->toDateString(),
            'problem' => ['kind' => self::KIND_DAY, 'driver_ids' => $driverIds, 'entry_ids' => $entryIds],
            'status' => TourProposalStatus::Computing,
            'created_by' => $actor->id,
        ]);
        ComputeTourProposal::dispatch((int) $proposal->id);

        return $proposal;
    }

    /**
     * Aufträge, die an einem Tag für den Tagesvorschlag in Frage kommen:
     * offen, ohne Tour, Auftragsart tourfähig, Korridor trifft den Tag,
     * Koordinaten vorhanden (eigene oder Kunde).
     *
     * @return Collection<int, DiaryEntry>
     */
    public function dayCandidates(CarbonImmutable $date): Collection {
        $day = $date->toDateString();

        return DiaryEntry::query()
            ->whereNull('tour_id')
            ->whereIn('status', [DiaryStatus::Open->value, DiaryStatus::Problem->value])
            ->where('is_archived', false)
            ->whereHas('entryType', fn($q) => $q->where('allow_tour', true))
            ->where(function ($q) use ($day): void {
                $q->where(fn($sub) => $sub->where('mode', Mode::Fixed->value)->whereBetween('scheduled_for', DateRange::days($day, $day)));
                $q->orWhere(fn($sub) => $sub->where('mode', Mode::Deadline->value)->where('due_date', '>=', DateRange::day($day)));
                $q->orWhere(fn($sub) => $sub->where('mode', Mode::Window->value)->where('window_start_date', '<', DateRange::dayAfter($day))->where('window_end_date', '>=', DateRange::day($day)));
                $q->orWhere('mode', Mode::Backlog->value);
                $q->orWhere(fn($sub) => $sub->where('mode', Mode::Recurring->value)->where('due_date', '<', DateRange::dayAfter($day)));
            })
            ->with(['customer:id,name,address_lat,address_lng', 'requiredQualifications'])
            ->orderByRaw('scheduled_for IS NULL')
            ->orderBy('scheduled_for')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(self::MAX_DAY_CANDIDATES)
            ->get()
            ->filter(static fn(DiaryEntry $entry): bool => $entry->planningCoordinates() !== null)
            ->values();
    }

    /** Rechnet einen angeforderten Vorschlag; Fehler landen im Datensatz, nicht beim Nutzer. */
    public function compute(TourProposal $proposal): void {
        if ($proposal->status !== TourProposalStatus::Computing) {
            return;
        }
        $request = $proposal->problem ?? [];
        try {
            $tour = $proposal->tour;
            if ($tour instanceof Tour) {
                $problem = $this->builder->forTour($tour, ($request['kind'] ?? self::KIND_TOUR) === self::KIND_REMAINDER);
            } else {
                $organization = $proposal->organization;
                if (! $organization instanceof Organization) {
                    throw new RuntimeException('Organisation des Vorschlags fehlt.');
                }
                $drivers = User::forOrganization($organization->id)->whereIn('id', (array) ($request['driver_ids'] ?? []))->get();
                $entries = DiaryEntry::query()->whereIn('id', (array) ($request['entry_ids'] ?? []))->get();
                $problem = $this->builder->forDay($organization, CarbonImmutable::parse((string) $proposal->proposal_date->toDateString()), $drivers, $entries);
            }
            $plan = $this->solver->solve($problem);
            foreach ($problem->unlocatedJobIds as $jobId) {
                $plan = $plan->withUnassigned(new UnassignedJob($jobId, UnassignedJob::REASON_NO_COORDINATES));
            }
            $proposal->forceFill([
                'solver' => $plan->solver,
                'is_estimate' => $plan->isEstimate,
                'problem' => array_merge($request, $problem->summary()),
                'plan' => $plan->toArray(),
                'metrics' => $this->metrics($tour, $plan),
                'status' => TourProposalStatus::Open,
            ])->save();
        } catch (\Throwable $e) {
            report($e);
            $proposal->forceFill(['status' => TourProposalStatus::Failed, 'failure' => ErrorText::for($e)])->save();
        }
    }

    /**
     * Übernahme: schreibt Reihenfolge, Stopp- und Auftragszeiten, Tour-Startzeit
     * und Route; beim Tagesvorschlag entsteht je Fahrer mit Stopps eine Tour.
     *
     * @return list<Tour>
     */
    public function adopt(TourProposal $proposal, User $actor): array {
        $this->assertStatusTransition($proposal->status, TourProposalStatus::Adopted);
        $plan = TourPlan::fromArray($proposal->plan ?? []);
        $timezone = (string) ($proposal->problem['timezone'] ?? Tz::current());
        $date = CarbonImmutable::parse((string) $proposal->proposal_date->toDateString(), $timezone);

        return DB::transaction(function () use ($proposal, $plan, $date, $timezone, $actor): array {
            $tours = [];
            $tour = $proposal->tour;
            if ($tour instanceof Tour) {
                $route = $plan->route((int) $tour->user_id) ?? ($plan->routes[0] ?? null);
                if (! $route instanceof PlannedRoute) {
                    throw new RuntimeException((string) __('Der Vorschlag enthält keine Tour.'));
                }
                $remainder = ($proposal->problem['kind'] ?? self::KIND_TOUR) === self::KIND_REMAINDER;
                $tours[] = $this->applyRoute($tour, $route, $date, $timezone, $remainder);
                $tour->record($remainder ? 'tour.replanned' : 'tour.proposal_adopted', $this->adoptionPayload($proposal, $route, $plan, $tour), $actor);
            } else {
                foreach ($plan->routes as $route) {
                    if ($route->stops === []) {
                        continue;
                    }
                    $driver = User::query()->find($route->vehicleId);
                    if (! $driver instanceof User) {
                        continue;
                    }
                    $target = Tour::query()->forUser((int) $driver->id)->onDate($date->toDateString())
                        ->whereIn('status', [TourStatus::Draft->value, TourStatus::Planned->value])
                        ->whereDoesntHave('diaryEntries')
                        ->first() ?? $this->tours->createDraft($driver, $date);
                    $target = $this->applyRoute($target, $route, $date, $timezone, false);
                    $target->record('tour.proposal_adopted', $this->adoptionPayload($proposal, $route, $plan, $target), $actor);
                    $tours[] = $target;
                }
            }

            $proposal->forceFill([
                'status' => TourProposalStatus::Adopted,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ])->save();

            return $tours;
        });
    }

    public function reject(TourProposal $proposal, User $actor, string $note): void {
        $this->assertStatusTransition($proposal->status, TourProposalStatus::Rejected);
        $proposal->forceFill([
            'status' => TourProposalStatus::Rejected,
            'decision_note' => $note,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ])->save();
        $proposal->tour?->record('tour.proposal_rejected', ['proposal_id' => $proposal->id, 'note' => $note], $actor);
    }

    /**
     * Schreibt eine geplante Route in die Tour. Bei der Rest-Neuplanung
     * bleiben erledigte und verlassene Stopps vorn stehen, ihre Zeiten
     * unverändert.
     */
    private function applyRoute(Tour $tour, PlannedRoute $route, CarbonImmutable $date, string $timezone, bool $remainder): Tour {
        $planned = $route->jobIds();
        $fixed = $remainder ? $this->fixedStopIds($tour) : [];
        $rest = $tour->diaryEntries()
            ->whereNotIn('id', [...$planned, ...$fixed])
            ->orderByRaw('tour_position IS NULL')
            ->orderBy('tour_position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn($id): int => (int) $id)
            ->all();
        $this->tours->assignOrders($tour, array_values([...$fixed, ...$planned, ...$rest]));

        foreach ($route->stops as $stop) {
            $arrival = TourProblem::atSeconds($date, $timezone, $stop->arrival);
            $serviceStart = TourProblem::atSeconds($date, $timezone, $stop->serviceStart);
            $departure = TourProblem::atSeconds($date, $timezone, $stop->departure);
            TourStop::query()->where('tour_id', $tour->id)->where('diary_entry_id', $stop->jobId)->update([
                'planned_arrival_at' => $arrival,
                'planned_departure_at' => $departure,
                'waiting_minutes' => intdiv($stop->waitingSeconds, 60),
            ]);
            $entry = DiaryEntry::query()->find($stop->jobId);
            if ($entry instanceof DiaryEntry) {
                $entry->fill([
                    'scheduled_for' => $date->toDateString(),
                    'start_at' => $serviceStart->toDateTimeString(),
                    'end_at' => $departure->toDateTimeString(),
                ])->save();
            }
        }
        if ($rest !== []) {
            TourStop::query()->where('tour_id', $tour->id)->whereIn('diary_entry_id', $rest)
                ->update(['planned_arrival_at' => null, 'planned_departure_at' => null, 'waiting_minutes' => 0]);
        }

        if (! $remainder) {
            $tour->planned_start_at = Carbon::instance(TourProblem::atSeconds($date, $timezone, $route->startAt));
        }
        $tour->planned_distance_km = (string) round($route->distanceMeters / 1000, 2);
        $tour->planned_duration_minutes = (int) ceil(($route->endAt - ($remainder ? $route->startAt : $route->startAt)) / 60);
        $tour->save();
        $this->tours->refreshRoute($tour);

        return $tour->refresh();
    }

    /**
     * Erledigte oder bereits verlassene Stopps einer laufenden Tour, in Positionsreihenfolge.
     *
     * @return list<int>
     */
    public function fixedStopIds(Tour $tour): array {
        $departed = TourStop::query()->where('tour_id', $tour->id)->whereNotNull('departed_at')->pluck('diary_entry_id')->map(static fn($id): int => (int) $id)->all();

        return array_values($tour->orderedStops()->get()
            ->filter(static fn(DiaryEntry $entry): bool => in_array((int) $entry->id, $departed, true)
                || in_array($entry->status, [DiaryStatus::Done, DiaryStatus::AcceptedFinal, DiaryStatus::Invoiced], true))
            ->map(static fn(DiaryEntry $entry): int => (int) $entry->id)
            ->all());
    }

    /** @return array<string, mixed> */
    private function adoptionPayload(TourProposal $proposal, PlannedRoute $route, TourPlan $plan, Tour $tour): array {
        return [
            'proposal_id' => $proposal->id,
            'solver' => $plan->solver,
            'stops' => count($route->stops),
            'unassigned' => count($plan->unassigned),
            'distance_km' => (float) $tour->planned_distance_km,
        ];
    }

    /** @return array{before: array<string, mixed>|null, after: array<string, mixed>} */
    private function metrics(?Tour $tour, TourPlan $plan): array {
        $routes = array_values(array_filter($plan->routes, static fn(PlannedRoute $r): bool => $r->stops !== []));
        $duration = 0;
        $breaks = 0;
        foreach ($routes as $route) {
            $duration += (int) ceil(($route->endAt - $route->startAt) / 60);
            $breaks += count($route->breaks);
        }

        return [
            'before' => $tour instanceof Tour ? [
                'distance_km' => (float) $tour->planned_distance_km,
                'duration_minutes' => (int) $tour->planned_duration_minutes,
                'stops' => $tour->diaryEntries()->count(),
            ] : null,
            'after' => [
                'distance_km' => round($plan->distanceMeters() / 1000, 2),
                'duration_minutes' => $duration,
                'driving_minutes' => (int) ceil($plan->drivingSeconds() / 60),
                'waiting_minutes' => (int) ceil($plan->waitingSeconds() / 60),
                'stops' => $plan->stopCount(),
                'breaks' => $breaks,
                'unassigned' => count($plan->unassigned),
                'tours' => count($routes),
            ],
        ];
    }
}
