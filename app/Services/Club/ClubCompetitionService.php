<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubCompetitionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubAttendanceSheetStatus, ClubAttendanceStatus, ClubEntryStatus, ClubEventKind, ClubEventRoleKind, ClubEventVisibility, ClubGroupMembershipStatus, ClubParticipationSource, ClubSportFamily};
use App\Models\Calendar\Event;
use App\Models\Club\{ClubAttendanceRecord, ClubAttendanceRequirement, ClubCompetitionDetails, ClubCompetitionEntry, ClubEventRole, ClubMember, ClubPerformance, ClubSportProfile, ClubStartRight};
use App\Models\Platform\{Organization, User};
use App\Services\Concerns\AssertsStatusTransition;
use App\Support\Query\DateRange;
use App\Support\Tz;
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Individual-, Wettkampf- und Schießsport (Feature 159, MVP-855) — einzige
 * Schreibstelle: Wettkämpfe mit Disziplinliste, Meldungen (ohne gültiges
 * Startrecht „zur Klärung“, nie still abgelehnt), Startrechte als dokumentierte
 * Prüfung, manuell erfasste Leistungen mit Bestätigung und Bestleistungen,
 * Nachweisliste aus bestätigten Anwesenheiten gegen Vereinsanforderungen.
 * Keine gesetzlichen Schwellen, kein Verbandsabgleich, keine Waffenverwaltung.
 */
class ClubCompetitionService {
    use AssertsStatusTransition;

    public function __construct(
        private readonly ClubEventService $events,
        private readonly ClubFeeService $fees,
    ) {}

