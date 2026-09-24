<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubGroupMembershipStatus, ClubMembershipKind};
use App\Models\Club\{ClubGuardian, ClubMember, ClubMembershipPeriod};
use App\Models\Platform\{Organization, User};
use App\Services\Concerns\AssignsSequentialNo;
use App\Services\Stammdaten\ContactDetailsWriter;
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Einzige Schreibstelle für Mitglieder, Mitgliedschaftsverlauf und
 * Vertretungen (Feature 159, MVP-842). Mitgliedsnummern laufen je
 * Organisation; Art-Wechsel und Pausen schreiben den Verlauf fort, ein
 * Austritt beendet Gruppenzuordnungen, löscht aber keinen Nachweis.
 */
class ClubMemberService {
    use AssignsSequentialNo;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Organization $organization, ?User $actor, array $attributes): ClubMember {
        return DB::transaction(function () use ($organization, $actor, $attributes): ClubMember {
            $memberNo = $this->nullableString($attributes['member_no'] ?? null) !== null
                ? (int) $attributes['member_no']
                : $this->nextNo(ClubMember::class, 'member_no', 'organization_id', (int) $organization->id);
            $this->assertMemberNoFree((int) $organization->id, $memberNo, null);

            $kind = $this->kind($attributes['kind'] ?? null) ?? ClubMembershipKind::Active;
            $joinedOn = $this->date($attributes['joined_on'] ?? null) ?? CarbonImmutable::today();

            $member = ClubMember::query()->create([
                'organization_id' => $organization->id,
                'member_no' => $memberNo,
                'first_name' => trim((string) $attributes['first_name']),
                'last_name' => trim((string) $attributes['last_name']),
                'email' => $this->email($attributes['email'] ?? null),
                'phone' => $this->nullableString($attributes['phone'] ?? null),
                'birth_date' => $this->date($attributes['birth_date'] ?? null)?->toDateString(),
                'kind' => $kind->value,
                'joined_on' => $joinedOn->toDateString(),
                'user_id' => $this->resolveUserId((int) $organization->id, $attributes['user_id'] ?? null, null),
                'notes' => $this->nullableString($attributes['notes'] ?? null),
                'created_by_user_id' => $actor?->id,
            ]);
            $this->writeAddress($member, $attributes);

            ClubMembershipPeriod::query()->create([
                'organization_id' => $organization->id,
                'club_member_id' => $member->id,
                'kind' => $kind->value,
                'starts_on' => $joinedOn->toDateString(),
                'ends_on' => null,
            ]);

            return $member;
        });
    }

    /**
     * Stammdaten (nicht Art, Eintritt, Austritt — die laufen über den Verlauf).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(ClubMember $member, array $attributes): ClubMember {
        return DB::transaction(function () use ($member, $attributes): ClubMember {
            $organizationId = $member->organization_id;
            $data = [];

            if ($this->nullableString($attributes['member_no'] ?? null) !== null && (int) $attributes['member_no'] !== $member->member_no) {
                $this->assertMemberNoFree($organizationId, (int) $attributes['member_no'], $member);
                $data['member_no'] = (int) $attributes['member_no'];
            }

            foreach (['first_name', 'last_name'] as $required) {
                if (array_key_exists($required, $attributes)) {
                    $data[$required] = trim((string) $attributes[$required]);
                }
            }
            foreach (['phone', 'notes'] as $optional) {
                if (array_key_exists($optional, $attributes)) {
                    $data[$optional] = $this->nullableString($attributes[$optional]);
                }
            }
            if (array_key_exists('email', $attributes)) {
                $data['email'] = $this->email($attributes['email']);
            }
            if (array_key_exists('birth_date', $attributes)) {
                $data['birth_date'] = $this->date($attributes['birth_date'])?->toDateString();
            }
            if (array_key_exists('user_id', $attributes)) {
                $data['user_id'] = $this->resolveUserId($organizationId, $attributes['user_id'], $member);
            }

            $member->fill($data)->save();
            $this->writeAddress($member, $attributes);

            return $member->refresh();
        });
    }

    /**
     * Art-Wechsel bzw. Pause zum Stichtag: schließt den offenen Abschnitt am
     * Vortag und eröffnet den neuen. Der Stand am Mitglied folgt dem heute
     * gültigen Abschnitt (künftige Wechsel zieht der tägliche Scan nach).
     */
    public function changeKind(ClubMember $member, ClubMembershipKind $kind, CarbonInterface $effectiveOn, User $actor, ?string $note = null): ClubMember {
        return DB::transaction(function () use ($member, $kind, $effectiveOn, $actor, $note): ClubMember {
            $day = CarbonImmutable::instance($effectiveOn)->startOfDay();
            $this->assertNotLeft($member, $day);
            if ($day->lessThan($member->joined_on)) {
                throw ValidationException::withMessages(['effective_on' => __('club.error.before_joined')]);
            }

            /** @var ClubMembershipPeriod|null $open */
            // periods() sortiert aufsteigend vor — reorder(), sonst gewinnt die erste ORDER BY.
            $open = $member->periods()->whereNull('ends_on')->reorder()->orderByDesc('starts_on')->lockForUpdate()->first();
            if ($open !== null) {
                if ($open->kind === $kind && $open->starts_on->lessThanOrEqualTo($day)) {
                    return $member;
                }
                if ($open->starts_on->greaterThanOrEqualTo($day)) {
                    // Abschnitt beginnt am oder nach dem Stichtag: Korrektur statt Nullintervall.
                    $open->delete();
                } else {
                    $open->update(['ends_on' => $day->subDay()->toDateString()]);
                }
            }

            ClubMembershipPeriod::query()->create([
                'organization_id' => $member->organization_id,
                'club_member_id' => $member->id,
                'kind' => $kind->value,
                'starts_on' => $day->toDateString(),
                'ends_on' => null,
                'note' => $this->nullableString($note),
            ]);

            $member->update(['kind' => $this->kindOn($member, CarbonImmutable::today())->value]);
            $member->audit('club.member.kindChanged', [
                'kind' => $kind->value,
                'effective_on' => $day->toDateString(),
                'note' => $this->nullableString($note),
                'actor_id' => $actor->id,
            ]);

            return $member->refresh();
        });
    }

    /**
     * Austritt: Austrittstag ist der letzte Mitgliedstag. Offener Abschnitt
     * und aktive Gruppenzuordnungen enden an diesem Tag, Anträge werden
     * abgelehnt — frühere Nachweise bleiben unangetastet.
     */
    public function leave(ClubMember $member, CarbonInterface $on, User $actor, ?string $note = null): ClubMember {
        return DB::transaction(function () use ($member, $on, $actor, $note): ClubMember {
            $day = CarbonImmutable::instance($on)->startOfDay();
            if ($member->left_on !== null) {
                throw ValidationException::withMessages(['left_on' => __('club.error.already_left')]);
            }
            if ($day->lessThan($member->joined_on)) {
                throw ValidationException::withMessages(['left_on' => __('club.error.before_joined')]);
            }

            $member->update(['left_on' => $day->toDateString()]);

            $member->periods()->where('starts_on', '>=', DateRange::dayAfter($day))->delete();
            $member->periods()->whereNull('ends_on')->update(['ends_on' => $day->toDateString()]);

            $endNote = $this->nullableString($note) ?? (string) __('club.note.member_left');
            $member->groupMemberships()
                ->where('status', ClubGroupMembershipStatus::Active->value)
                ->where(function ($query) use ($day): void {
                    $query->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::dayAfter($day));
                })
                ->get()
                ->each(function ($membership) use ($day, $actor, $endNote): void {
                    $validTo = $membership->valid_from->greaterThan($day) ? $membership->valid_from : $day;
                    $membership->update([
                        'status' => ClubGroupMembershipStatus::Ended->value,
                        'valid_to' => $validTo->toDateString(),
                        'note' => $endNote,
                        'decided_by_user_id' => $actor->id,
                        'decided_at' => now(),
                    ]);
                });
            $member->groupMemberships()
                ->where('status', ClubGroupMembershipStatus::Requested->value)
                ->update([
                    'status' => ClubGroupMembershipStatus::Rejected->value,
                    'note' => $endNote,
                    'decided_by_user_id' => $actor->id,
                    'decided_at' => now(),
                ]);

            $member->audit('club.member.left', [
                'left_on' => $day->toDateString(),
                'note' => $this->nullableString($note),
                'actor_id' => $actor->id,
            ]);

            return $member->refresh();
        });
    }

    /** Stand am Mitglied für Abschnitte nachziehen, die am Stichtag beginnen (täglicher Scan). */
    public function syncCurrentKinds(Organization $organization, CarbonInterface $today): int {
        $day = CarbonImmutable::instance($today)->startOfDay();
        $updated = 0;

        ClubMember::query()
            ->where('organization_id', $organization->id)
            ->whereNull('left_on')
            ->whereHas('periods', fn($query) => $query->whereBetween('starts_on', DateRange::days($day, $day)))
            ->get()
            ->each(function (ClubMember $member) use ($day, &$updated): void {
                $kind = $this->kindOn($member, $day);
                if ($member->kind !== $kind) {
                    $member->update(['kind' => $kind->value]);
                    $updated++;
                }
            });

        return $updated;
    }

    public function kindOn(ClubMember $member, CarbonInterface $date): ClubMembershipKind {
        $day = CarbonImmutable::instance($date)->startOfDay();

        /** @var ClubMembershipPeriod|null $period */
        $period = $member->periods()
            ->where('starts_on', '<', DateRange::dayAfter($day))
            ->where(function ($query) use ($day): void {
                $query->whereNull('ends_on')->orWhere('ends_on', '>=', DateRange::day($day));
            })
            ->reorder()
            ->orderByDesc('starts_on')
            ->first();

        return $period !== null ? $period->kind : $member->kind;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addGuardian(ClubMember $member, array $attributes, User $actor): ClubGuardian {
        $guardian = ClubGuardian::query()->create([
            'organization_id' => $member->organization_id,
            'club_member_id' => $member->id,
            'name' => trim((string) $attributes['name']),
            'email' => $this->email($attributes['email'] ?? null),
            'phone' => $this->nullableString($attributes['phone'] ?? null),
            'user_id' => $this->resolveGuardianUserId($member->organization_id, $attributes['user_id'] ?? null),
            'permissions' => $this->permissions($attributes['permissions'] ?? []),
            'valid_from' => $this->date($attributes['valid_from'] ?? null)?->toDateString(),
            'valid_to' => $this->date($attributes['valid_to'] ?? null)?->toDateString(),
            'note' => $this->nullableString($attributes['note'] ?? null),
        ]);
        $this->writeAddress($guardian, $attributes);
        $guardian->audit('club.guardian.granted', ['permissions' => $guardian->permissions, 'actor_id' => $actor->id]);

        return $guardian;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateGuardian(ClubGuardian $guardian, array $attributes): ClubGuardian {
        if ($guardian->isRevoked()) {
            throw ValidationException::withMessages(['name' => __('club.error.guardian_revoked')]);
        }

        $guardian->update([
            'name' => trim((string) ($attributes['name'] ?? $guardian->name)),
            'email' => array_key_exists('email', $attributes) ? $this->email($attributes['email']) : $guardian->email,
            'phone' => array_key_exists('phone', $attributes) ? $this->nullableString($attributes['phone']) : $guardian->phone,
            'user_id' => array_key_exists('user_id', $attributes) ? $this->resolveGuardianUserId($guardian->organization_id, $attributes['user_id']) : $guardian->user_id,
            'permissions' => array_key_exists('permissions', $attributes) ? $this->permissions($attributes['permissions']) : $guardian->permissions,
            'valid_from' => array_key_exists('valid_from', $attributes) ? $this->date($attributes['valid_from'])?->toDateString() : $guardian->valid_from,
            'valid_to' => array_key_exists('valid_to', $attributes) ? $this->date($attributes['valid_to'])?->toDateString() : $guardian->valid_to,
            'note' => array_key_exists('note', $attributes) ? $this->nullableString($attributes['note']) : $guardian->note,
        ]);
        $this->writeAddress($guardian, $attributes);

        return $guardian->refresh();
    }

    public function revokeGuardian(ClubGuardian $guardian, User $actor, ?string $note = null): ClubGuardian {
        if ($guardian->isRevoked()) {
            return $guardian;
        }

        $guardian->update([
            'revoked_at' => now(),
            'revoked_by_user_id' => $actor->id,
            'note' => $this->nullableString($note) ?? $guardian->note,
        ]);
        $guardian->audit('club.guardian.revoked', ['actor_id' => $actor->id, 'note' => $this->nullableString($note)]);

        return $guardian->refresh();
    }

    private function assertMemberNoFree(int $organizationId, int $memberNo, ?ClubMember $ignore): void {
        if ($memberNo < 1) {
            throw ValidationException::withMessages(['member_no' => __('club.error.member_no_invalid')]);
        }

        $ignoreId = $ignore?->id;
        $taken = ClubMember::withTrashed()
            ->where('organization_id', $organizationId)
            ->where('member_no', $memberNo)
            ->when($ignoreId !== null, fn($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['member_no' => __('club.error.member_no_taken')]);
        }
    }

    private function assertNotLeft(ClubMember $member, CarbonInterface $day): void {
        if ($member->left_on !== null) {
            throw ValidationException::withMessages(['effective_on' => __('club.error.already_left')]);
        }
        unset($day);
    }

    /** Verknüpftes Konto: gleiche Organisation und noch nicht an ein anderes Mitglied gebunden. */
    private function resolveUserId(int $organizationId, mixed $value, ?ClubMember $ignore): ?int {
        if ($this->nullableString($value) === null) {
            return null;
        }

        $user = User::query()->whereKey((int) $value)->where('organization_id', $organizationId)->first();
        if ($user === null) {
            throw ValidationException::withMessages(['user_id' => __('club.error.user_foreign')]);
        }

        $ignoreId = $ignore?->id;
        $linkedElsewhere = ClubMember::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->when($ignoreId !== null, fn($query) => $query->whereKeyNot($ignoreId))
            ->exists();
        if ($linkedElsewhere) {
            throw ValidationException::withMessages(['user_id' => __('club.error.user_already_linked')]);
        }

        return $user->id;
    }

    private function resolveGuardianUserId(int $organizationId, mixed $value): ?int {
        if ($this->nullableString($value) === null) {
            return null;
        }

        $user = User::query()->whereKey((int) $value)->where('organization_id', $organizationId)->first();
        if ($user === null) {
            throw ValidationException::withMessages(['user_id' => __('club.error.user_foreign')]);
        }

        return $user->id;
    }

    /**
     * @return list<string>
     */
    private function permissions(mixed $values): array {
        $out = [];
        foreach ((array) $values as $value) {
            $enum = \App\Enums\Club\ClubGuardianPermission::tryFrom((string) $value);
            if ($enum !== null && ! in_array($enum->value, $out, true)) {
                $out[] = $enum->value;
            }
        }

        return $out;
    }

    private function kind(mixed $value): ?ClubMembershipKind {
        if ($value instanceof ClubMembershipKind) {
            return $value;
        }
        $string = $this->nullableString($value);

        return $string === null ? null : ClubMembershipKind::tryFrom($string);
    }

    private function date(mixed $value): ?CarbonImmutable {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }
        $string = $this->nullableString($value);

        return $string === null ? null : CarbonImmutable::parse($string)->startOfDay();
    }

    private function email(mixed $value): ?string {
        $string = $this->nullableString($value);

        return $string === null ? null : mb_strtolower($string);
    }

    /**
     * Anschrift (`address_*`) in den Satelliten; nur übergebene Schlüssel
     * ändern sich, damit Importzeilen ohne Adresse nichts leeren.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function writeAddress(ClubMember|ClubGuardian $party, array $attributes): void {
        $address = ContactDetailsWriter::pullInline($attributes);
        if ($address !== []) {
            app(ContactDetailsWriter::class)->writeInline($party, $address);
        }
    }

    private function nullableString(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
