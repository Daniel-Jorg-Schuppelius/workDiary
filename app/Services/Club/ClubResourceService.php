<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubResourceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubParticipationStatus, ClubResourceKind};
use App\Enums\Event\EventStatus;
use App\Models\Calendar\Event;
use App\Models\Club\{ClubMember, ClubResource, ClubResourceBooking, ClubResourceClearance, ClubResourceClosure};
use App\Models\Facility\Room;
use App\Models\Platform\{Organization, User};
use App\Services\Asset\AssetUsageGuard;
use App\Services\Event\RoomBookingService;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sportstätten und Ressourcen (Feature 159, MVP-853) — einzige Schreibstelle.
 * Eine Belegung sperrt Ressource, Über- und Unterressourcen (Halle ↔ Tische)
 * in einer Transaktion und prüft Kapazität, Raumkalender (event_room),
 * Sperrzeiten, Asset-Sperren und Einweisungsfreigaben, bevor sie entsteht.
 */
class ClubResourceService {
    public const ASSET_CONTEXT = 'club-booking';

    public function __construct(
        private readonly RoomBookingService $rooms,
        private readonly AssetUsageGuard $assetGuard,
    ) {}

    // ── Ressourcen ───────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $data */
    public function create(Organization $organization, array $data): ClubResource {
        $resource = ClubResource::query()->create(['organization_id' => $organization->id] + $this->attributes($organization->id, $data, null));
        $resource->audit('club.resource.created', ['kind' => $resource->kind->value]);

        return $resource;
    }

    /** @param  array<string, mixed>  $data */
    public function update(ClubResource $resource, array $data): ClubResource {
        $resource->update($this->attributes($resource->organization_id, $data, $resource));
        $resource->audit('club.resource.updated');

        return $resource->refresh();
    }

    public function delete(ClubResource $resource): void {
        if ($resource->bookings()->exists() || $resource->children()->exists()) {
            throw ValidationException::withMessages(['resource' => __('club.resources.error.in_use')]);
        }
        $resource->audit('club.resource.deleted');
        $resource->delete();
    }

    // ── Belegung ─────────────────────────────────────────────────────────

    /**
     * Belegung anlegen: Zeilen der Ressource samt Über-/Unterressourcen werden
     * gesperrt, dann Konflikte geprüft — zwei gleichzeitige Buchungen werden nie
     * beide bestätigt.
     *
     * @param  array<string, mixed>  $data  quantity, starts_at/ends_at (UTC, sonst Terminzeiten), setup_minutes, teardown_minutes, club_member_id, note
     */
    public function book(Event $event, ClubResource $resource, array $data, User $actor): ClubResourceBooking {
        return DB::transaction(function () use ($event, $resource, $data, $actor): ClubResourceBooking {
            if ($resource->organization_id !== $event->organization_id) {
                throw ValidationException::withMessages(['club_resource_id' => __('club.resources.error.foreign')]);
            }
            if (! $resource->is_active) {
                throw ValidationException::withMessages(['club_resource_id' => __('club.resources.error.inactive')]);
            }
            if ($event->status === EventStatus::Cancelled) {
                throw ValidationException::withMessages(['club_resource_id' => __('club.events.error.event_cancelled')]);
            }
            $this->lockTree($resource);
            if (ClubResourceBooking::query()->where('event_id', $event->id)->where('club_resource_id', $resource->id)->exists()) {
                throw ValidationException::withMessages(['club_resource_id' => __('club.resources.error.already_booked', ['name' => $resource->name])]);
            }
            $start = $this->time($data['starts_at'] ?? null) ?? CarbonImmutable::instance($event->started_at);
            $end = $this->time($data['ends_at'] ?? null) ?? CarbonImmutable::instance($event->ended_at);
            if (! $end->gt($start)) {
                throw ValidationException::withMessages(['ends_at' => __('club.resources.error.window')]);
            }
            $quantity = max(1, (int) ($data['quantity'] ?? 1));
            $setup = (int) ($data['setup_minutes'] ?? $resource->setup_minutes);
            $teardown = (int) ($data['teardown_minutes'] ?? $resource->teardown_minutes);
            $member = $this->member($data['club_member_id'] ?? null, $event->organization_id);

            $this->assertBookable($resource, $start->subMinutes($setup), $end->addMinutes($teardown), $quantity, $event->id, $member, $start);

            $booking = ClubResourceBooking::query()->create([
                'organization_id' => $event->organization_id,
                'club_resource_id' => $resource->id,
                'event_id' => $event->id,
                'club_member_id' => $member?->id,
                'quantity' => $quantity,
                'starts_at' => $start,
                'ends_at' => $end,
                'setup_minutes' => $setup,
                'teardown_minutes' => $teardown,
                'note' => $this->nullableString($data['note'] ?? null),
                'created_by_user_id' => $actor->id,
            ]);
            $booking->audit('club.resource.booked', ['event_id' => $event->id, 'quantity' => $quantity, 'window' => $start->toIso8601String() . '/' . $end->toIso8601String()]);

            return $booking;
        });
    }

