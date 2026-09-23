<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubEventKind, ClubEventVisibility, ClubGroupMembershipStatus, ClubParticipationSource, ClubParticipationStatus};
use App\Enums\Event\{EventStatus, EventVisibility};
use App\Models\Club\{ClubEventDetails, ClubEventParticipation, ClubGuardian, ClubMember};
use App\Models\{Event, Organization, User};
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Event\EventService;
use App\Services\Participation\EventSeatService;
use App\Support\Query\DateRange;
use App\Support\Tz;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vereinstermine (Feature 159, MVP-843) — einzige Schreibstelle für
 * Vereinsdetails, Zielgruppen und Mitgliedsteilnahmen. Der Termin bleibt ein
 * `Event` (Feature 028): Anlage, Räume, Serien und Absage laufen über den
 * EventService; Kapazität und Warteliste teilt sich der Termin mit den
 * Benutzer-Teilnahmen über den EventSeatService. Anmeldung und Nachrücken
 * sperren den Termin in einer Transaktion; Nachweise (MVP-844) bleiben von
 * Verschiebung und Absage unberührt.
 */
class ClubEventService {
    use AssertsStatusTransition;

    public function __construct(
        private readonly EventService $events,
        private readonly EventSeatService $seats,
        private readonly ClubMemberNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data  title, description, kind, visibility, club_department_id,
     *                                      club_group_ids, started_at/ended_at (UTC), timezone, leader_user_id,
     *                                      room_id, max_participants, registration_lead_hours,
     *                                      cancellation_lead_hours, recurrence, series_until
     */
    public function create(Organization $organization, User $actor, array $data): Event {
        return DB::transaction(function () use ($organization, $actor, $data): Event {
            $rule = $this->buildRule($data);
            $event = $this->events->create(
                $this->eventAttributes($data) + [
                    'organization_id' => $organization->id,
                    'status' => EventStatus::Planned->value,
                    'visibility' => EventVisibility::Internal->value,
                    'is_all_day' => false,
                    'is_mandatory' => false,
                    'recurrence_rule' => $rule,
                    'series_until' => $rule !== null ? $this->nullableString($data['series_until'] ?? null) : null,
                    'created_by' => $actor->id,
                ],
                $this->roomRows($data),
            );

            ClubEventDetails::query()->create([
                'organization_id' => $organization->id,
                'event_id' => $event->id,
            ] + $this->detailAttributes($data));
            $this->syncGroups($event, $this->groupIds($data));

            $this->propagateToOccurrences($event);

            return $event->fresh(['clubDetails', 'clubGroups', 'rooms']) ?? $event;
        });
    }

    /**
     * Änderung dieses Termins; `$applyToFuture` überträgt sie auf spätere
     * Vorkommen derselben Serie (Zeiten als Verschiebung, Rest als Kopie).
     * Bestehende Teilnahmen und Nachweise werden nicht angefasst.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, User $actor, array $data, bool $applyToFuture = false): Event {
        return DB::transaction(function () use ($event, $actor, $data, $applyToFuture): Event {
            $details = $this->detailsOf($event);
            $oldStart = CarbonImmutable::instance($event->started_at);
            $oldEnd = CarbonImmutable::instance($event->ended_at);

            $this->events->update($event, $this->eventAttributes($data) + ['updated_by' => $actor->id], array_key_exists('room_id', $data) ? $this->roomRows($data) : null);
            $details->update($this->detailAttributes($data));
            $this->syncGroups($event, $this->groupIds($data));
            $details->audit('club.event.updated', ['future' => $applyToFuture]);
            $event->refresh();
            if (! $oldStart->equalTo($event->started_at) || ! $oldEnd->equalTo($event->ended_at)) {
                // Ressourcen (MVP-853) wandern mit; ein Konflikt wirft und rollt die ganze Änderung zurück.
                app(ClubResourceService::class)->moveWithEvent($event, $oldStart, $oldEnd);
                $this->notifier->rescheduled($event, $oldStart);
                if ($details->kind === ClubEventKind::Exam) {
                    // Prüfung verschoben → Zulassung zum neuen Stichtag neu bewerten (MVP-847); lazy gegen den Zyklus Exam→Event.
                    app(ClubExamService::class)->recheckForEvent($event);
                }
            }

            if ($applyToFuture) {
                $shift = $oldStart->diffInSeconds(CarbonImmutable::instance($event->started_at), false);
                $duration = CarbonImmutable::instance($event->started_at)->diffInSeconds(CarbonImmutable::instance($event->ended_at), false);
                foreach ($this->futureOccurrences($event) as $occurrence) {
                    $previousStart = CarbonImmutable::instance($occurrence->started_at);
                    $start = $previousStart->addSeconds((int) $shift);
                    $this->events->update($occurrence, [
                        'title' => $event->title,
                        'description' => $event->description,
                        'event_type' => $event->event_type->value,
                        'timezone' => $event->timezone,
                        'responsible_user_id' => $event->responsible_user_id,
                        'max_participants' => $event->max_participants,
                        'started_at' => $start->format('Y-m-d H:i:s'),
                        'ended_at' => $start->addSeconds((int) $duration)->format('Y-m-d H:i:s'),
                        'updated_by' => $actor->id,
                    ], array_key_exists('room_id', $data) ? $this->roomRows($data) : null);
                    $this->copyClubData($event, $occurrence);
                    if ((int) $shift !== 0) {
                        $this->notifier->rescheduled($occurrence->refresh(), $previousStart);
                    }
                }
            }

            return $event->fresh(['clubDetails', 'clubGroups', 'rooms']) ?? $event;
        });
    }

    /** Absage — Teilnahmen bleiben als Historie, kein Platz wird neu vergeben. */
    public function cancel(Event $event, User $actor, ?string $reason = null, bool $applyToFuture = false): void {
        DB::transaction(function () use ($event, $actor, $reason, $applyToFuture): void {
            $details = $this->detailsOf($event);
            $this->events->cancel($event, $reason);
            app(ClubResourceService::class)->releaseAll($event, $actor);
            $details->audit('club.event.cancelled', ['reason' => $reason, 'future' => $applyToFuture, 'actor_id' => $actor->id]);
            $this->notifier->cancelled($event->refresh());

            if ($applyToFuture) {
                foreach ($this->futureOccurrences($event) as $occurrence) {
                    $this->events->cancel($occurrence, $reason);
                    app(ClubResourceService::class)->releaseAll($occurrence, $actor);
                    $this->notifier->cancelled($occurrence->refresh());
                }
            }
        });
    }

    /**
     * Soll-Liste zum Termin: je nach Sichtbarkeit alle aktuellen Mitglieder,
     * die Mitglieder der Zielgruppen (am Termintag gültig, jedes Mitglied
     * einmal) oder die persönlich Eingeladenen.
     *
     * @return Collection<int, ClubMember>
     */
    public function targetMembers(Event $event): Collection {
        $details = $this->detailsOf($event);
        $day = $this->localDay($event);

        $query = ClubMember::query()
            ->where('organization_id', $event->organization_id)
            ->current($day)
            ->orderBy('last_name')
            ->orderBy('first_name');

        return match ($details->visibility) {
            ClubEventVisibility::Club => $query->get(),
            ClubEventVisibility::Groups => $query
                ->whereHas('groupMemberships', fn(Builder $memberships) => $this->validMembershipsOn($memberships, $event, $day))
                ->get(),
            ClubEventVisibility::Invited => $query
                ->whereHas('eventParticipations', fn(Builder $participations) => $participations
                    ->where('event_id', $event->id)
                    ->whereIn('status', [ClubParticipationStatus::Invited->value, ClubParticipationStatus::Registered->value, ClubParticipationStatus::Waitlisted->value]))
                ->get(),
        };
    }

    /**
     * Termine aus Sicht eines Mitglieds (MVP-845): alles, wofür es anmeldeberechtigt
     * ist (Verein, Zielgruppe am Termintag, Einladung) — ein passender Lehrgang
     * erscheint ohne persönliche Einladung — plus Termine mit eigener Teilnahme,
     * auch abgesagte. Keine fremden Teilnehmer.
     *
     * @return Collection<int, array{event: Event, participation: ClubEventParticipation|null, eligible: bool}>
     */
    public function visibleEventsFor(ClubMember $member, CarbonInterface $from, CarbonInterface $to, int $limit = 200): Collection {
        $events = Event::query()
            ->where('organization_id', $member->organization_id)
            ->whereHas('clubDetails')
            ->where('ended_at', '>=', $from)
            ->where('started_at', '<', $to)
            ->with(['clubDetails', 'clubGroups:id,name'])
            ->orderBy('started_at')
            ->limit($limit)
            ->get();
        $participations = ClubEventParticipation::query()
            ->where('club_member_id', $member->id)
            ->whereIn('event_id', $events->modelKeys())
            ->get()
            ->keyBy('event_id');

        return $events
            ->map(function (Event $event) use ($member, $participations): array {
                $eligible = ! $event->isCancelled() && $this->isEligible($event, $member);

                return ['event' => $event, 'participation' => $participations->get($event->id), 'eligible' => $eligible];
            })
            ->filter(fn(array $row): bool => $row['eligible'] || $row['participation'] !== null)
            ->values();
    }

    /** Darf sich das Mitglied ohne Sonderrecht anmelden (Sichtbarkeit, Zielgruppe, Einladung)? */
    public function isEligible(Event $event, ClubMember $member): bool {
        $details = $this->detailsOf($event);
        $day = $this->localDay($event);
        if ($member->organization_id !== $event->organization_id || $member->hasLeftOn($day)) {
            return false;
        }

        return match ($details->visibility) {
            ClubEventVisibility::Club => true,
            ClubEventVisibility::Groups => $this->validMembershipsOn($member->groupMemberships()->getQuery(), $event, $day)->exists(),
            ClubEventVisibility::Invited => ClubEventParticipation::query()
                ->where('event_id', $event->id)
                ->where('club_member_id', $member->id)
                ->whereIn('status', [ClubParticipationStatus::Invited->value, ClubParticipationStatus::Registered->value, ClubParticipationStatus::Waitlisted->value])
                ->exists(),
        };
    }

    /**
     * Anmeldung: belegt einen Platz oder wartet. Fristen, Sichtbarkeit und
     * Terminbeginn gelten für Mitglied/Vertretung; Verwaltung und Leitung
     * ergänzen mit `$force` auch spontan.
     */
    public function register(Event $event, ClubMember $member, User $actor, ClubParticipationSource $source, ?ClubGuardian $guardian = null, bool $force = false, ?string $note = null): ClubEventParticipation {
        return DB::transaction(function () use ($event, $member, $actor, $source, $guardian, $force, $note): ClubEventParticipation {
            $event = $this->lockEvent($event);
            $details = $this->detailsOf($event);
            $day = $this->localDay($event);
            $now = CarbonImmutable::now();

            if ($member->organization_id !== $event->organization_id) {
                throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
            }
            if ($event->status === EventStatus::Cancelled) {
                throw ValidationException::withMessages(['club_member_id' => __('club.events.error.event_cancelled')]);
            }
            if ($member->hasLeftOn($day)) {
                throw ValidationException::withMessages(['club_member_id' => __('club.error.member_left')]);
            }
            if (! $force) {
                if (CarbonImmutable::instance($event->started_at)->lessThan($now)) {
                    throw ValidationException::withMessages(['club_member_id' => __('club.events.error.event_started')]);
                }
                $closes = $details->registrationClosesAt($event);
                if ($closes !== null && $closes->lessThan($now)) {
                    throw ValidationException::withMessages(['club_member_id' => __('club.events.error.registration_closed')]);
                }
                if (! $this->isEligible($event, $member)) {
                    throw ValidationException::withMessages(['club_member_id' => __('club.events.error.not_eligible')]);
                }
            }

            /** @var ClubEventParticipation|null $existing */
            $existing = ClubEventParticipation::query()
                ->where('event_id', $event->id)
                ->where('club_member_id', $member->id)
                ->first();
            if ($existing !== null && $existing->isActive()) {
                return $existing;
            }

            $status = $this->seats->hasFreeSeat($event) ? ClubParticipationStatus::Registered : ClubParticipationStatus::Waitlisted;
            $attributes = [
                'status' => $status->value,
                'source' => $source->value,
                'registered_at' => $now,
                'registered_by_user_id' => $actor->id,
                'club_guardian_id' => $guardian?->id,
                'promoted_at' => null,
                'cancelled_at' => null,
                'cancelled_by_user_id' => null,
                'note' => $this->nullableString($note),
            ];

            if ($existing !== null) {
                $this->assertStatusTransition($existing->status, $status);
                $existing->update($attributes);
                $participation = $existing->refresh();
            } else {
                $participation = ClubEventParticipation::query()->create([
                    'organization_id' => $event->organization_id,
                    'event_id' => $event->id,
                    'club_member_id' => $member->id,
                ] + $attributes);
            }
            $participation->audit($status === ClubParticipationStatus::Registered ? 'club.event.registered' : 'club.event.waitlisted', ['source' => $source->value, 'force' => $force]);

            return $participation;
        });
    }

    /** Absage einer Teilnahme; ein frei werdender Platz rückt sofort nach (ältester Wartender, egal welcher Art). */
    public function cancelRegistration(Event $event, ClubMember $member, User $actor, bool $force = false, ?string $note = null): ?ClubEventParticipation {
        return DB::transaction(function () use ($event, $member, $actor, $force, $note): ?ClubEventParticipation {
            $event = $this->lockEvent($event);
            $details = $this->detailsOf($event);

            /** @var ClubEventParticipation|null $participation */
            $participation = ClubEventParticipation::query()
                ->where('event_id', $event->id)
                ->where('club_member_id', $member->id)
                ->first();
            if ($participation === null || ! $participation->isActive()) {
                return $participation;
            }

            if (! $force) {
                $closes = $details->cancellationClosesAt($event);
                if ($closes !== null && $closes->lessThan(CarbonImmutable::now())) {
                    throw ValidationException::withMessages(['club_member_id' => __('club.events.error.cancellation_closed')]);
                }
            }

            $this->assertStatusTransition($participation->status, ClubParticipationStatus::Cancelled);
            $participation->update([
                'status' => ClubParticipationStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
                'note' => $this->nullableString($note) ?? $participation->note,
            ]);
            $participation->audit('club.event.registrationCancelled', ['force' => $force]);

            $promoted = $this->seats->promoteNext($event)?->subject;
            if ($promoted instanceof ClubEventParticipation) {
                $this->notifier->promoted($promoted->load(['event.organization', 'member']));
            }

            return $participation->refresh();
        });
    }

    /** Persönliche Einladung — ein Platz wird erst mit der Anmeldung belegt. */
    public function invite(Event $event, ClubMember $member, User $actor): ClubEventParticipation {
        return DB::transaction(function () use ($event, $member, $actor): ClubEventParticipation {
            $event = $this->lockEvent($event);
            if ($member->organization_id !== $event->organization_id) {
                throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
            }

            /** @var ClubEventParticipation|null $existing */
            $existing = ClubEventParticipation::query()
                ->where('event_id', $event->id)
                ->where('club_member_id', $member->id)
                ->first();
            if ($existing !== null && $existing->status !== ClubParticipationStatus::Cancelled) {
                return $existing;
            }

            $attributes = [
                'status' => ClubParticipationStatus::Invited->value,
                'source' => ClubParticipationSource::Invitation->value,
                'registered_at' => null,
                'registered_by_user_id' => $actor->id,
                'club_guardian_id' => null,
                'promoted_at' => null,
                'cancelled_at' => null,
                'cancelled_by_user_id' => null,
            ];
            if ($existing !== null) {
                $existing->update($attributes);
                $participation = $existing->refresh();
            } else {
                $participation = ClubEventParticipation::query()->create([
                    'organization_id' => $event->organization_id,
                    'event_id' => $event->id,
                    'club_member_id' => $member->id,
                ] + $attributes);
            }
            $participation->audit('club.event.invited');

            return $participation;
        });
    }

    /** Vereinsdetails und Zielgruppen des Masters auf Vorkommen ohne Details übertragen. */
    public function propagateToOccurrences(Event $master): int {
        if ($master->clubDetails()->doesntExist()) {
            return 0;
        }

        $copied = 0;
        $master->occurrences()->whereDoesntHave('clubDetails')->get()->each(function (Event $occurrence) use ($master, &$copied): void {
            $this->copyClubData($master, $occurrence);
            $copied++;
        });

        return $copied;
    }

    /** Für ein nachträglich materialisiertes Vorkommen (Scheduler): Details vom Master übernehmen. */
    public function inheritFromMaster(Event $occurrence): void {
        if ($occurrence->series_id === null || $occurrence->clubDetails()->exists()) {
            return;
        }
        $master = Event::query()->withoutGlobalScopes()->find($occurrence->series_id);
        if (! $master instanceof Event || $master->clubDetails()->doesntExist()) {
            return;
        }

        $this->copyClubData($master, $occurrence);
    }

    /**
     * Freie Plätze (null = unbegrenzt), belegte Plätze und Wartende für die Anzeige.
     *
     * @return array{taken: int, free: int|null, max: int|null}
     */
    public function seatSummary(Event $event): array {
        return [
            'taken' => $this->seats->takenSeats($event),
            'free' => $this->seats->freeSeats($event),
            'max' => $event->max_participants,
        ];
    }

    private function copyClubData(Event $source, Event $target): void {
        $details = $this->detailsOf($source);
        ClubEventDetails::query()->updateOrCreate(
            ['event_id' => $target->id],
            [
                'organization_id' => $target->organization_id,
                'kind' => $details->kind->value,
                'visibility' => $details->visibility->value,
                'club_department_id' => $details->club_department_id,
                'discipline' => $details->discipline,
                'registration_lead_hours' => $details->registration_lead_hours,
                'cancellation_lead_hours' => $details->cancellation_lead_hours,
            ],
        );
        $this->syncGroups($target, array_values(array_map('intval', $source->clubGroups()->pluck('club_groups.id')->all())));
    }

    /**
     * Pivot trägt die Mandantenspalte — sync() setzt sie nicht von selbst.
     *
     * @param  list<int>  $groupIds
     */
    private function syncGroups(Event $event, array $groupIds): void {
        $event->clubGroups()->sync(array_fill_keys($groupIds, ['organization_id' => $event->organization_id]));
    }

    /** @return Collection<int, Event> */
    private function futureOccurrences(Event $event): Collection {
        $seriesId = $event->series_id ?? $event->id;

        return Event::query()
            ->where('series_id', $seriesId)
            ->whereKeyNot($event->id)
            ->where('started_at', '>', $event->started_at)
            ->whereNull('cancelled_at')
            ->orderBy('started_at')
            ->get();
    }

    /**
     * @param  Builder<\App\Models\Club\ClubGroupMembership>  $memberships
     * @return Builder<\App\Models\Club\ClubGroupMembership>
     */
    private function validMembershipsOn(Builder $memberships, Event $event, CarbonImmutable $day): Builder {
        return $memberships
            ->where('status', ClubGroupMembershipStatus::Active->value)
            ->where('valid_from', '<', DateRange::dayAfter($day))
            ->where(fn(Builder $valid) => $valid->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($day)))
            ->whereIn('club_group_id', $event->clubGroups()->pluck('club_groups.id'));
    }

