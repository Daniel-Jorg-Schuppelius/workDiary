<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubTeamService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubMembershipKind, ClubResultFormat, ClubSportFamily};
use App\Models\Club\{ClubGroup, ClubMember, ClubSeason, ClubSportProfile, ClubSquad, ClubSquadMember};
use App\Models\Platform\{Organization, User};
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sportartenprofile, Saisons und Saisonkader (Feature 159, MVP-852) —
 * einzige Schreibstelle. Eine Sportart ist Konfiguration (Profil), keine
 * Code-Sonderfälle; Gastspieler sind Mitglieder der Art „Gast“ mit
 * Herkunftsverein im Kader, ohne Beitrag oder Login.
 */
class ClubTeamService {
    public function __construct(
        private readonly ClubMemberService $members,
    ) {}

    // ── Sportartenprofile ────────────────────────────────────────────────

    /** @param  array<string, mixed>  $data */
    public function createProfile(Organization $organization, array $data): ClubSportProfile {
        $profile = ClubSportProfile::query()->create(['organization_id' => $organization->id] + $this->profileAttributes($data));
        $profile->audit('club.team.profileCreated', ['family' => $profile->family->value]);

        return $profile;
    }

    /** @param  array<string, mixed>  $data */
    public function updateProfile(ClubSportProfile $profile, array $data): ClubSportProfile {
        $profile->update($this->profileAttributes($data));
        $profile->audit('club.team.profileUpdated');

        return $profile->refresh();
    }

    public function deleteProfile(ClubSportProfile $profile): void {
        if ($profile->groups()->exists() || $profile->departments()->exists()) {
            throw ValidationException::withMessages(['profile' => __('club.teams.error.profile_in_use')]);
        }
        $profile->audit('club.team.profileDeleted');
        $profile->delete();
    }

    /** Profil einer Gruppe: eigenes, sonst das der Abteilung. */
    public function profileFor(ClubGroup $group): ?ClubSportProfile {
        if ($group->club_sport_profile_id !== null) {
            return $group->sportProfile;
        }

        return $group->department?->sportProfile;
    }

    /**
     * Positionsliste aus Textzeilen („code=Bezeichnung“, „code|Bezeichnung“ oder
     * nur „Bezeichnung“) oder aus einer bereits strukturierten Liste.
     *
     * @return list<array{code: string, label: string}>
     */
    public function parsePositions(mixed $input): array {
        $lines = is_array($input) ? $input : (preg_split('/\R/u', (string) $input) ?: []);
        $positions = [];
        $seen = [];
        foreach ($lines as $line) {
            if (is_array($line)) {
                $code = trim((string) ($line['code'] ?? ''));
                $label = trim((string) ($line['label'] ?? ''));
            } else {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }
                $parts = preg_split('/\s*[=|]\s*/u', $line, 2) ?: [$line];
                $code = trim((string) $parts[0]);
                $label = trim((string) ($parts[1] ?? $parts[0]));
            }
            if ($label === '') {
                continue;
            }
            $code = Str::slug($code !== '' ? $code : $label, '_');
            $code = mb_substr($code !== '' ? $code : 'p' . (count($positions) + 1), 0, 30);
            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $positions[] = ['code' => $code, 'label' => mb_substr($label, 0, 60)];
        }

