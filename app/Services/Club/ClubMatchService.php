<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMatchService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubAvailabilityStatus, ClubEventKind, ClubEventRoleKind, ClubEventVisibility, ClubLineupSlot, ClubLineupStatus, ClubMatchProposalSource, ClubParticipationSource, ClubProposalStatus, ClubResultFormat};
use App\Enums\Event\EventStatus;
use App\Models\Calendar\Event;
use App\Models\Club\{ClubEventRole, ClubGroup, ClubLineupEntry, ClubMatchAvailability, ClubMatchDetails, ClubMatchProposal, ClubMember, ClubSeason};
use App\Models\Platform\{Organization, User};
use App\Services\Concerns\AssertsStatusTransition;
use App\Support\Tz;
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\Helper\Data\CSV\StringHelper as CsvStringHelper;
use CommonToolkit\Parsers\CSVDocumentParser;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\{DateTimeParser, Reader};
use Throwable;

/**
 * Spieltage (Feature 159, MVP-852) — einzige Schreibstelle: Spieltag als
 * Vereinstermin mit Sportdetails, Verfügbarkeit (Zusage ≠ Nominierung),
 * Aufstellung im Format des Sportartenprofils mit Konfliktprüfung vor der
 * Freigabe, Terminrollen, manuell erfasstes Ergebnis und Spielplan-Import
 * als Vorschlagsliste, die vor Bestätigung nichts ändert.
 */
class ClubMatchService {
    use AssertsStatusTransition;

    public const DEFAULT_DURATION_MINUTES = 120;

    public function __construct(
        private readonly ClubEventService $events,
        private readonly ClubTeamService $teams,
    ) {}