    /** Kalendertag des Beginns in der Zeitzone des Termins bzw. der Organisation. */
    public function localDay(Event $event): CarbonImmutable {
        $timezone = Tz::isValid($event->timezone) && $event->timezone !== 'UTC'
            ? (string) $event->timezone
            : ($event->organization !== null ? Tz::ofOrganization($event->organization) : Tz::current());

        return CarbonImmutable::instance($event->started_at)->setTimezone($timezone)->startOfDay();
    }

    private function lockEvent(Event $event): Event {
        /** @var Event $locked */
        $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    private function detailsOf(Event $event): ClubEventDetails {
        $details = $event->clubDetails()->first();
        if (! $details instanceof ClubEventDetails) {
            throw ValidationException::withMessages(['event' => __('club.events.error.not_club_event')]);
        }

        return $details;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function eventAttributes(array $data): array {
        $kind = ClubEventKind::from((string) $data['kind']);
        $attributes = [
            'title' => trim((string) $data['title']),
            'description' => $this->nullableString($data['description'] ?? null),
            'event_type' => $kind->eventType()->value,
            'responsible_user_id' => $this->nullableInt($data['leader_user_id'] ?? null),
            'max_participants' => $this->nullableInt($data['max_participants'] ?? null),
        ];
        foreach (['started_at', 'ended_at', 'timezone'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function detailAttributes(array $data): array {
        return [
            'kind' => ClubEventKind::from((string) $data['kind'])->value,
            'visibility' => ClubEventVisibility::from((string) ($data['visibility'] ?? ClubEventVisibility::Groups->value))->value,
            'club_department_id' => $this->nullableInt($data['club_department_id'] ?? null),
            'discipline' => $this->nullableString($data['discipline'] ?? null),
            'registration_lead_hours' => $this->nullableInt($data['registration_lead_hours'] ?? null),
            'cancellation_lead_hours' => $this->nullableInt($data['cancellation_lead_hours'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function groupIds(array $data): array {
        $ids = [];
        foreach ((array) ($data['club_group_ids'] ?? []) as $id) {
            if ($this->nullableString($id) !== null) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{room_id: int}>
     */
    private function roomRows(array $data): array {
        $roomId = $this->nullableInt($data['room_id'] ?? null);

        return $roomId === null ? [] : [['room_id' => $roomId]];
    }

    /**
     * Einfache Serienwahl → RRULE (Feature 028 versteht das volle Format).
     *
     * @param  array<string, mixed>  $data
     */
    private function buildRule(array $data): ?string {
        $rule = match ($this->nullableString($data['recurrence'] ?? null)) {
            'weekly' => 'FREQ=WEEKLY;INTERVAL=1',
            'biweekly' => 'FREQ=WEEKLY;INTERVAL=2',
            'monthly' => 'FREQ=MONTHLY;INTERVAL=1',
            default => null,
        };
        $until = $this->nullableString($data['series_until'] ?? null);
        if ($rule !== null && $until !== null) {
            $rule .= ';UNTIL=' . CarbonImmutable::parse($until)->endOfDay()->utc()->format('Ymd\THis\Z');
        }

        return $rule;
    }

    private function nullableInt(mixed $value): ?int {
        $string = $this->nullableString($value);

        return $string === null ? null : (int) $string;
    }

    private function nullableString(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