    public function release(ClubResourceBooking $booking, ?User $actor = null): void {
        $booking->audit('club.resource.released', ['event_id' => $booking->event_id, 'actor_id' => $actor?->id]);
        $booking->delete();
    }

    /** Absage eines Termins gibt alle Belegungen frei. */
    public function releaseAll(Event $event, ?User $actor = null): int {
        $count = 0;
        foreach (ClubResourceBooking::query()->where('event_id', $event->id)->get() as $booking) {
            $this->release($booking, $actor);
            $count++;
        }

        return $count;
    }

    /**
     * Verschiebung: alle Belegungen des Termins auf das neue Fenster prüfen und
     * verschieben — bei Konflikt bleibt (durch Rollback des Aufrufers) alles beim Alten.
     *
     * @return int verschobene Belegungen
     */
    public function moveWithEvent(Event $event, CarbonInterface $previousStart, CarbonInterface $previousEnd): int {
        $bookings = ClubResourceBooking::query()->where('event_id', $event->id)->with('resource')->get();
        if ($bookings->isEmpty()) {
            return 0;
        }
        $shift = CarbonImmutable::instance($previousStart)->diffInSeconds(CarbonImmutable::instance($event->started_at), false);
        $durationDelta = CarbonImmutable::instance($event->ended_at)->diffInSeconds(CarbonImmutable::instance($event->started_at), false)
            - CarbonImmutable::instance($previousEnd)->diffInSeconds(CarbonImmutable::instance($previousStart), false);
        $moved = 0;
        foreach ($bookings as $booking) {
            $resource = $booking->resource;
            if (! $resource instanceof ClubResource) {
                continue;
            }
            $this->lockTree($resource);
            $start = CarbonImmutable::instance($booking->starts_at)->addSeconds((int) $shift);
            $end = CarbonImmutable::instance($booking->ends_at)->addSeconds((int) $shift + (int) $durationDelta);
            $this->assertBookable($resource, $start->subMinutes($booking->setup_minutes), $end->addMinutes($booking->teardown_minutes), $booking->quantity, $event->id, null, $start);
            $booking->update(['starts_at' => $start, 'ends_at' => $end]);
            $moved++;
        }
        if ($moved > 0) {
            $event->audit('club.resource.moved', ['bookings' => $moved]);
        }

        return $moved;
    }