    // ── Wettkampf ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data  club_sport_profile_id, disciplines (Codes), venue, organizer, entry_fee, requires_start_right, notes
     *                                      + Termindaten (title, started_at/ended_at UTC, timezone, club_group_ids, visibility, leader_user_id, room_id, registration_lead_hours)
     */
    public function create(Organization $organization, User $actor, array $data): Event {
        return DB::transaction(function () use ($organization, $actor, $data): Event {
            $profile = $this->profileOf($organization, $data['club_sport_profile_id'] ?? null);
            $event = $this->events->create($organization, $actor, ['kind' => ClubEventKind::Competition->value, 'visibility' => $data['visibility'] ?? ClubEventVisibility::Club->value] + $data);
            $details = ClubCompetitionDetails::query()->create([
                'organization_id' => $organization->id,
                'event_id' => $event->id,
                'club_sport_profile_id' => $profile->id,
                'currency' => $this->fees->defaultCurrency()->value,
            ] + $this->attributes($profile, $data));
            $details->audit('club.competition.created', ['profile_id' => $profile->id, 'disciplines' => $details->disciplines]);

            return $event->fresh(['clubDetails', 'clubGroups']) ?? $event;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Event $event, User $actor, array $data): Event {
        return DB::transaction(function () use ($event, $actor, $data): Event {
            $details = $this->detailsOf($event);
            $profile = $details->profile()->firstOrFail();
            $event = $this->events->update($event, $actor, ['kind' => ClubEventKind::Competition->value, 'visibility' => $data['visibility'] ?? $event->clubDetails?->visibility->value ?? ClubEventVisibility::Club->value] + $data);
            $details->update($this->attributes($profile, $data));
            $details->audit('club.competition.updated');

            return $event->fresh(['clubDetails', 'clubGroups']) ?? $event;
        });
    }

    public function detailsOf(Event $event): ClubCompetitionDetails {
        /** @var ClubCompetitionDetails $details */
        $details = ClubCompetitionDetails::query()->where('event_id', $event->id)->firstOrFail();

        return $details;
    }

    // ── Meldungen ────────────────────────────────────────────────────────

    /**
     * Meldung je Disziplin; ohne gültiges Startrecht Status „zur Klärung“.
     * Die Meldung macht das Mitglied zum Teilnehmer (Fristen/Plätze gelten).
     *
     * @param  list<string>  $disciplineCodes
     * @return Collection<int, ClubCompetitionEntry>
     */
    public function enter(Event $event, ClubMember $member, array $disciplineCodes, User $actor, ClubParticipationSource $source = ClubParticipationSource::Leader, bool $force = false): Collection {
        return DB::transaction(function () use ($event, $member, $disciplineCodes, $actor, $source, $force): Collection {
            $details = $this->detailsOf($event);
            if ($member->organization_id !== $event->organization_id) {
                throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
            }
            $codes = array_values(array_unique(array_filter(array_map(static fn($c): string => trim((string) $c), $disciplineCodes))));
            if ($codes === []) {
                throw ValidationException::withMessages(['disciplines' => __('club.competitions.error.discipline_required')]);
            }
            foreach ($codes as $code) {
                if (! in_array($code, $details->disciplines, true)) {
                    throw ValidationException::withMessages(['disciplines' => __('club.competitions.error.discipline_unknown', ['code' => $code])]);
                }
            }
            $this->events->register($event, $member, $actor, $source, null, $force);
            $day = $this->localDay($event);
            $hasRight = ! $details->requires_start_right || $this->hasStartRight($member, $details->profile()->firstOrFail(), $day);
            $entries = collect();
            foreach ($codes as $code) {
                /** @var ClubCompetitionEntry|null $existing */
                $existing = ClubCompetitionEntry::query()->where('event_id', $event->id)->where('club_member_id', $member->id)->where('discipline_code', $code)->first();
                $status = $hasRight ? ClubEntryStatus::Registered : ClubEntryStatus::NeedsReview;
                $attributes = [
                    'status' => $status->value,
                    'review_reason' => $hasRight ? null : (string) __('club.competitions.label.no_start_right'),
                    'fee_amount' => $details->entry_fee?->getAmount(),
                    'currency' => $details->currency->value,
                    'registered_by_user_id' => $actor->id,
                    'registered_at' => now(),
                ];
                if ($existing !== null) {
                    if ($existing->status !== $status) {
                        $this->assertStatusTransition($existing->status, $status);
                    }
                    $existing->update($attributes);
                    $entry = $existing->refresh();
                } else {
                    $entry = ClubCompetitionEntry::query()->create(['organization_id' => $event->organization_id, 'event_id' => $event->id, 'club_member_id' => $member->id, 'discipline_code' => $code] + $attributes);
                }
                $entry->audit('club.competition.entered', ['discipline' => $code, 'status' => $status->value, 'source' => $source->value]);
                $entries->push($entry);
            }

            return $entries;
        });
    }

    /** Klärung durch die Leitung: Meldung trotz fehlendem Startrecht freigeben (dokumentiert) oder nach Nachweis. */
    public function clearEntry(ClubCompetitionEntry $entry, User $actor, ?string $note = null): ClubCompetitionEntry {
        $this->assertStatusTransition($entry->status, ClubEntryStatus::Registered);
        $entry->update(['status' => ClubEntryStatus::Registered->value, 'review_reason' => null]);
        $entry->audit('club.competition.entryCleared', ['note' => $note, 'actor_id' => $actor->id]);

        return $entry->refresh();
    }

    public function withdraw(ClubCompetitionEntry $entry, User $actor, ?string $note = null): ClubCompetitionEntry {
        $this->assertStatusTransition($entry->status, ClubEntryStatus::Withdrawn);
        $entry->update(['status' => ClubEntryStatus::Withdrawn->value, 'fee_amount' => null]);
        $entry->audit('club.competition.withdrawn', ['note' => $note, 'actor_id' => $actor->id]);

        return $entry->refresh();
    }

    // ── Startrecht ───────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $data  club_sport_profile_id, reference, valid_from, valid_to, note */
    public function grantStartRight(ClubMember $member, array $data, User $actor): ClubStartRight {
        $profileId = $this->nullableInt($data['club_sport_profile_id'] ?? null);
        if ($profileId !== null && ! ClubSportProfile::query()->whereKey($profileId)->where('organization_id', $member->organization_id)->exists()) {
            throw ValidationException::withMessages(['club_sport_profile_id' => __('club.teams.error.profile_foreign')]);
        }
        $from = $this->date($data['valid_from'] ?? null) ?? CarbonImmutable::today();
        $to = $this->date($data['valid_to'] ?? null);
        if ($to !== null && $to->lt($from)) {
            throw ValidationException::withMessages(['valid_to' => __('club.teams.error.validity_range')]);
        }
        $right = ClubStartRight::query()->create([
            'organization_id' => $member->organization_id,
            'club_member_id' => $member->id,
            'club_sport_profile_id' => $profileId,
            'reference' => $this->nullableString($data['reference'] ?? null),
            'valid_from' => $from->toDateString(),
            'valid_to' => $to?->toDateString(),
            'note' => $this->nullableString($data['note'] ?? null),
            'granted_by_user_id' => $actor->id,
        ]);
        $right->audit('club.competition.startRightGranted', ['profile_id' => $profileId, 'valid_to' => $to?->toDateString()]);
        $this->reviewEntriesFor($member);

        return $right;
    }

    public function revokeStartRight(ClubStartRight $right, User $actor): void {
        $right->audit('club.competition.startRightRevoked', ['actor_id' => $actor->id]);
        $right->delete();
    }

    public function hasStartRight(ClubMember $member, ClubSportProfile $profile, CarbonInterface $on): bool {
        return ClubStartRight::query()
            ->where('club_member_id', $member->id)
            ->where(fn($q) => $q->whereNull('club_sport_profile_id')->orWhere('club_sport_profile_id', $profile->id))
            ->get()
            ->contains(fn(ClubStartRight $r): bool => $r->isValidOn($on));
    }

    // ── Leistungen ───────────────────────────────────────────────────────

    /**
     * Leistung erfassen (Training oder Wettkampf); Einheit und Vergleichsrichtung
     * werden aus dem Profil eingefroren. Bestätigung ist ein eigener Schritt.
     *
     * @param  array<string, mixed>  $data  club_sport_profile_id, discipline_code, performed_on, value, placement, note, event_id, confirm
     */
    public function recordPerformance(ClubMember $member, array $data, User $actor): ClubPerformance {
        $profile = $this->profileOf(Organization::query()->findOrFail($member->organization_id), $data['club_sport_profile_id'] ?? null);
        $code = trim((string) ($data['discipline_code'] ?? ''));
        $discipline = $this->disciplineOf($profile, $code);
        $value = NumberHelper::normalizeDecimalStringOrNull((string) ($data['value'] ?? ''));
        if ($value === null) {
            throw ValidationException::withMessages(['value' => __('club.competitions.error.value_invalid')]);
        }
        $eventId = $this->nullableInt($data['event_id'] ?? null);
        $performance = ClubPerformance::query()->create([
            'organization_id' => $member->organization_id,
            'club_member_id' => $member->id,
            'club_sport_profile_id' => $profile->id,
            'event_id' => $eventId,
            'discipline_code' => $code,
            'performed_on' => ($this->date($data['performed_on'] ?? null) ?? CarbonImmutable::today())->toDateString(),
            'value' => $value,
            'unit' => $discipline['unit'],
            'lower_is_better' => $discipline['lower_is_better'],
            'placement' => $this->nullableInt($data['placement'] ?? null),
            'note' => $this->nullableString($data['note'] ?? null),
            'recorded_by_user_id' => $actor->id,
        ]);
        $performance->audit('club.competition.performanceRecorded', ['discipline' => $code, 'value' => $value]);
        if ((bool) ($data['confirm'] ?? false)) {
            $this->confirmPerformance($performance, $actor);
        }

        return $performance->refresh();
    }

    public function confirmPerformance(ClubPerformance $performance, User $actor): ClubPerformance {
        if ($performance->isConfirmed()) {
            return $performance;
        }
        $performance->update(['confirmed_at' => now(), 'confirmed_by_user_id' => $actor->id]);
        $performance->audit('club.competition.performanceConfirmed', ['value' => $performance->value]);

        return $performance->refresh();
    }

    /**
     * Korrektur (auditiert): Wert, Platzierung, Notiz; die Bestätigung erlischt.
     *
     * @param  array<string, mixed>  $data
     */
    public function correctPerformance(ClubPerformance $performance, array $data, User $actor): ClubPerformance {
        $value = NumberHelper::normalizeDecimalStringOrNull((string) ($data['value'] ?? ''));
        if ($value === null) {
            throw ValidationException::withMessages(['value' => __('club.competitions.error.value_invalid')]);
        }
        $before = ['value' => $performance->value, 'placement' => $performance->placement];
        $performance->update([
            'value' => $value,
            'placement' => $this->nullableInt($data['placement'] ?? null),
            'note' => $this->nullableString($data['note'] ?? null),
            'confirmed_at' => null,
            'confirmed_by_user_id' => null,
        ]);
        $performance->audit('club.competition.performanceCorrected', ['from' => $before, 'to' => ['value' => $value, 'placement' => $performance->placement], 'actor_id' => $actor->id]);

        return $performance->refresh();
    }

    public function deletePerformance(ClubPerformance $performance, User $actor): void {
        $performance->audit('club.competition.performanceDeleted', ['value' => $performance->value, 'actor_id' => $actor->id]);
        $performance->delete();
    }

    /**
     * Bestleistungen je Disziplin — nur bestätigte Werte, Richtung aus dem Schnappschuss.
     *
     * @return Collection<int, ClubPerformance>
     */
    public function bests(ClubMember $member, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection {
        $query = ClubPerformance::query()->where('club_member_id', $member->id)->whereNotNull('confirmed_at')->with('profile:id,name');
        if ($from !== null) {
            $query->where('performed_on', '>=', DateRange::day($from));
        }
        if ($to !== null) {
            $query->where('performed_on', '<=', DateRange::day($to));
        }
        $best = [];
        foreach ($query->orderBy('performed_on')->get() as $performance) {
            $key = $performance->club_sport_profile_id . ':' . $performance->discipline_code;
            if (! isset($best[$key]) || $performance->beats($best[$key])) {
                $best[$key] = $performance;
            }
        }

        return collect(array_values($best));
    }

    // ── Nachweisliste ────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $data */
    public function createRequirement(Organization $organization, array $data): ClubAttendanceRequirement {
        $requirement = ClubAttendanceRequirement::query()->create(['organization_id' => $organization->id] + $this->requirementAttributes($organization->id, $data));
        $requirement->audit('club.competition.requirementCreated', ['required' => $requirement->required_count, 'months' => $requirement->period_months]);

        return $requirement;
    }

    /** @param  array<string, mixed>  $data */
    public function updateRequirement(ClubAttendanceRequirement $requirement, array $data): ClubAttendanceRequirement {
        $requirement->update($this->requirementAttributes($requirement->organization_id, $data));
        $requirement->audit('club.competition.requirementUpdated');

        return $requirement->refresh();
    }

    public function deleteRequirement(ClubAttendanceRequirement $requirement): void {
        $requirement->audit('club.competition.requirementDeleted');
        $requirement->delete();
    }

    /**
     * Nachweisliste: je Mitglied (Gruppe/Abteilung der Anforderung) die Zahl
     * bestätigter, anrechenbarer Anwesenheiten im Zeitraum vor dem Stichtag
     * gegen die konfigurierte Anzahl.
     *
     * @return Collection<int, array{member: ClubMember, count: int, required: int, met: bool, last_on: CarbonImmutable|null}>
     */
    public function complianceReport(ClubAttendanceRequirement $requirement, CarbonInterface $asOf): Collection {
        $asOf = CarbonImmutable::instance($asOf)->startOfDay();
        $from = $asOf->subMonths($requirement->period_months)->addDay();
        $members = ClubMember::query()->where('organization_id', $requirement->organization_id)->current($asOf);
        if ($requirement->club_group_id !== null) {
            $groupId = $requirement->club_group_id;
            $members->whereHas('groupMemberships', fn($q) => $q->where('club_group_id', $groupId)->where('status', ClubGroupMembershipStatus::Active->value));
        } elseif ($requirement->club_department_id !== null) {
            $departmentId = $requirement->club_department_id;
            $members->whereHas('groupMemberships', fn($q) => $q->where('status', ClubGroupMembershipStatus::Active->value)->whereHas('group', fn($g) => $g->where('club_department_id', $departmentId)));
        }
        $rows = collect();
        foreach ($members->orderBy('last_name')->orderBy('first_name')->get() as $member) {
            $records = ClubAttendanceRecord::query()
                ->join('club_attendance_sheets', 'club_attendance_sheets.id', '=', 'club_attendance_records.club_attendance_sheet_id')
                ->join('events', 'events.id', '=', 'club_attendance_records.event_id')
                ->leftJoin('club_event_details', 'club_event_details.event_id', '=', 'events.id')
                ->where('club_attendance_records.club_member_id', $member->id)
                ->where('club_attendance_sheets.status', ClubAttendanceSheetStatus::Confirmed->value)
                ->whereIn('club_attendance_records.status', [ClubAttendanceStatus::Present->value, ClubAttendanceStatus::Partial->value])
                ->where(fn($q) => $q->whereNull('club_attendance_records.overlap_event_id')->orWhereNotNull('club_attendance_records.overlap_cleared_at'))
                ->where('events.started_at', '>=', DateRange::dayStart($from))
                ->where('events.started_at', '<', DateRange::dayAfter($asOf))
                ->when($requirement->event_kind !== null, fn($q) => $q->where('club_event_details.kind', $requirement->event_kind?->value))
                ->when($requirement->club_group_id !== null, fn($q) => $q->whereExists(fn($sub) => $sub->selectRaw('1')->from('club_event_groups')->whereColumn('club_event_groups.event_id', 'events.id')->where('club_event_groups.club_group_id', $requirement->club_group_id)))
                ->orderByDesc('events.started_at')
                ->get(['events.started_at as started_at']);
            $count = $records->count();
            $last = $records->first();
            $rows->push([
                'member' => $member,
                'count' => $count,
                'required' => $requirement->required_count,
                'met' => $count >= $requirement->required_count,
                'last_on' => $last !== null ? CarbonImmutable::parse((string) $last->getAttribute('started_at'))->setTimezone(Tz::current()) : null,
            ]);
        }

        return $rows;
    }

    /** Pflichtrolle Standaufsicht (Schießsport): Termin eines Schießsport-Profils ohne Standaufsicht. */
    public function missingRangeOfficer(Event $event): bool {
        $families = $event->clubGroups()->with(['sportProfile:id,family', 'department.sportProfile:id,family'])->get()
            ->map(function ($group): ?ClubSportFamily {
                $profile = $group->sportProfile ?? $group->department?->sportProfile;

                return $profile !== null ? $profile->family : null;
            })
            ->filter();
        $competition = ClubCompetitionDetails::query()->where('event_id', $event->id)->with('profile:id,family')->first();
        if ($competition?->profile !== null) {
            $families->push($competition->profile->family);
        }
        if (! $families->contains(ClubSportFamily::Shooting)) {
            return false;
        }

        return ! ClubEventRole::query()->where('event_id', $event->id)->where('role', ClubEventRoleKind::RangeOfficer->value)->exists();
    }

    // ── Helfer ───────────────────────────────────────────────────────────

    /** Nach neuem Startrecht offene Klärungen des Mitglieds auflösen. */
    private function reviewEntriesFor(ClubMember $member): void {
        $entries = ClubCompetitionEntry::query()->where('club_member_id', $member->id)->where('status', ClubEntryStatus::NeedsReview->value)->with('event')->get();
        foreach ($entries as $entry) {
            $event = $entry->event;
            if ($event === null) {
                continue;
            }
            $details = ClubCompetitionDetails::query()->where('event_id', $event->id)->with('profile')->first();
            if ($details?->profile !== null && $this->hasStartRight($member, $details->profile, $this->localDay($event))) {
                $entry->update(['status' => ClubEntryStatus::Registered->value, 'review_reason' => null]);
                $entry->audit('club.competition.entryCleared', ['note' => 'start_right']);
            }
        }
    }

    private function profileOf(Organization $organization, mixed $id): ClubSportProfile {
        $profileId = $this->nullableInt($id);
        $profile = $profileId !== null ? ClubSportProfile::query()->whereKey($profileId)->where('organization_id', $organization->id)->first() : null;
        if ($profile === null) {
            throw ValidationException::withMessages(['club_sport_profile_id' => __('club.teams.error.profile_foreign')]);
        }

        return $profile;
    }

    /** @return array{code: string, label: string, unit: string|null, lower_is_better: bool} */
    private function disciplineOf(ClubSportProfile $profile, string $code): array {
        foreach ($profile->disciplines ?? [] as $discipline) {
            if ((string) $discipline['code'] === $code) {
                return ['code' => $code, 'label' => (string) $discipline['label'], 'unit' => isset($discipline['unit']) ? (string) $discipline['unit'] : null, 'lower_is_better' => (bool) ($discipline['lower_is_better'] ?? false)];
            }
        }
        throw ValidationException::withMessages(['discipline_code' => __('club.competitions.error.discipline_unknown', ['code' => $code])]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(ClubSportProfile $profile, array $data): array {
        $codes = array_values(array_unique(array_filter(array_map(static fn($c): string => trim((string) $c), (array) ($data['disciplines'] ?? [])))));
        $known = array_map(static fn(array $d): string => (string) $d['code'], $profile->disciplines ?? []);
        foreach ($codes as $code) {
            if (! in_array($code, $known, true)) {
                throw ValidationException::withMessages(['disciplines' => __('club.competitions.error.discipline_unknown', ['code' => $code])]);
            }
        }
        if ($codes === []) {
            throw ValidationException::withMessages(['disciplines' => __('club.competitions.error.discipline_required')]);
        }
        $fee = $this->nullableString($data['entry_fee'] ?? null);

        return [
            'disciplines' => $codes,
            'venue' => $this->nullableString($data['venue'] ?? null),
            'organizer' => $this->nullableString($data['organizer'] ?? null),
            'entry_fee' => $fee === null ? null : NumberHelper::normalizeDecimalStringOrNull($fee),
            'requires_start_right' => (bool) ($data['requires_start_right'] ?? true),
            'notes' => $this->nullableString($data['notes'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function requirementAttributes(int $organizationId, array $data): array {
        $groupId = $this->nullableInt($data['club_group_id'] ?? null);
        $departmentId = $this->nullableInt($data['club_department_id'] ?? null);
        $kind = $this->nullableString($data['event_kind'] ?? null);

        return [
            'name' => mb_substr(trim((string) $data['name']), 0, 120),
            'club_group_id' => $groupId,
            'club_department_id' => $groupId === null ? $departmentId : null,
            'required_count' => max(1, (int) ($data['required_count'] ?? 1)),
            'period_months' => max(1, (int) ($data['period_months'] ?? 12)),
            'event_kind' => $kind !== null ? ClubEventKind::from($kind)->value : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $this->nullableString($data['notes'] ?? null),
        ];
    }

    private function localDay(Event $event): CarbonImmutable {
        $tz = Tz::isValid($event->timezone) && $event->timezone !== 'UTC' ? (string) $event->timezone : Tz::current();

        return CarbonImmutable::instance($event->started_at)->setTimezone($tz)->startOfDay();
    }

    private function date(mixed $value): ?CarbonImmutable {
        $string = $this->nullableString($value);

        return $string === null ? null : CarbonImmutable::parse($string)->startOfDay();
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