    // ── Spieltag ─────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data  club_group_id, club_season_id, opponent_name, competition, is_home, venue,
     *                                      meet_at (UTC), started_at/ended_at (UTC), timezone, title, description,
     *                                      leader_user_id, room_id, club_group_ids (weitere Zielgruppen)
     */
    public function createMatch(Organization $organization, User $actor, array $data): Event {
        return DB::transaction(function () use ($organization, $actor, $data): Event {
            /** @var ClubGroup $team */
            $team = ClubGroup::query()->whereKey((int) $data['club_group_id'])->firstOrFail();
            if ($team->organization_id !== $organization->id) {
                throw ValidationException::withMessages(['club_group_id' => __('club.error.member_foreign')]);
            }
            $start = CarbonImmutable::parse((string) $data['started_at'], 'UTC');
            $season = $this->resolveSeason($organization->id, $data['club_season_id'] ?? null, $start);
            $groupIds = array_values(array_unique(array_merge([$team->id], array_map('intval', (array) ($data['club_group_ids'] ?? [])))));

            $event = $this->events->create($organization, $actor, [
                'title' => $this->title($team, $data),
                'description' => $data['description'] ?? null,
                'kind' => ClubEventKind::Match->value,
                'visibility' => ClubEventVisibility::Groups->value,
                'club_department_id' => $team->club_department_id,
                'club_group_ids' => $groupIds,
                'started_at' => $data['started_at'],
                'ended_at' => $data['ended_at'],
                'timezone' => $data['timezone'] ?? null,
                'leader_user_id' => $data['leader_user_id'] ?? $team->leader_user_id,
                'room_id' => $data['room_id'] ?? null,
            ]);

            $details = ClubMatchDetails::query()->create([
                'organization_id' => $organization->id,
                'event_id' => $event->id,
                'club_group_id' => $team->id,
                'club_season_id' => $season?->id,
                'club_squad_id' => $season !== null ? $this->teams->squadFor($team, $season, false)?->id : null,
                'lineup_status' => ClubLineupStatus::Draft->value,
            ] + $this->matchAttributes($data));
            $details->audit('club.match.created', ['team_id' => $team->id, 'opponent' => $details->opponent_name]);

            return $event->fresh(['clubDetails', 'clubGroups', 'clubMatch']) ?? $event;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function updateMatch(Event $event, User $actor, array $data): Event {
        return DB::transaction(function () use ($event, $actor, $data): Event {
            $details = $this->detailsOf($event);
            $team = $details->team()->firstOrFail();
            $eventData = [
                'kind' => ClubEventKind::Match->value,
                'visibility' => ClubEventVisibility::Groups->value,
                'club_department_id' => $team->club_department_id,
                'club_group_ids' => array_values(array_unique(array_merge([$team->id], array_map('intval', (array) ($data['club_group_ids'] ?? []))))),
                'title' => $this->title($team, $data + ['opponent_name' => $data['opponent_name'] ?? $details->opponent_name, 'is_home' => $data['is_home'] ?? $details->is_home]),
                'description' => $data['description'] ?? $event->description,
                'leader_user_id' => $data['leader_user_id'] ?? $event->responsible_user_id,
            ];
            foreach (['started_at', 'ended_at', 'timezone', 'room_id'] as $key) {
                if (array_key_exists($key, $data)) {
                    $eventData[$key] = $data[$key];
                }
            }
            $event = $this->events->update($event, $actor, $eventData);
            $attributes = $this->matchAttributes($data);
            if (array_key_exists('club_season_id', $data) || array_key_exists('started_at', $data)) {
                $season = $this->resolveSeason($event->organization_id, $data['club_season_id'] ?? null, CarbonImmutable::instance($event->started_at));
                $attributes['club_season_id'] = $season?->id;
                $attributes['club_squad_id'] = $season !== null ? $this->teams->squadFor($team, $season, false)?->id : null;
            }
            $details->update($attributes);
            $details->audit('club.match.updated');

            return $event->fresh(['clubDetails', 'clubGroups', 'clubMatch']) ?? $event;
        });
    }

    public function detailsOf(Event $event): ClubMatchDetails {
        /** @var ClubMatchDetails $details */
        $details = ClubMatchDetails::query()->where('event_id', $event->id)->firstOrFail();

        return $details;
    }

    // ── Verfügbarkeit ────────────────────────────────────────────────────

    /** Zusage/Absage eines Mitglieds — ändert keine Nominierung. */
    public function setAvailability(Event $event, ClubMember $member, ClubAvailabilityStatus $status, ?User $actor, ?string $note = null): ClubMatchAvailability {
        if ($member->organization_id !== $event->organization_id) {
            throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
        }
        /** @var ClubMatchAvailability $availability */
        $availability = ClubMatchAvailability::query()->updateOrCreate(
            ['event_id' => $event->id, 'club_member_id' => $member->id],
            [
                'organization_id' => $event->organization_id,
                'status' => $status->value,
                'note' => $this->nullableString($note),
                'responded_at' => CarbonImmutable::now(),
                'responded_by_user_id' => $actor?->id,
            ],
        );

        return $availability;
    }

    /**
     * Kandidaten für Aufstellung und Verfügbarkeit: Kader der Saison (inkl.
     * Gastspieler) plus aktive Gruppenmitglieder am Spieltag, mit Verfügbarkeit
     * und aktueller Aufstellungsposition.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function candidatesFor(Event $event): Collection {
        $details = $this->detailsOf($event);
        $team = $details->team()->firstOrFail();
        $day = $this->events->localDay($event);
        $season = $details->club_season_id !== null ? $details->season()->first() : null;
        $availabilities = ClubMatchAvailability::query()->where('event_id', $event->id)->get()->keyBy('club_member_id');
        $entries = ClubLineupEntry::query()->where('event_id', $event->id)->orderBy('order_no')->get()->groupBy('club_member_id');
        $rows = $this->teams->rosterOn($team, $day, $season);

        // Aufgestellte Personen außerhalb des Kaders (z. B. nach Kaderende) bleiben sichtbar.
        foreach ($entries->keys()->diff($rows->pluck('member.id')) as $memberId) {
            $member = ClubMember::query()->find($memberId);
            if ($member !== null) {
                $rows->push(['member' => $member, 'squad' => null]);
            }
        }

        /** @var Collection<int, array<string, mixed>> $result */
        $result = collect();
        foreach ($rows as $row) {
            $result->push($row + [
                'availability' => $availabilities->get($row['member']->id),
                'entries' => collect($entries->get($row['member']->id, [])),
            ]);
        }

        return $result;
    }

    // ── Aufstellung ──────────────────────────────────────────────────────

    /**
     * Aufstellung speichern (ersetzt alle Positionen). Nach einer Freigabe
     * fällt die Aufstellung auf „Entwurf“ zurück und muss erneut freigegeben werden.
     *
     * @param  list<array{club_member_id: int, slot: string|null, position_code?: string|null, jersey_no?: int|string|null, pairing_no?: int|string|null, order_no?: int|string|null}>  $rows
     * @return Collection<int, ClubLineupEntry>
     */
    public function saveLineup(Event $event, User $actor, array $rows): Collection {
        return DB::transaction(function () use ($event, $actor, $rows): Collection {
            $details = ClubMatchDetails::query()->where('event_id', $event->id)->lockForUpdate()->firstOrFail();
            $team = $details->team()->firstOrFail();
            $profile = $this->teams->profileFor($team);
            $allowed = $this->candidatesFor($event)->pluck('member.id')->all();
            $positionCodes = $profile?->positionCodes() ?? [];
            $entries = [];
            $counts = ['field' => 0, 'bench' => 0];
            $jerseys = [];
            $pairings = [];
            $order = 0;
            foreach ($rows as $row) {
                $slotValue = $this->nullableString($row['slot'] ?? null);
                if ($slotValue === null) {
                    continue;
                }
                $slot = ClubLineupSlot::from($slotValue);
                $memberId = (int) $row['club_member_id'];
                if (! in_array($memberId, $allowed, true)) {
                    throw ValidationException::withMessages(['lineup' => __('club.matches.error.not_in_roster')]);
                }
                if ($slot->isPairing() && ($profile === null || ! $profile->hasPairings())) {
                    throw ValidationException::withMessages(['lineup' => __('club.matches.error.pairing_not_allowed')]);
                }
                if ($slot === ClubLineupSlot::Double && ($profile === null || ! $profile->has_doubles)) {
                    throw ValidationException::withMessages(['lineup' => __('club.matches.error.doubles_not_allowed')]);
                }
                $position = $this->nullableString($row['position_code'] ?? null);
                if ($position !== null && $positionCodes !== [] && ! in_array($position, $positionCodes, true)) {
                    throw ValidationException::withMessages(['lineup' => __('club.matches.error.unknown_position', ['code' => $position])]);
                }
                $jersey = $this->nullableInt($row['jersey_no'] ?? null);
                if ($jersey !== null && $slot->slotKey() === 'team') {
                    if (isset($jerseys[$jersey])) {
                        throw ValidationException::withMessages(['lineup' => __('club.matches.error.jersey_taken', ['no' => $jersey])]);
                    }
                    $jerseys[$jersey] = true;
                }
                $pairing = $slot === ClubLineupSlot::Double ? ($this->nullableInt($row['pairing_no'] ?? null) ?? 1) : ($slot === ClubLineupSlot::Single ? $this->nullableInt($row['pairing_no'] ?? null) : null);
                if ($slot === ClubLineupSlot::Double) {
                    $pairings[$pairing] = ($pairings[$pairing] ?? 0) + 1;
                    if ($pairings[$pairing] > 2) {
                        throw ValidationException::withMessages(['lineup' => __('club.matches.error.double_overfull', ['no' => $pairing])]);
                    }
                }
                if ($slot === ClubLineupSlot::Field || $slot === ClubLineupSlot::Bench) {
                    $counts[$slot->value]++;
                }
                $key = $memberId . ':' . $slot->slotKey();
                if (isset($entries[$key])) {
                    throw ValidationException::withMessages(['lineup' => __('club.matches.error.member_twice')]);
                }
                $entries[$key] = [
                    'organization_id' => $event->organization_id,
                    'event_id' => $event->id,
                    'club_member_id' => $memberId,
                    'slot' => $slot->value,
                    'slot_key' => $slot->slotKey(),
                    'position_code' => $position,
                    'jersey_no' => $jersey,
                    'order_no' => $this->nullableInt($row['order_no'] ?? null) ?? ++$order,
                    'pairing_no' => $pairing,
                ];
            }
            if ($profile?->squad_size_field !== null && $counts['field'] > $profile->squad_size_field) {
                throw ValidationException::withMessages(['lineup' => __('club.matches.error.field_overfull', ['max' => $profile->squad_size_field])]);
            }
            if ($profile?->squad_size_bench !== null && $counts['bench'] > $profile->squad_size_bench) {
                throw ValidationException::withMessages(['lineup' => __('club.matches.error.bench_overfull', ['max' => $profile->squad_size_bench])]);
            }

            ClubLineupEntry::query()->where('event_id', $event->id)->delete();
            foreach ($entries as $attributes) {
                ClubLineupEntry::query()->create($attributes);
            }
            if ($details->isReleased()) {
                $this->assertStatusTransition($details->lineup_status, ClubLineupStatus::Draft);
                $details->update(['lineup_status' => ClubLineupStatus::Draft->value, 'lineup_conflict_note' => null]);
            }
            $details->audit('club.match.lineupSaved', ['entries' => count($entries), 'field' => $counts['field'], 'bench' => $counts['bench'], 'actor_id' => $actor->id]);

            return ClubLineupEntry::query()->where('event_id', $event->id)->orderBy('order_no')->get();
        });
    }

    /**
     * Konflikte der aufgestellten Personen: zeitgleicher Einsatz in einer
     * anderen Aufstellung oder ausdrückliche Absage.
     *
     * @return list<array{member: ClubMember, reason: string, event: Event|null}>
     */
    public function conflictsFor(Event $event): array {
        $memberIds = ClubLineupEntry::query()->where('event_id', $event->id)->distinct()->pluck('club_member_id')->all();
        if ($memberIds === []) {
            return [];
        }
        $members = ClubMember::query()->whereIn('id', $memberIds)->get()->keyBy('id');
        $conflicts = [];
        $overlapping = Event::query()
            ->where('organization_id', $event->organization_id)
            ->whereKeyNot($event->id)
            ->where('status', '!=', EventStatus::Cancelled->value)
            ->where('started_at', '<', $event->ended_at)
            ->where('ended_at', '>', $event->started_at)
            ->whereHas('clubLineupEntries', fn(Builder $q) => $q->whereIn('club_member_id', $memberIds))
            ->with(['clubLineupEntries' => fn($q) => $q->whereIn('club_member_id', $memberIds)])
            ->orderBy('started_at')
            ->get();
        foreach ($overlapping as $other) {
            foreach ($other->clubLineupEntries->pluck('club_member_id')->unique() as $memberId) {
                $member = $members->get($memberId);
                if ($member !== null) {
                    $conflicts[] = ['member' => $member, 'reason' => 'overlap', 'event' => $other];
                }
            }
        }
        $unavailable = ClubMatchAvailability::query()
            ->where('event_id', $event->id)
            ->whereIn('club_member_id', $memberIds)
            ->where('status', ClubAvailabilityStatus::Unavailable->value)
            ->pluck('club_member_id');
        foreach ($unavailable as $memberId) {
            $member = $members->get($memberId);
            if ($member !== null) {
                $conflicts[] = ['member' => $member, 'reason' => 'unavailable', 'event' => null];
            }
        }

        return $conflicts;
    }

    /**
     * Freigabe (Nominierung): Konflikte sperren, außer die Leitung übergeht sie
     * ausdrücklich mit Begründung. Nominierte werden Teilnehmer des Termins
     * (Anwesenheit wird weiterhin separat erfasst).
     */
    public function releaseLineup(Event $event, User $actor, bool $override = false, ?string $note = null): ClubMatchDetails {
        return DB::transaction(function () use ($event, $actor, $override, $note): ClubMatchDetails {
            $details = ClubMatchDetails::query()->where('event_id', $event->id)->lockForUpdate()->firstOrFail();
            $entries = ClubLineupEntry::query()->where('event_id', $event->id)->get();
            if ($entries->isEmpty()) {
                throw ValidationException::withMessages(['lineup' => __('club.matches.error.lineup_empty')]);
            }
            $conflicts = $this->conflictsFor($event);
            if ($conflicts !== [] && ! $override) {
                throw ValidationException::withMessages(['lineup' => array_map(fn(array $c): string => $this->conflictText($c), $conflicts)]);
            }
            if ($conflicts !== [] && $this->nullableString($note) === null) {
                throw ValidationException::withMessages(['note' => __('club.matches.error.override_note_required')]);
            }
            $this->assertStatusTransition($details->lineup_status, ClubLineupStatus::Released);
            $details->update([
                'lineup_status' => ClubLineupStatus::Released->value,
                'lineup_released_at' => CarbonImmutable::now(),
                'lineup_released_by_user_id' => $actor->id,
                'lineup_conflict_note' => $conflicts !== [] ? mb_substr((string) $note, 0, 255) : null,
            ]);
            foreach ($entries->pluck('club_member_id')->unique() as $memberId) {
                $member = ClubMember::query()->whereKey((int) $memberId)->first();
                if ($member !== null) {
                    $this->events->register($event, $member, $actor, ClubParticipationSource::Leader, null, true, (string) __('club.matches.label.nominated'));
                }
            }
            $details->audit('club.match.lineupReleased', ['persons' => $entries->pluck('club_member_id')->unique()->count(), 'conflicts' => count($conflicts), 'override' => $override]);

            return $details->refresh();
        });
    }

    public function withdrawLineup(Event $event, User $actor): ClubMatchDetails {
        $details = $this->detailsOf($event);
        $this->assertStatusTransition($details->lineup_status, ClubLineupStatus::Draft);
        $details->update(['lineup_status' => ClubLineupStatus::Draft->value, 'lineup_conflict_note' => null]);
        $details->audit('club.match.lineupWithdrawn', ['actor_id' => $actor->id]);

        return $details->refresh();
    }

    // ── Terminrollen ─────────────────────────────────────────────────────

    /**
     * Rolle am Termin (Schiedsrichter, Fahrdienst …): Mitglied, Benutzer oder
     * externer Name. Ein Mitglied wird Teilnehmer (Vereinsteilnahme), nicht Kaderplatz.
     *
     * @param  array<string, mixed>  $data  club_member_id | user_id | name; note
     */
    public function assignRole(Event $event, ClubEventRoleKind $role, array $data, User $actor): ClubEventRole {
        return DB::transaction(function () use ($event, $role, $data, $actor): ClubEventRole {
            $memberId = $this->nullableInt($data['club_member_id'] ?? null);
            $userId = $this->nullableInt($data['user_id'] ?? null);
            $name = $this->nullableString($data['name'] ?? null);
            if ($memberId === null && $userId === null && $name === null) {
                throw ValidationException::withMessages(['name' => __('club.matches.error.role_holder_required')]);
            }
            $member = $memberId !== null ? ClubMember::query()->whereKey($memberId)->firstOrFail() : null;
            if ($member !== null && $member->organization_id !== $event->organization_id) {
                throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
            }
            $duplicate = ClubEventRole::query()->where('event_id', $event->id)->where('role', $role->value)
                ->when($member !== null, fn(Builder $q) => $q->where('club_member_id', $member?->id))
                ->when($member === null && $userId !== null, fn(Builder $q) => $q->where('user_id', $userId))
                ->when($member === null && $userId === null, fn(Builder $q) => $q->where('name', $name))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['role' => __('club.matches.error.role_duplicate')]);
            }
            $assignment = ClubEventRole::query()->create([
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'role' => $role->value,
                'club_member_id' => $member?->id,
                'user_id' => $member === null ? $userId : null,
                'name' => $member === null && $userId === null ? $name : null,
                'note' => $this->nullableString($data['note'] ?? null),
            ]);
            if ($member !== null && $event->status !== EventStatus::Cancelled) {
                $this->events->register($event, $member, $actor, ClubParticipationSource::Leader, null, true, $role->label());
            }
            $assignment->audit('club.match.roleAssigned', ['role' => $role->value, 'member_id' => $member?->id, 'user_id' => $userId]);

            return $assignment;
        });
    }