    /**
     * Konflikte für ein Fenster (inkl. Puffer): Kapazität der Ressource,
     * Belegungen über-/untergeordneter Ressourcen, Raumkalender verknüpfter
     * Räume und Sperrzeiten.
     *
     * @return list<array{kind: string, resource: ClubResource, event: Event|null, closure: ClubResourceClosure|null}>
     */
    public function conflictsFor(ClubResource $resource, CarbonInterface $blockStart, CarbonInterface $blockEnd, int $quantity = 1, ?int $ignoreEventId = null): array {
        $conflicts = [];
        $start = Carbon::instance($blockStart);
        $end = Carbon::instance($blockEnd);

        // Eigene Ressource: Summe der Mengen gegen die Kapazität.
        $own = $this->overlappingBookings([$resource->id], $start, $end, $ignoreEventId);
        if ($own->sum('quantity') + $quantity > $resource->capacity) {
            foreach ($own as $booking) {
                $conflicts[] = ['kind' => 'booking', 'resource' => $resource, 'event' => $booking->event, 'closure' => null];
            }
            if ($own->isEmpty()) {
                $conflicts[] = ['kind' => 'capacity', 'resource' => $resource, 'event' => null, 'closure' => null];
            }
        }

        // Über- und Unterressourcen: jede Belegung sperrt vollständig (Halle ↔ Tisch).
        $related = $resource->ancestors()->merge($resource->descendants());
        if ($related->isNotEmpty()) {
            foreach ($this->overlappingBookings($related->map(fn(ClubResource $r): int => $r->id)->all(), $start, $end, $ignoreEventId) as $booking) {
                $conflicts[] = ['kind' => 'booking', 'resource' => $booking->resource ?? $resource, 'event' => $booking->event, 'closure' => null];
            }
        }

        // Raumkalender: verknüpfte Räume der Ressource und ihrer Verwandten.
        foreach ($related->push($resource) as $node) {
            $room = $node->room_id !== null ? $node->room : null;
            if (! $room instanceof Room) {
                continue;
            }
            foreach ($this->rooms->findConflicts($room, $start, $end, 0, 0, $ignoreEventId, false) as $event) {
                $conflicts[] = ['kind' => 'room', 'resource' => $node, 'event' => $event, 'closure' => null];
            }
        }

        // Sperrzeiten der Ressource und ihrer Verwandten.
        $ids = $related->pluck('id')->push($resource->id)->all();
        foreach (ClubResourceClosure::query()->whereIn('club_resource_id', $ids)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->with('resource')->get() as $closure) {
            $conflicts[] = ['kind' => 'closure', 'resource' => $closure->resource ?? $resource, 'event' => null, 'closure' => $closure];
        }

        return $conflicts;
    }

    /**
     * Für den Raumkalender: Termine, deren Ressourcenbelegungen einen Raum
     * blockieren (Ressource mit diesem Raum samt Über-/Unterressourcen).
     *
     * @return EloquentCollection<int, Event>
     */
    public function eventsBlockingRoom(Room $room, CarbonInterface $blockStart, CarbonInterface $blockEnd, ?int $ignoreEventId = null): EloquentCollection {
        $resources = ClubResource::query()->where('room_id', $room->id)->get();
        if ($resources->isEmpty()) {
            return new EloquentCollection();
        }
        /** @var array<int, int> $ids */
        $ids = [];
        foreach ($resources as $resource) {
            $ids[] = $resource->id;
            foreach ($resource->ancestors()->merge($resource->descendants()) as $related) {
                $ids[] = $related->id;
            }
        }
        $bookings = $this->overlappingBookings(array_values(array_unique($ids)), Carbon::instance($blockStart), Carbon::instance($blockEnd), $ignoreEventId);

        /** @var EloquentCollection<int, Event> $events */
        $events = new EloquentCollection($bookings->map(fn(ClubResourceBooking $booking): ?Event => $booking->event)->filter()->unique('id')->values()->all());

        return $events;
    }

    // ── Sperrzeiten ──────────────────────────────────────────────────────