        return $positions;
    }

    // ── Saisons ──────────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $data */
    public function createSeason(Organization $organization, array $data): ClubSeason {
        $starts = CarbonImmutable::parse((string) $data['starts_on']);
        $ends = CarbonImmutable::parse((string) $data['ends_on']);
        if ($ends->lt($starts)) {
            throw ValidationException::withMessages(['ends_on' => __('club.teams.error.season_range')]);
        }

        return ClubSeason::query()->create([
            'organization_id' => $organization->id,
            'name' => trim((string) $data['name']),
            'starts_on' => $starts->toDateString(),
            'ends_on' => $ends->toDateString(),
        ]);
    }

    public function seasonContaining(int $organizationId, CarbonInterface $date): ?ClubSeason {
        /** @var ClubSeason|null $season */
        $season = ClubSeason::query()->where('organization_id', $organizationId)->containing($date)->orderByDesc('starts_on')->first();

        return $season;
    }

    // ── Saisonkader ──────────────────────────────────────────────────────

    /** Kader einer Mannschaft je Saison; wird bei Bedarf angelegt, Vorsaisons bleiben bestehen. */
    public function squadFor(ClubGroup $group, ClubSeason $season, bool $create = true): ?ClubSquad {
        /** @var ClubSquad|null $squad */
        $squad = ClubSquad::query()->where('club_group_id', $group->id)->where('club_season_id', $season->id)->first();
        if ($squad !== null || ! $create) {
            return $squad;
        }
        if ($season->organization_id !== $group->organization_id) {
            throw ValidationException::withMessages(['club_season_id' => __('club.teams.error.season_foreign')]);
        }

        return ClubSquad::query()->create([
            'organization_id' => $group->organization_id,
            'club_group_id' => $group->id,
            'club_season_id' => $season->id,
        ]);
    }

    /**
     * Kaderzuordnung: vorhandenes Mitglied oder neuer Gastspieler (Vorname,
     * Nachname, Geburtsdatum, Herkunftsverein) — der Gast wird als Mitglied
     * der Art „Gast“ geführt, ohne Beitragszuordnung oder Login.
     *
     * @param  array<string, mixed>  $data  club_member_id | guest_first_name, guest_last_name, guest_birth_date;
     *                                      guest_origin, valid_from, valid_to, jersey_no, position_code, strength_rank, notes
     */
    public function addSquadMember(ClubSquad $squad, array $data, User $actor): ClubSquadMember {
        return DB::transaction(function () use ($squad, $data, $actor): ClubSquadMember {
            $season = $squad->season()->firstOrFail();
            $from = $this->date($data['valid_from'] ?? null) ?? CarbonImmutable::instance($season->starts_on);
            $to = $this->date($data['valid_to'] ?? null);
            if ($to !== null && $to->lt($from)) {
                throw ValidationException::withMessages(['valid_to' => __('club.teams.error.validity_range')]);
            }
            $origin = $this->nullableString($data['guest_origin'] ?? null);

            $memberId = $data['club_member_id'] ?? null;
            if ($memberId !== null && $memberId !== '') {
                /** @var ClubMember $member */
                $member = ClubMember::query()->whereKey((int) $memberId)->firstOrFail();
            } else {
                if ($origin === null) {
                    throw ValidationException::withMessages(['guest_origin' => __('club.teams.error.guest_origin_required')]);
                }
                $member = $this->members->create($squad->organization()->firstOrFail(), $actor, [
                    'first_name' => (string) ($data['guest_first_name'] ?? ''),
                    'last_name' => (string) ($data['guest_last_name'] ?? ''),
                    'birth_date' => $data['guest_birth_date'] ?? null,
                    'kind' => ClubMembershipKind::Guest->value,
                    'joined_on' => $from->toDateString(),
                    'notes' => (string) __('club.teams.label.guest_note', ['origin' => $origin]),
                ]);
            }
            if ($member->organization_id !== $squad->organization_id) {
                throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
            }

            $overlapQuery = ClubSquadMember::query()
                ->where('club_squad_id', $squad->id)
                ->where('club_member_id', $member->id)
                ->where(fn(Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($from)));
            if ($to !== null) {
                $overlapQuery->where('valid_from', '<=', DateRange::day($to));
            }
            $overlap = $overlapQuery->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['club_member_id' => __('club.teams.error.already_in_squad')]);
            }

            $entry = ClubSquadMember::query()->create([
                'organization_id' => $squad->organization_id,
                'club_squad_id' => $squad->id,
                'club_member_id' => $member->id,
                'valid_from' => $from->toDateString(),
                'valid_to' => $to?->toDateString(),
                'guest_origin' => $origin,
            ] + $this->squadMemberAttributes($data));
            $entry->audit('club.team.squadMemberAdded', ['member_id' => $member->id, 'guest' => $origin !== null, 'season_id' => $season->id]);

            return $entry;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function updateSquadMember(ClubSquadMember $entry, array $data): ClubSquadMember {
        $attributes = $this->squadMemberAttributes($data);
        if (array_key_exists('guest_origin', $data)) {
            $attributes['guest_origin'] = $this->nullableString($data['guest_origin']);
        }
        $to = array_key_exists('valid_to', $data) ? $this->date($data['valid_to']) : $entry->valid_to;
        if ($to !== null && $to->lt($entry->valid_from)) {
            throw ValidationException::withMessages(['valid_to' => __('club.teams.error.validity_range')]);
        }
        $attributes['valid_to'] = $to?->toDateString();
        $entry->update($attributes);
        $entry->audit('club.team.squadMemberUpdated');

        return $entry->refresh();
    }

    public function endSquadMember(ClubSquadMember $entry, CarbonInterface $on, User $actor): ClubSquadMember {
        $day = CarbonImmutable::instance($on)->startOfDay();
        if ($day->lt($entry->valid_from)) {
            throw ValidationException::withMessages(['valid_to' => __('club.teams.error.validity_range')]);
        }
        $entry->update(['valid_to' => $day->toDateString()]);
        $entry->audit('club.team.squadMemberEnded', ['valid_to' => $day->toDateString(), 'actor_id' => $actor->id]);

        return $entry->refresh();
    }

    /**
     * Personen, die am Tag für die Mannschaft in Frage kommen: Kaderzuordnungen
     * der Saison (inkl. Gastspieler) plus aktive Gruppenmitglieder.
     *
     * @return Collection<int, array{member: ClubMember, squad: ClubSquadMember|null}>
     */
    public function rosterOn(ClubGroup $group, CarbonInterface $day, ?ClubSeason $season = null): Collection {
        $day = CarbonImmutable::instance($day)->startOfDay();
        $rows = collect();
        $squad = $season !== null ? $this->squadFor($group, $season, false) : null;
        if ($squad !== null) {
            foreach ($squad->membersOn($day)->with('member')->get() as $entry) {
                if ($entry->member !== null && ! $entry->member->hasLeftOn($day)) {
                    $rows->put($entry->club_member_id, ['member' => $entry->member, 'squad' => $entry]);
                }
            }
        }
        $memberships = $group->activeMemberships()
            ->with('member')
            ->where('valid_from', '<=', DateRange::day($day))
            ->where(fn(Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($day)))
            ->get();
        foreach ($memberships as $membership) {
            if ($membership->member !== null && ! $rows->has($membership->club_member_id) && ! $membership->member->hasLeftOn($day)) {
                $rows->put($membership->club_member_id, ['member' => $membership->member, 'squad' => null]);
            }
        }

        return $rows->sortBy(function (array $row): string {
            $squad = $row['squad'];
            $rank = $squad instanceof ClubSquadMember && $squad->strength_rank !== null ? $squad->strength_rank : 999;
            $jersey = $squad instanceof ClubSquadMember && $squad->jersey_no !== null ? $squad->jersey_no : 999;

            return sprintf('%03d-%03d-%s', $rank, $jersey, $row['member']->last_name . ' ' . $row['member']->first_name);
        })->values();
    }

    // ── Altersklasse per Stichtag ────────────────────────────────────────

    /**
     * Stichtag der Altersklasse: bei Mannschaften mit Profil-Stichtag (MM-TT)
     * der Stichtag innerhalb der Saison, die den Tag enthält — sonst der Tag selbst.
     */
    public function ageReferenceDate(ClubGroup $group, CarbonInterface $on): CarbonImmutable {
        $on = CarbonImmutable::instance($on)->startOfDay();
        $profile = $group->is_team ? $this->profileFor($group) : null;
        $cutoff = $profile?->age_cutoff;
        if ($cutoff === null || preg_match('/^(\d{2})-(\d{2})$/', $cutoff, $m) !== 1) {
            return $on;
        }
        $season = $this->seasonContaining($group->organization_id, $on);
        if ($season === null) {
            return $on;
        }
        foreach ([$season->starts_on->year, $season->starts_on->year + 1] as $year) {
            $candidate = CarbonImmutable::createFromDate($year, (int) $m[1], (int) $m[2])->startOfDay();
            if ($season->contains($candidate)) {
                return $candidate;
            }
        }

        return $on;
    }

    public function ageClassOf(ClubMember $member, ClubGroup $group, CarbonInterface $on): ?int {
        return $member->ageOn($this->ageReferenceDate($group, $on));
    }

    // ── Helfer ───────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profileAttributes(array $data): array {
        $family = ClubSportFamily::from((string) ($data['family'] ?? ClubSportFamily::Other->value));
        $format = ClubResultFormat::from((string) ($data['result_format'] ?? ClubResultFormat::None->value));
        $cutoff = $this->nullableString($data['age_cutoff'] ?? null);
        if ($cutoff !== null && preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $cutoff) !== 1) {
            throw ValidationException::withMessages(['age_cutoff' => __('club.teams.error.age_cutoff_format')]);
        }

        return [
            'name' => trim((string) $data['name']),
            'family' => $family->value,
            'positions' => $this->parsePositions($data['positions'] ?? ''),
            'squad_size_field' => $this->nullableInt($data['squad_size_field'] ?? null),
            'squad_size_bench' => $this->nullableInt($data['squad_size_bench'] ?? null),
            'result_format' => $format->value,
            'has_doubles' => (bool) ($data['has_doubles'] ?? false),
            'age_cutoff' => $cutoff,
            'disciplines' => $this->parseDisciplines($data['disciplines'] ?? ''),
            'resource_types' => $this->parseList($data['resource_types'] ?? ''),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * Disziplinen als Zeilen „code=Bezeichnung;Einheit;kleiner_besser“ (MVP-855 nutzt sie).
     *
     * @return list<array{code: string, label: string, unit: string|null, lower_is_better: bool}>
     */
    private function parseDisciplines(mixed $input): array {
        $rows = [];
        foreach ($this->parsePositions($input) as $position) {
            $parts = array_map('trim', explode(';', $position['label']));
            $rows[] = [
                'code' => $position['code'],
                'label' => (string) $parts[0],
                'unit' => isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null,
                'lower_is_better' => in_array(mb_strtolower((string) ($parts[2] ?? '')), ['1', 'ja', 'yes', 'true', 'lower', 'kleiner'], true),
            ];
        }

        return $rows;
    }

    /** @return list<string> */
    private function parseList(mixed $input): array {
        $lines = is_array($input) ? $input : (preg_split('/\R|,/u', (string) $input) ?: []);
        $values = [];
        foreach ($lines as $line) {
            $value = trim((string) $line);
            if ($value !== '' && ! in_array($value, $values, true)) {
                $values[] = mb_substr($value, 0, 60);
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function squadMemberAttributes(array $data): array {
        $attributes = [];
        foreach (['jersey_no' => 'int', 'strength_rank' => 'int', 'position_code' => 'string', 'notes' => 'string'] as $key => $type) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $type === 'int' ? $this->nullableInt($data[$key]) : $this->nullableString($data[$key]);
            }
        }

        return $attributes;
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
