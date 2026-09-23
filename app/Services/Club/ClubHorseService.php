<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubHorseService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubHorseKind, ClubParticipationStatus, ClubResourceKind};
use App\Enums\Notification\NotificationEvent;
use App\Models\Club\{ClubHorse, ClubHorseAssignment, ClubHorseUse, ClubMember, ClubResource, ClubResourceBooking};
use App\Models\{Event, Organization, User};
use App\Services\Notification\NotificationDispatcher;
use App\Support\Tz;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\{Builder, Collection as EloquentCollection};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Reitbetrieb (Feature 159, MVP-854) — einzige Schreibstelle: Pferdeprofile
 * auf Ressourcen (Belegung, Ruhepuffer, Sperrzeiten, Eignungsfreigaben über
 * MVP-853), Zuordnung Reiter–Pferd je Reitstunde mit Prüfung von Sperre,
 * Eignung und Einsatzgrenze, getrennte Pferdeeinsatznachweise. Das System
 * entscheidet keine fachliche Einsatzfähigkeit — Ausnahmen sind ausdrücklich
 * und begründet.
 */
class ClubHorseService {
    public function __construct(
        private readonly ClubResourceService $resources,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    // ── Pferdeprofil ─────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data  name, kind, owner_member_id, contact, max_uses_per_day, rest_minutes,
     *                                      requires_clearance, suitable_for, group_ids, is_active, notes
     */
    public function create(Organization $organization, array $data): ClubHorse {
        return DB::transaction(function () use ($organization, $data): ClubHorse {
            $resource = $this->resources->create($organization, [
                'name' => (string) __('club.horses.label.resource_name', ['name' => trim((string) $data['name'])]),
                'kind' => ClubResourceKind::Horse->value,
                'capacity' => 1,
                'teardown_minutes' => max(0, (int) ($data['rest_minutes'] ?? 0)),
                'requires_clearance' => (bool) ($data['requires_clearance'] ?? true),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
            $horse = ClubHorse::query()->create(['organization_id' => $organization->id, 'club_resource_id' => $resource->id] + $this->attributes($organization->id, $data));
            $this->syncGroups($horse, $data);
            $horse->audit('club.horse.created', ['kind' => $horse->kind->value]);

            return $horse;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(ClubHorse $horse, array $data): ClubHorse {
        return DB::transaction(function () use ($horse, $data): ClubHorse {
            $horse->update($this->attributes($horse->organization_id, $data));
            $resource = $horse->resource()->firstOrFail();
            $this->resources->update($resource, [
                'name' => (string) __('club.horses.label.resource_name', ['name' => $horse->name]),
                'kind' => ClubResourceKind::Horse->value,
                'parent_id' => $resource->parent_id,
                'room_id' => $resource->room_id,
                'asset_id' => $resource->asset_id,
                'capacity' => 1,
                'setup_minutes' => $resource->setup_minutes,
                'teardown_minutes' => array_key_exists('rest_minutes', $data) ? max(0, (int) $data['rest_minutes']) : $resource->teardown_minutes,
                'requires_clearance' => array_key_exists('requires_clearance', $data) ? (bool) $data['requires_clearance'] : $resource->requires_clearance,
                'is_active' => $horse->is_active,
                'sort_order' => $resource->sort_order,
                'notes' => $resource->notes,
            ]);
            $this->syncGroups($horse, $data);
            $horse->audit('club.horse.updated');

            return $horse->refresh();
        });
    }

    public function delete(ClubHorse $horse): void {
        if ($horse->assignments()->exists() || $horse->uses()->exists()) {
            throw ValidationException::withMessages(['horse' => __('club.horses.error.in_use')]);
        }
        DB::transaction(function () use ($horse): void {
            $resource = $horse->resource()->first();
            $horse->audit('club.horse.deleted');
            $horse->delete();
            if ($resource instanceof ClubResource) {
                $this->resources->delete($resource);
            }
        });
    }

    // ── Zuordnung Reiter–Pferd ───────────────────────────────────────────

    /**
     * Pferd (oder eigenes Pferd) einem Reiter für eine Stunde zuordnen. Prüft
     * Sperrzeit/Belegung (über die Ressource), Eignungsfreigabe und Einsatzgrenze
     * je Tag; Ausnahmen nur ausdrücklich mit Begründung. Ein Wechsel ersetzt
     * die bisherige Zuordnung und gibt das alte Pferd frei.
     */
    public function assign(Event $event, ClubMember $rider, ?ClubHorse $horse, User $actor, bool $ownHorse = false, bool $override = false, ?string $note = null): ClubHorseAssignment {
        return DB::transaction(function () use ($event, $rider, $horse, $actor, $ownHorse, $override, $note): ClubHorseAssignment {
            if ($rider->organization_id !== $event->organization_id || ($horse !== null && $horse->organization_id !== $event->organization_id)) {
                throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
            }
            if ($horse === null && ! $ownHorse) {
                throw ValidationException::withMessages(['club_horse_id' => __('club.horses.error.horse_or_own')]);
            }
            $note = $this->nullableString($note);
            if ($override && $note === null) {
                throw ValidationException::withMessages(['override_note' => __('club.horses.error.override_note_required')]);
            }
            /** @var ClubHorseAssignment|null $existing */
            $existing = ClubHorseAssignment::query()->where('event_id', $event->id)->where('club_member_id', $rider->id)->lockForUpdate()->first();
            if ($existing?->club_resource_booking_id !== null) {
                $old = ClubResourceBooking::query()->find($existing->club_resource_booking_id);
                if ($old !== null) {
                    $this->resources->release($old, $actor);
                }
            }

            $booking = null;
            if ($horse !== null) {
                if (! $horse->is_active) {
                    throw ValidationException::withMessages(['club_horse_id' => __('club.horses.error.inactive', ['name' => $horse->name])]);
                }
                if ($horse->isPrivate() && $horse->owner_member_id !== $rider->id && ! $override) {
                    throw ValidationException::withMessages(['club_horse_id' => __('club.horses.error.private_horse', ['name' => $horse->name])]);
                }
                $resource = $horse->resource()->firstOrFail();
                $day = $this->localDay($event);
                if (! $this->resources->hasClearance($resource, $rider, $day) && ! $override) {
                    throw ValidationException::withMessages(['club_horse_id' => __('club.horses.error.no_clearance', ['name' => $rider->fullName(), 'horse' => $horse->name])]);
                }
                $limit = $horse->max_uses_per_day;
                if ($limit !== null && $this->usesOnDay($horse, $day, $event->id) >= $limit && ! $override) {
                    throw ValidationException::withMessages(['club_horse_id' => __('club.horses.error.daily_limit', ['name' => $horse->name, 'max' => $limit])]);
                }
                // Belegung der Pferde-Ressource: Doppelvergabe, Ruhepuffer und Sperrzeiten werden dort geprüft — nie übergehbar.
                $booking = $this->resources->book($event, $resource, ['club_member_id' => $override ? null : $rider->id, 'note' => (string) __('club.horses.label.booking_note', ['name' => $rider->fullName()])], $actor);
            }

            $attributes = [
                'club_horse_id' => $horse?->id,
                'club_resource_booking_id' => $booking?->id,
                'own_horse' => $horse === null,
                'override_note' => $override ? $note : null,
                'needs_review_at' => null,
                'review_reason' => null,
                'assigned_by_user_id' => $actor->id,
            ];
            if ($existing !== null) {
                $existing->update($attributes);
                $assignment = $existing->refresh();
            } else {
                $assignment = ClubHorseAssignment::query()->create(['organization_id' => $event->organization_id, 'event_id' => $event->id, 'club_member_id' => $rider->id] + $attributes);
            }
            $assignment->audit('club.horse.assigned', ['horse_id' => $horse?->id, 'own_horse' => $attributes['own_horse'], 'override' => $override, 'note' => $note]);

            return $assignment;
        });
    }

    public function unassign(ClubHorseAssignment $assignment, User $actor): void {
        DB::transaction(function () use ($assignment, $actor): void {
            if ($assignment->club_resource_booking_id !== null) {
                $booking = ClubResourceBooking::query()->find($assignment->club_resource_booking_id);
                if ($booking !== null) {
                    $this->resources->release($booking, $actor);
                }
            }
            $assignment->audit('club.horse.unassigned', ['horse_id' => $assignment->club_horse_id, 'actor_id' => $actor->id]);
            $assignment->delete();
        });
    }

    /**
     * Ausfall eines Pferdes (Sperrzeit auf der Ressource): betroffene Zuordnungen
     * zur Neuplanung markieren und die Leitung der Stunden benachrichtigen —
     * keine automatische Ersatzzuteilung.
     *
     * @return int markierte Zuordnungen
     */
    public function flagUnavailable(ClubResource $resource, CarbonInterface $from, CarbonInterface $to, string $reason): int {
        $horse = ClubHorse::query()->where('club_resource_id', $resource->id)->first();
        if ($horse === null) {
            return 0;
        }
        $flagged = 0;
        $assignments = ClubHorseAssignment::query()
            ->where('club_horse_id', $horse->id)
            ->whereHas('event', fn($q) => $q->whereNull('cancelled_at')->where('started_at', '<', $to)->where('ended_at', '>', $from))
            ->with(['event.responsibleUser', 'member:id,first_name,last_name'])
            ->get();
        foreach ($assignments as $assignment) {
            $assignment->update(['needs_review_at' => now(), 'review_reason' => mb_substr((string) __('club.horses.label.unavailable_reason', ['horse' => $horse->name, 'reason' => $reason]), 0, 255)]);
            $assignment->audit('club.horse.needsReview', ['reason' => $reason]);
            $flagged++;
            $this->notifyLeader($assignment, $horse, $reason);
        }

        return $flagged;
    }

    // ── Einsatznachweis ──────────────────────────────────────────────────

    /** Pferdeeinsatz in Minuten je Stunde — getrennt von der Reiteranwesenheit; wiederholtes Erfassen ersetzt. */
    public function recordUse(Event $event, ClubHorse $horse, ?ClubMember $rider, int $minutes, User $actor, ?string $note = null): ClubHorseUse {
        if ($minutes < 0 || $minutes > 24 * 60) {
            throw ValidationException::withMessages(['minutes' => __('club.horses.error.minutes')]);
        }
        /** @var ClubHorseUse $use */
        $use = ClubHorseUse::query()->updateOrCreate(
            ['event_id' => $event->id, 'club_horse_id' => $horse->id, 'club_member_id' => $rider?->id],
            ['organization_id' => $event->organization_id, 'minutes' => $minutes, 'note' => $this->nullableString($note), 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()],
        );
        $use->audit('club.horse.useRecorded', ['minutes' => $minutes, 'member_id' => $rider?->id]);

        return $use;
    }

    /** Einsatzminuten eines Pferdes im Zeitraum (nur nicht abgesagte Stunden). */
    public function minutesFor(ClubHorse $horse, CarbonInterface $from, CarbonInterface $to): int {
        return (int) ClubHorseUse::query()
            ->where('club_horse_id', $horse->id)
            ->whereHas('event', fn($q) => $q->whereNull('cancelled_at')->where('started_at', '>=', CarbonImmutable::instance($from)->utc())->where('started_at', '<', CarbonImmutable::instance($to)->utc()))
            ->sum('minutes');
    }

    // ── Sichten ──────────────────────────────────────────────────────────

    /**
     * Reiter der Stunde (angemeldet/wartend) mit Zuordnung, Einsatz und Prüfhinweisen.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function ridersFor(Event $event): Collection {
        $assignments = ClubHorseAssignment::query()->where('event_id', $event->id)->with(['horse', 'booking'])->get()->keyBy('club_member_id');
        $uses = ClubHorseUse::query()->where('event_id', $event->id)->get()->groupBy('club_member_id');
        $members = ClubMember::query()
            ->whereHas('eventParticipations', fn($q) => $q->where('event_id', $event->id)->whereIn('status', [ClubParticipationStatus::Registered->value, ClubParticipationStatus::Waitlisted->value]))
            ->orderBy('last_name')->orderBy('first_name')->get();
        foreach ($assignments->keys()->diff($members->pluck('id')) as $memberId) {
            $extra = ClubMember::query()->find($memberId);
            if ($extra !== null) {
                $members->push($extra);
            }
        }
        $day = $this->localDay($event);
        $rows = collect();
        foreach ($members as $member) {
            $assignment = $assignments->get($member->id);
            $horse = $assignment?->horse;
            $warnings = [];
            if ($assignment instanceof ClubHorseAssignment && $assignment->needsReview()) {
                $warnings[] = (string) $assignment->review_reason;
            }
            if ($horse !== null && $assignment->override_note === null) {
                $resource = $horse->resource;
                if ($resource instanceof ClubResource && ! $this->resources->hasClearance($resource, $member, $day)) {
                    $warnings[] = (string) __('club.horses.error.no_clearance', ['name' => $member->fullName(), 'horse' => $horse->name]);
                }
            }
            $rows->push([
                'member' => $member,
                'assignment' => $assignment,
                'horse' => $horse,
                'uses' => $uses->get($member->id, collect()),
                'warnings' => $warnings,
            ]);
        }

        return $rows;
    }

    /**
     * Wählbare Pferde für eine Stunde: aktive Schulpferde der Zielgruppen (oder ohne Gruppenbindung)
     * plus Privatpferde der angemeldeten Reiter.
     *
     * @return EloquentCollection<int, ClubHorse>
     */
    public function horsesFor(Event $event): EloquentCollection {
        $groupIds = $event->clubGroups()->pluck('club_groups.id')->all();

        return ClubHorse::query()
            ->where('organization_id', $event->organization_id)
            ->where('is_active', true)
            ->where(function (Builder $q) use ($groupIds, $event): void {
                $q->where(function (Builder $school) use ($groupIds): void {
                    $school->where('kind', ClubHorseKind::School->value)
                        ->where(fn(Builder $g) => $g->whereDoesntHave('groups')->orWhereHas('groups', fn(Builder $gg) => $gg->whereIn('club_groups.id', $groupIds)));
                })->orWhere(function (Builder $private) use ($event): void {
                    $private->where('kind', ClubHorseKind::Private->value)
                        ->whereHas('owner.eventParticipations', fn(Builder $p) => $p->where('event_id', $event->id));
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function usesOnDay(ClubHorse $horse, CarbonInterface $day, ?int $ignoreEventId = null): int {
        $tz = Tz::current();
        $start = CarbonImmutable::instance($day)->setTimezone($tz)->startOfDay()->utc();
        $end = $start->addDay();

        return ClubHorseAssignment::query()
            ->where('club_horse_id', $horse->id)
            ->when($ignoreEventId !== null, fn($q) => $q->where('event_id', '!=', $ignoreEventId))
            ->whereHas('event', fn($q) => $q->whereNull('cancelled_at')->where('started_at', '>=', $start)->where('started_at', '<', $end))
            ->count();
    }

    // ── Helfer ───────────────────────────────────────────────────────────

    private function notifyLeader(ClubHorseAssignment $assignment, ClubHorse $horse, string $reason): void {
        $event = $assignment->event;
        $leader = $event?->responsibleUser;
        if ($event === null || $leader === null) {
            return;
        }
        $params = ['horse' => $horse->name, 'title' => (string) $event->title, 'date' => CarbonImmutable::instance($event->started_at)->setTimezone(Tz::current())->format('d.m.Y H:i'), 'reason' => $reason, 'rider' => $assignment->member?->fullName() ?? ''];
        try {
            $this->dispatcher->notify(NotificationEvent::ClubHorseUnavailable, $event, $leader, [
                'title' => (string) $event->title,
                'message' => (string) __('notification.message.club_horse_unavailable', $params),
                'message_key' => 'notification.message.club_horse_unavailable',
                'message_params' => $params,
                'url' => route('club.events.show', $event),
                'icon' => 'bedroom_baby',
            ]);
        } catch (Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }
        }
    }

    private function localDay(Event $event): CarbonImmutable {
        $tz = Tz::isValid($event->timezone) && $event->timezone !== 'UTC' ? (string) $event->timezone : Tz::current();

        return CarbonImmutable::instance($event->started_at)->setTimezone($tz)->startOfDay();
    }

    /** @param  array<string, mixed>  $data */
    private function syncGroups(ClubHorse $horse, array $data): void {
        if (! array_key_exists('group_ids', $data)) {
            return;
        }
        $ids = array_values(array_unique(array_map('intval', array_filter((array) $data['group_ids'], static fn($v): bool => $v !== null && $v !== ''))));
        $rows = [];
        foreach ($ids as $id) {
            $rows[$id] = ['organization_id' => $horse->organization_id];
        }
        $horse->groups()->sync($rows);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(int $organizationId, array $data): array {
        $kind = ClubHorseKind::from((string) ($data['kind'] ?? ClubHorseKind::School->value));
        $ownerId = $this->nullableInt($data['owner_member_id'] ?? null);
        if ($kind === ClubHorseKind::Private && $ownerId === null) {
            throw ValidationException::withMessages(['owner_member_id' => __('club.horses.error.owner_required')]);
        }
        if ($ownerId !== null && ! ClubMember::query()->whereKey($ownerId)->where('organization_id', $organizationId)->exists()) {
            throw ValidationException::withMessages(['owner_member_id' => __('club.error.member_foreign')]);
        }

        return [
            'name' => mb_substr(trim((string) $data['name']), 0, 120),
            'kind' => $kind->value,
            'owner_member_id' => $kind === ClubHorseKind::Private ? $ownerId : null,
            'contact' => $this->nullableString($data['contact'] ?? null),
            'max_uses_per_day' => $this->nullableInt($data['max_uses_per_day'] ?? null),
            'suitable_for' => $this->nullableString($data['suitable_for'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $this->nullableString($data['notes'] ?? null),
        ];
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