    /** Sperrzeit (Witterung, Wartung): bestehende Belegungen werden zur Neuplanung markiert, nicht gelöscht. */
    public function close(ClubResource $resource, CarbonInterface $start, CarbonInterface $end, string $reason, User $actor): ClubResourceClosure {
        return DB::transaction(function () use ($resource, $start, $end, $reason, $actor): ClubResourceClosure {
            if (! CarbonImmutable::instance($end)->gt($start)) {
                throw ValidationException::withMessages(['ends_at' => __('club.resources.error.window')]);
            }
            $closure = ClubResourceClosure::query()->create([
                'organization_id' => $resource->organization_id,
                'club_resource_id' => $resource->id,
                'starts_at' => CarbonImmutable::instance($start),
                'ends_at' => CarbonImmutable::instance($end),
                'reason' => mb_substr(trim($reason), 0, 255),
                'created_by_user_id' => $actor->id,
            ]);
            $ids = $resource->descendants()->pluck('id')->merge($resource->ancestors()->pluck('id'))->push($resource->id)->all();
            $flagged = 0;
            foreach ($this->overlappingBookings($ids, Carbon::instance($start), Carbon::instance($end), null) as $booking) {
                $booking->update(['flagged_at' => now(), 'flag_reason' => mb_substr((string) __('club.resources.label.closed_reason', ['reason' => $closure->reason]), 0, 255)]);
                $flagged++;
            }
            $closure->audit('club.resource.closed', ['reason' => $closure->reason, 'flagged' => $flagged]);
            if ($resource->kind === ClubResourceKind::Horse) {
                // Reitbetrieb (MVP-854): betroffene Stunden zur Neuplanung, Leitung informieren — keine automatische Ersatzzuteilung.
                app(ClubHorseService::class)->flagUnavailable($resource, $closure->starts_at, $closure->ends_at, $closure->reason);
            }

            return $closure;
        });
    }

    public function reopen(ClubResourceClosure $closure, User $actor): void {
        DB::transaction(function () use ($closure, $actor): void {
            $resource = $closure->resource()->firstOrFail();
            $closure->audit('club.resource.reopened', ['actor_id' => $actor->id]);
            $closure->delete();
            $ids = $resource->descendants()->pluck('id')->merge($resource->ancestors()->pluck('id'))->push($resource->id)->all();
            foreach (ClubResourceBooking::query()->whereIn('club_resource_id', $ids)->whereNotNull('flagged_at')->get() as $booking) {
                $stillClosed = ClubResourceClosure::query()->whereIn('club_resource_id', $ids)
                    ->where('starts_at', '<', $booking->blockEnd())->where('ends_at', '>', $booking->blockStart())->exists();
                if (! $stillClosed) {
                    $booking->update(['flagged_at' => null, 'flag_reason' => null]);
                }
            }
        });
    }

    // ── Freigaben ────────────────────────────────────────────────────────

    public function grantClearance(ClubResource $resource, ClubMember $member, User $actor, ?CarbonInterface $validTo = null, ?string $note = null): ClubResourceClearance {
        if ($member->organization_id !== $resource->organization_id) {
            throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
        }
        /** @var ClubResourceClearance $clearance */
        $clearance = ClubResourceClearance::query()->updateOrCreate(
            ['club_resource_id' => $resource->id, 'club_member_id' => $member->id],
            [
                'organization_id' => $resource->organization_id,
                'granted_on' => CarbonImmutable::today()->toDateString(),
                'valid_to' => $validTo?->format('Y-m-d'),
                'granted_by_user_id' => $actor->id,
                'note' => $this->nullableString($note),
            ],
        );
        $clearance->audit('club.resource.clearanceGranted', ['member_id' => $member->id, 'valid_to' => $validTo?->format('Y-m-d')]);

        return $clearance;
    }

    public function revokeClearance(ClubResourceClearance $clearance, User $actor): void {
        $clearance->audit('club.resource.clearanceRevoked', ['actor_id' => $actor->id]);
        $clearance->delete();
    }