    public function removeRole(ClubEventRole $assignment, User $actor): void {
        $assignment->audit('club.match.roleRemoved', ['role' => $assignment->role->value, 'actor_id' => $actor->id]);
        $assignment->delete();
    }

    // ── Ergebnis ─────────────────────────────────────────────────────────

    /**
     * Ergebnis im Format des Profils: Tore (home/away), Punkte je Abschnitt
     * oder Sätze (periods[]{home,away}); Torschützen o. Ä. als Notiz.
     *
     * @param  array<string, mixed>  $input
     */
    public function recordResult(Event $event, User $actor, array $input): ClubMatchDetails {
        $details = $this->detailsOf($event);
        $team = $details->team()->firstOrFail();
        $profile = $this->teams->profileFor($team);
        $format = $profile !== null ? $profile->result_format : ClubResultFormat::Goals;
        [$result, $summary] = $this->normalizeResult($format, $input);
        $previous = $details->result_summary;
        $details->update([
            'result' => $result,
            'result_summary' => $summary,
            'result_note' => $this->nullableString($input['result_note'] ?? null),
            'result_recorded_at' => CarbonImmutable::now(),
            'result_recorded_by_user_id' => $actor->id,
        ]);
        $details->audit('club.match.resultRecorded', ['format' => $format->value, 'from' => $previous, 'to' => $summary]);

        return $details->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    public function normalizeResult(ClubResultFormat $format, array $input): array {
        if ($format === ClubResultFormat::None) {
            return [null, null];
        }
        if ($format === ClubResultFormat::Goals) {
            $home = $this->score($input['home'] ?? null);
            $away = $this->score($input['away'] ?? null);
            if ($home === null || $away === null) {
                throw ValidationException::withMessages(['home' => __('club.matches.error.result_incomplete')]);
            }

            return [['home' => $home, 'away' => $away], $home . ':' . $away];
        }
        $periods = [];
        foreach ((array) ($input['periods'] ?? []) as $row) {
            $home = $this->score($row['home'] ?? null);
            $away = $this->score($row['away'] ?? null);
            if ($home === null && $away === null) {
                continue;
            }
            if ($home === null || $away === null) {
                throw ValidationException::withMessages(['periods' => __('club.matches.error.result_incomplete')]);
            }
            $periods[] = [$home, $away];
        }
        if ($periods === []) {
            throw ValidationException::withMessages(['periods' => __('club.matches.error.result_incomplete')]);
        }
        $detail = implode(', ', array_map(static fn(array $p): string => $p[0] . ':' . $p[1], $periods));
        if ($format === ClubResultFormat::Sets) {
            $won = count(array_filter($periods, static fn(array $p): bool => $p[0] > $p[1]));
            $lost = count(array_filter($periods, static fn(array $p): bool => $p[1] > $p[0]));

            return [['sets' => $periods, 'home' => $won, 'away' => $lost], $won . ':' . $lost . ' (' . $detail . ')'];
        }
        $home = array_sum(array_column($periods, 0));
        $away = array_sum(array_column($periods, 1));

        return [['periods' => $periods, 'home' => $home, 'away' => $away], $home . ':' . $away . ' (' . $detail . ')'];
    }

    // ── Spielplan-Import ─────────────────────────────────────────────────

    /**
     * CSV oder ICS als Vorschlagsliste: nichts wird angelegt, Dubletten zu
     * bestehenden Spieltagen werden markiert, bekannte Zeilen übersprungen.
     *
     * @return array{created: int, skipped: int, duplicates: int, errors: list<string>}
     */
    public function importProposals(ClubGroup $team, User $actor, ClubMatchProposalSource $source, string $content, ?string $timezone = null): array {
        $tz = Tz::isValid($timezone) && $timezone !== null ? $timezone : Tz::current();
        $rows = $source === ClubMatchProposalSource::Csv ? $this->rowsFromCsv($team, $content, $tz) : $this->rowsFromIcs($team, $content, $tz);
        $summary = ['created' => 0, 'skipped' => 0, 'duplicates' => 0, 'errors' => $rows['errors']];
        foreach ($rows['rows'] as $row) {
            $key = (string) CryptoHelper::hash($team->id . '|' . $row['starts_at']->utc()->format('Y-m-d H:i') . '|' . mb_strtolower(trim($row['opponent_name'])));
            if (ClubMatchProposal::query()->where('organization_id', $team->organization_id)->where('dedupe_key', $key)->exists()) {
                $summary['skipped']++;

                continue;
            }
            // Mögliche Dublette: derselbe Gegner am selben (lokalen) Tag — auch bei korrigierter Anstoßzeit.
            $dayStart = $row['starts_at']->startOfDay();
            $duplicate = ClubMatchDetails::query()
                ->where('club_group_id', $team->id)
                ->whereRaw('LOWER(opponent_name) = ?', [mb_strtolower(trim($row['opponent_name']))])
                ->whereHas('event', fn(Builder $q) => $q->where('started_at', '>=', $dayStart->utc())->where('started_at', '<', $dayStart->addDay()->utc()))
                ->value('event_id');
            $proposal = ClubMatchProposal::query()->create([
                'organization_id' => $team->organization_id,
                'club_group_id' => $team->id,
                'source' => $source->value,
                'dedupe_key' => $key,
                'starts_at' => $row['starts_at']->utc(),
                'ends_at' => $row['ends_at']?->utc(),
                'opponent_name' => mb_substr(trim($row['opponent_name']), 0, 150),
                'competition' => $row['competition'] !== null ? mb_substr($row['competition'], 0, 120) : null,
                'is_home' => $row['is_home'],
                'venue' => $row['venue'] !== null ? mb_substr($row['venue'], 0, 200) : null,
                'raw' => $row['raw'],
                'status' => ClubProposalStatus::Open->value,
                'duplicate_event_id' => $duplicate !== null ? (int) $duplicate : null,
                'imported_by_user_id' => $actor->id,
            ]);
            $summary['created']++;
            if ($proposal->duplicate_event_id !== null) {
                $summary['duplicates']++;
            }
        }
        if ($summary['created'] > 0) {
            $team->audit('club.match.proposalsImported', ['source' => $source->value] + $summary);
        }

        return $summary;
    }

    /**
     * Vorschlag übernehmen (mit Korrekturen der Leitung) — erst hier entsteht der Spieltag.
     *
     * @param  array<string, mixed>  $overrides  opponent_name, competition, is_home, venue, started_at/ended_at (UTC), timezone, title, room_id, leader_user_id
     */
    public function acceptProposal(ClubMatchProposal $proposal, User $actor, array $overrides = []): Event {
        return DB::transaction(function () use ($proposal, $actor, $overrides): Event {
            /** @var ClubMatchProposal $proposal */
            $proposal = ClubMatchProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if (! $proposal->isOpen()) {
                throw ValidationException::withMessages(['proposal' => __('club.matches.error.proposal_decided')]);
            }
            $start = $proposal->starts_at;
            $end = $proposal->ends_at ?? CarbonImmutable::instance($start)->addMinutes(self::DEFAULT_DURATION_MINUTES);
            $event = $this->createMatch($proposal->organization()->firstOrFail(), $actor, array_merge([
                'club_group_id' => $proposal->club_group_id,
                'opponent_name' => $proposal->opponent_name,
                'competition' => $proposal->competition,
                'is_home' => $proposal->is_home,
                'venue' => $proposal->venue,
                'started_at' => CarbonImmutable::instance($start)->utc()->format('Y-m-d H:i:s'),
                'ended_at' => CarbonImmutable::instance($end)->utc()->format('Y-m-d H:i:s'),
                'timezone' => Tz::current(),
            ], array_filter($overrides, static fn(mixed $v): bool => $v !== null && $v !== '')));
            $proposal->update(['status' => ClubProposalStatus::Confirmed->value, 'event_id' => $event->id, 'decided_by_user_id' => $actor->id, 'decided_at' => CarbonImmutable::now()]);
            $proposal->audit('club.match.proposalAccepted', ['event_id' => $event->id]);

            return $event;
        });
    }

    public function dismissProposal(ClubMatchProposal $proposal, User $actor): ClubMatchProposal {
        if (! $proposal->isOpen()) {
            throw ValidationException::withMessages(['proposal' => __('club.matches.error.proposal_decided')]);
        }
        $proposal->update(['status' => ClubProposalStatus::Dismissed->value, 'decided_by_user_id' => $actor->id, 'decided_at' => CarbonImmutable::now()]);
        $proposal->audit('club.match.proposalDismissed');

        return $proposal->refresh();
    }

    // ── Helfer ───────────────────────────────────────────────────────────

    /**
     * @return array{rows: list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable|null, opponent_name: string, competition: string|null, is_home: bool, venue: string|null, raw: array<string, mixed>}>, errors: list<string>}
     */
    private function rowsFromCsv(ClubGroup $team, string $content, string $tz): array {
        $errors = [];
        $rows = [];
        try {
            $delimiter = CsvStringHelper::detectDelimiter($content);
            $document = CSVDocumentParser::fromString($content, $delimiter, '"', true, null, false);
        } catch (Throwable $e) {
            return ['rows' => [], 'errors' => [(string) __('import.error.format.parse', ['reason' => $e->getMessage()])]];
        }
        $header = $document->getHeader();
        if ($header === null) {
            return ['rows' => [], 'errors' => [(string) __('club.matches.error.import_header')]];
        }
        $col = static fn(array $aliases): ?int => $header->getColumnIndexByAliases($aliases);
        $dateIdx = $col(['datum', 'date', 'spieltag', 'tag', 'day']);
        $timeIdx = $col(['zeit', 'uhrzeit', 'time', 'anpfiff', 'beginn', 'start', 'startzeit']);
        $endIdx = $col(['ende', 'end', 'bis', 'endzeit']);
        $opponentIdx = $col(['gegner', 'opponent', 'gegner_name']);
        // Mit Gegner-Spalte ist „Heim“ ein H/A-Kennzeichen, ohne sie der Name der Heimmannschaft.
        $flagAliases = ['heimspiel', 'heim/auswaerts', 'heim_auswaerts', 'h/a', 'ha', 'home_away', 'art'];
        if ($opponentIdx !== null) {
            $flagAliases = array_merge($flagAliases, ['heim', 'home']);
        }
        $homeFlagIdx = $col($flagAliases);
        $homeTeamIdx = $col(['heim', 'heimmannschaft', 'home', 'home_team', 'mannschaft_heim']);
        $awayTeamIdx = $col(['gast', 'gastmannschaft', 'away', 'away_team', 'auswaerts', 'mannschaft_gast']);
        $venueIdx = $col(['spielort', 'ort', 'venue', 'location', 'halle', 'platz', 'sportstaette']);
        $competitionIdx = $col(['wettbewerb', 'liga', 'staffel', 'competition', 'league', 'runde']);
        if ($dateIdx === null || ($opponentIdx === null && ($homeTeamIdx === null || $awayTeamIdx === null))) {
            return ['rows' => [], 'errors' => [(string) __('club.matches.error.import_header')]];
        }
        $teamName = mb_strtolower(trim($team->name));
        foreach ($document->getRows() as $index => $line) {
            $lineNo = $index + 2;
            $raw = [];
            foreach ($header->getColumnNames() as $i => $name) {
                $raw[$name] = $header->getValueByIndex($line, $i);
            }
            $start = $this->parseLocalDateTime($header->getValueByIndex($line, $dateIdx), $timeIdx !== null ? $header->getValueByIndex($line, $timeIdx) : null, $tz);
            if ($start === null) {
                $errors[] = (string) __('club.matches.error.import_row_date', ['line' => $lineNo]);

                continue;
            }
            $end = $endIdx !== null ? $this->parseLocalDateTime($header->getValueByIndex($line, $dateIdx), $header->getValueByIndex($line, $endIdx), $tz) : null;
            $isHome = true;
            $opponent = null;
            if ($opponentIdx !== null) {
                $opponent = $header->getValueByIndex($line, $opponentIdx);
                if ($homeFlagIdx !== null) {
                    $isHome = $this->parseHomeFlag($header->getValueByIndex($line, $homeFlagIdx)) ?? true;
                }
            }
            if (($opponent === null || $opponent === '') && $homeTeamIdx !== null && $awayTeamIdx !== null) {
                $home = (string) $header->getValueByIndex($line, $homeTeamIdx);
                $away = (string) $header->getValueByIndex($line, $awayTeamIdx);
                if ($teamName !== '' && str_contains(mb_strtolower($away), $teamName) && ! str_contains(mb_strtolower($home), $teamName)) {
                    $isHome = false;
                    $opponent = $home;
                } else {
                    $opponent = $away;
                }
            }
            if ($opponent === null || trim($opponent) === '') {
                $errors[] = (string) __('club.matches.error.import_row_opponent', ['line' => $lineNo]);

                continue;
            }
            $rows[] = [
                'starts_at' => $start,
                'ends_at' => $end !== null && $end->gt($start) ? $end : null,
                'opponent_name' => $opponent,
                'competition' => $competitionIdx !== null ? $this->nullableString($header->getValueByIndex($line, $competitionIdx)) : null,
                'is_home' => $isHome,
                'venue' => $venueIdx !== null ? $this->nullableString($header->getValueByIndex($line, $venueIdx)) : null,
                'raw' => $raw,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @return array{rows: list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable|null, opponent_name: string, competition: string|null, is_home: bool, venue: string|null, raw: array<string, mixed>}>, errors: list<string>}
     */
    private function rowsFromIcs(ClubGroup $team, string $content, string $tz): array {
        try {
            $document = Reader::read($content, Reader::OPTION_FORGIVING | Reader::OPTION_IGNORE_INVALID_LINES);
        } catch (Throwable $e) {
            return ['rows' => [], 'errors' => [(string) __('import.error.format.parse', ['reason' => $e->getMessage()])]];
        }
        $zone = new DateTimeZone($tz);
        $teamName = mb_strtolower(trim($team->name));
        $rows = [];
        $errors = [];
        foreach ($document->select('VEVENT') as $vevent) {
            if (! $vevent instanceof VEvent) {
                continue;
            }
            $summary = trim((string) ($vevent->SUMMARY ?? ''));
            $dtstart = $vevent->DTSTART ?? null;
            if ($dtstart === null || ! $dtstart->hasTime()) {
                $errors[] = (string) __('import.error.ical.noTime', ['event' => $summary !== '' ? $summary : (string) ($vevent->UID ?? '')]);

                continue;
            }
            $start = CarbonImmutable::instance($dtstart->getDateTime($zone))->setTimezone($zone);
            $end = null;
            if (isset($vevent->DTEND) && $vevent->DTEND->hasTime()) {
                $end = CarbonImmutable::instance($vevent->DTEND->getDateTime($zone))->setTimezone($zone);
            } elseif (isset($vevent->DURATION)) {
                $interval = DateTimeParser::parseDuration((string) $vevent->DURATION);
                $end = $interval instanceof \DateInterval ? $start->add($interval) : null;
            }
            $isHome = true;
            $opponent = $summary;
            $parts = preg_split('/\s+[-–:]\s+|\s+vs\.?\s+/iu', $summary, 2) ?: [];
            if (count($parts) === 2) {
                [$first, $second] = array_map('trim', $parts);
                if ($teamName !== '' && str_contains(mb_strtolower($second), $teamName) && ! str_contains(mb_strtolower($first), $teamName)) {
                    $isHome = false;
                    $opponent = $first;
                } else {
                    $opponent = $second;
                }
            }
            if ($opponent === '') {
                $errors[] = (string) __('club.matches.error.import_row_opponent', ['line' => (string) ($vevent->UID ?? '?')]);

                continue;
            }
            $rows[] = [
                'starts_at' => $start,
                'ends_at' => $end !== null && $end->gt($start) ? $end : null,
                'opponent_name' => $opponent,
                'competition' => null,
                'is_home' => $isHome,
                'venue' => $this->nullableString((string) ($vevent->LOCATION ?? '')),
                'raw' => ['uid' => (string) ($vevent->UID ?? ''), 'summary' => $summary, 'description' => trim((string) ($vevent->DESCRIPTION ?? ''))],
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    private function parseLocalDateTime(?string $date, ?string $time, string $tz): ?CarbonImmutable {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }
        $time = trim((string) $time);
        $candidates = $time !== '' ? [$date . ' ' . $time] : [$date];
        if ($time === '' && preg_match('/^(.+?)\s+(\d{1,2}:\d{2})$/', $date, $m) === 1) {
            $candidates = [$date];
        }
        foreach ($candidates as $candidate) {
            foreach (['d.m.Y H:i', 'd.m.y H:i', 'Y-m-d H:i', 'd.m.Y H:i:s', 'Y-m-d H:i:s', 'Y-m-d\TH:i', 'd.m.Y', 'd.m.y', 'Y-m-d'] as $format) {
                $parsed = CarbonImmutable::createFromFormat($format, $candidate, $tz);
                if ($parsed instanceof CarbonImmutable && $parsed->format($format) === $candidate) {
                    return str_contains($format, 'H') ? $parsed : $parsed->startOfDay();
                }
            }
        }

        return null;
    }

    private function parseHomeFlag(?string $value): ?bool {
        $value = mb_strtolower(trim((string) $value));

        return match (true) {
            $value === '' => null,
            in_array($value, ['h', 'heim', 'home', 'ja', 'yes', '1', 'true', 'heimspiel'], true) => true,
            in_array($value, ['a', 'g', 'auswärts', 'auswaerts', 'away', 'gast', 'nein', 'no', '0', 'false'], true) => false,
            default => null,
        };
    }

    private function resolveSeason(int $organizationId, mixed $seasonId, CarbonInterface $start): ?ClubSeason {
        $id = $this->nullableInt($seasonId);
        if ($id !== null) {
            /** @var ClubSeason $season */
            $season = ClubSeason::query()->whereKey($id)->firstOrFail();
            if ($season->organization_id !== $organizationId) {
                throw ValidationException::withMessages(['club_season_id' => __('club.teams.error.season_foreign')]);
            }

            return $season;
        }

        return $this->teams->seasonContaining($organizationId, $start);
    }

    /** @param  array<string, mixed>  $data */
    private function title(ClubGroup $team, array $data): string {
        $title = $this->nullableString($data['title'] ?? null);
        if ($title !== null) {
            return mb_substr($title, 0, 200);
        }
        $opponent = trim((string) ($data['opponent_name'] ?? ''));
        $isHome = (bool) ($data['is_home'] ?? true);

        return mb_substr($isHome ? $team->name . ' – ' . $opponent : $opponent . ' – ' . $team->name, 0, 200);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function matchAttributes(array $data): array {
        $attributes = [];
        if (array_key_exists('opponent_name', $data)) {
            $attributes['opponent_name'] = mb_substr(trim((string) $data['opponent_name']), 0, 150);
        }
        foreach (['competition', 'venue'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $this->nullableString($data[$key]);
            }
        }
        if (array_key_exists('is_home', $data)) {
            $attributes['is_home'] = (bool) $data['is_home'];
        }
        if (array_key_exists('meet_at', $data)) {
            $meet = $this->nullableString($data['meet_at']);
            $attributes['meet_at'] = $meet === null ? null : CarbonImmutable::parse($meet, 'UTC');
        }

        return $attributes;
    }

    /** @param  array{member: ClubMember, reason: string, event: Event|null}  $conflict */
    private function conflictText(array $conflict): string {
        if ($conflict['reason'] === 'unavailable') {
            return (string) __('club.matches.conflict.unavailable', ['name' => $conflict['member']->fullName()]);
        }

        return (string) __('club.matches.conflict.overlap', ['name' => $conflict['member']->fullName(), 'title' => $conflict['event'] !== null ? $conflict['event']->title : '']);
    }

    private function score(mixed $value): ?int {
        $string = $this->nullableString($value);
        if ($string === null) {
            return null;
        }
        if (! ctype_digit($string) || (int) $string > 999) {
            throw ValidationException::withMessages(['home' => __('club.matches.error.result_invalid')]);
        }

        return (int) $string;
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