    public function hasClearance(ClubResource $resource, ClubMember $member, CarbonInterface $on): bool {
        if (! $resource->requires_clearance) {
            return true;
        }
        $clearance = ClubResourceClearance::query()->where('club_resource_id', $resource->id)->where('club_member_id', $member->id)->first();

        return $clearance instanceof ClubResourceClearance && $clearance->isValidOn($on);
    }

    /**
     * Angemeldete Teilnehmer ohne Freigabe für freigabepflichtige Ressourcen des Termins.
     *
     * @return Collection<int, array{resource: ClubResource, member: ClubMember}>
     */
    public function missingClearances(Event $event): Collection {
        $rows = collect();
        $bookings = ClubResourceBooking::query()->where('event_id', $event->id)->with('resource')->get();
        $required = $bookings->map(fn(ClubResourceBooking $b): ?ClubResource => $b->resource)->filter(fn(?ClubResource $r): bool => $r instanceof ClubResource && $r->requires_clearance);
        if ($required->isEmpty()) {
            return $rows;
        }
        $day = CarbonImmutable::instance($event->started_at);
        $members = ClubMember::query()->whereHas('eventParticipations', fn($q) => $q->where('event_id', $event->id)->where('status', ClubParticipationStatus::Registered->value))->get();
        foreach ($required as $resource) {
            foreach ($members as $member) {
                if (! $this->hasClearance($resource, $member, $day)) {
                    $rows->push(['resource' => $resource, 'member' => $member]);
                }
            }
        }

        return $rows;
    }

    // ── Helfer ───────────────────────────────────────────────────────────

    /** Sperrt die Ressource samt Verwandten (Zeilensperren) — Serialisierung gleichzeitiger Buchungen. */
    private function lockTree(ClubResource $resource): void {
        $ids = $resource->ancestors()->pluck('id')->merge($resource->descendants()->pluck('id'))->push($resource->id)->all();
        ClubResource::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }

    private function assertBookable(ClubResource $resource, CarbonInterface $blockStart, CarbonInterface $blockEnd, int $quantity, int $ignoreEventId, ?ClubMember $member, CarbonInterface $on): void {
        if ($quantity > $resource->capacity) {
            throw ValidationException::withMessages(['quantity' => __('club.resources.error.capacity', ['max' => $resource->capacity])]);
        }
        $asset = $resource->asset_id !== null ? $resource->asset : null;
        if ($asset !== null && ! $this->assetGuard->isUsable($asset, self::ASSET_CONTEXT)) {
            throw ValidationException::withMessages(['club_resource_id' => __('club.resources.error.asset_blocked', ['name' => $resource->name])]);
        }
        if ($member !== null && ! $this->hasClearance($resource, $member, $on)) {
            throw ValidationException::withMessages(['club_member_id' => __('club.resources.error.no_clearance', ['name' => $member->fullName(), 'resource' => $resource->name])]);
        }
        $conflicts = $this->conflictsFor($resource, $blockStart, $blockEnd, $quantity, $ignoreEventId);
        if ($conflicts !== []) {
            throw ValidationException::withMessages(['club_resource_id' => array_map(fn(array $c): string => $this->conflictText($c), $conflicts)]);
        }
    }

    /**
     * @param  array<int, int>  $resourceIds
     * @return EloquentCollection<int, ClubResourceBooking>
     */
    private function overlappingBookings(array $resourceIds, Carbon $start, Carbon $end, ?int $ignoreEventId): EloquentCollection {
        $rangeStart = $start->copy()->subDay();
        $rangeEnd = $end->copy()->addDay();

        return ClubResourceBooking::query()
            ->whereIn('club_resource_id', $resourceIds)
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->when($ignoreEventId !== null, fn($q) => $q->where('event_id', '!=', $ignoreEventId))
            ->whereHas('event', fn($q) => $q->whereNull('cancelled_at'))
            ->with(['event:id,title,started_at,ended_at,cancelled_at', 'resource:id,name,parent_id,room_id'])
            ->get()
            // Feinprüfung mit Puffern der bestehenden Belegung.
            ->filter(fn(ClubResourceBooking $booking): bool => $booking->blockStart()->lt($end) && $booking->blockEnd()->gt($start))
            ->values();
    }

    /** @param  array{kind: string, resource: ClubResource, event: Event|null, closure: ClubResourceClosure|null}  $conflict */
    private function conflictText(array $conflict): string {
        $title = $conflict['event'] !== null ? $conflict['event']->title : '';
        $reason = $conflict['closure'] !== null ? $conflict['closure']->reason : '';

        return match ($conflict['kind']) {
            'closure' => (string) __('club.resources.conflict.closed', ['resource' => $conflict['resource']->name, 'reason' => $reason]),
            'capacity' => (string) __('club.resources.conflict.capacity', ['resource' => $conflict['resource']->name, 'max' => $conflict['resource']->capacity]),
            'room' => (string) __('club.resources.conflict.room', ['resource' => $conflict['resource']->name, 'title' => $title]),
            default => (string) __('club.resources.conflict.booked', ['resource' => $conflict['resource']->name, 'title' => $title]),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(int $organizationId, array $data, ?ClubResource $current): array {
        $parentId = $this->nullableInt($data['parent_id'] ?? null);
        if ($parentId !== null) {
            $parent = ClubResource::query()->whereKey($parentId)->where('organization_id', $organizationId)->first();
            if ($parent === null) {
                throw ValidationException::withMessages(['parent_id' => __('club.resources.error.parent_foreign')]);
            }
            if ($current !== null && ($parent->id === $current->id || $parent->ancestors()->contains('id', $current->id))) {
                throw ValidationException::withMessages(['parent_id' => __('club.resources.error.parent_cycle')]);
            }
            if ($parent->ancestors()->count() + 1 >= ClubResource::MAX_DEPTH) {
                throw ValidationException::withMessages(['parent_id' => __('club.resources.error.too_deep')]);
            }
        }
        $roomId = $this->nullableInt($data['room_id'] ?? null);
        if ($roomId !== null && ! Room::query()->whereKey($roomId)->where('organization_id', $organizationId)->exists()) {
            throw ValidationException::withMessages(['room_id' => __('club.resources.error.room_foreign')]);
        }
        $assetId = $this->nullableInt($data['asset_id'] ?? null);
        if ($assetId !== null && ! \App\Models\Asset\Asset::query()->whereKey($assetId)->where('organization_id', $organizationId)->exists()) {
            throw ValidationException::withMessages(['asset_id' => __('club.resources.error.asset_foreign')]);
        }

        return [
            'parent_id' => $parentId,
            'room_id' => $roomId,
            'asset_id' => $assetId,
            'name' => mb_substr(trim((string) $data['name']), 0, 120),
            'kind' => ClubResourceKind::from((string) ($data['kind'] ?? ClubResourceKind::Other->value))->value,
            'capacity' => max(1, (int) ($data['capacity'] ?? 1)),
            'setup_minutes' => max(0, (int) ($data['setup_minutes'] ?? 0)),
            'teardown_minutes' => max(0, (int) ($data['teardown_minutes'] ?? 0)),
            'requires_clearance' => (bool) ($data['requires_clearance'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'notes' => $this->nullableString($data['notes'] ?? null),
        ];
    }

    private function member(mixed $id, int $organizationId): ?ClubMember {
        $memberId = $this->nullableInt($id);
        if ($memberId === null) {
            return null;
        }
        $member = ClubMember::query()->whereKey($memberId)->first();
        if ($member === null || $member->organization_id !== $organizationId) {
            throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
        }

        return $member;
    }

    private function time(mixed $value): ?CarbonImmutable {
        $string = $this->nullableString($value);

        return $string === null ? null : CarbonImmutable::parse($string, 'UTC');
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
