<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGroupService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubCriteriaResult, ClubGroupMembershipStatus, ClubProposalStatus};
use App\Models\Club\{ClubGrade, ClubGradingSystem, ClubGroup, ClubGroupChangeProposal, ClubGroupMembership, ClubMember};
use App\Models\{Organization, User};
use App\Services\Concerns\AssertsStatusTransition;
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Einzige Schreibstelle für Gruppen, Gruppenzuordnungen und Wechsel-/
 * Prüfvorschläge (Feature 159, MVP-842). Aufnahme prüft Alter am Stichtag,
 * Kapazität und Doppelzuordnung in einer Transaktion; Ausnahmen brauchen
 * Recht und Begründung. Vorschläge entstehen aus dem täglichen Abgleich und
 * werden nie automatisch umgesetzt.
 */
class ClubGroupService {
    use AssertsStatusTransition;

    /**
     * Kriterien am Stichtag: Alter (Grenzen inklusive, leere Grenze offen, ohne
     * Geburtsdatum Prüfung nötig) und Grad (MVP-846: gültiger Grad der Ordnung
     * der Gruppe im Rangbereich; unbekannter Grad gewährt keinen Zugang).
     */
    public function evaluate(ClubGroup $group, ClubMember $member, CarbonInterface $on): ClubCriteriaResult {
        if ($group->hasAgeCriteria()) {
            // Mannschaften prüfen das Alter am Stichtag des Sportartenprofils innerhalb der Saison (MVP-852).
            $age = $member->ageOn(app(ClubTeamService::class)->ageReferenceDate($group, $on));
            if ($age === null) {
                return ClubCriteriaResult::ReviewRequired;
            }
            if ($group->min_age !== null && $age < $group->min_age) {
                return ClubCriteriaResult::AgeBelow;
            }
            if ($group->max_age !== null && $age > $group->max_age) {
                return ClubCriteriaResult::AgeAbove;
            }
        }

        if ($group->hasGradeCriteria()) {
            $system = $group->gradingSystem;
            $current = $system instanceof ClubGradingSystem ? app(ClubGradingService::class)->currentGrade($member, $system, $on) : null;
            $rank = $current?->grade?->rank;
            if ($rank === null) {
                return ClubCriteriaResult::GradeUnknown;
            }
            $min = $group->minGrade;
            $max = $group->maxGrade;
            if ($min instanceof ClubGrade && $rank < $min->rank) {
                return ClubCriteriaResult::GradeBelow;
            }
            if ($max instanceof ClubGrade && $rank > $max->rank) {
                return ClubCriteriaResult::GradeAbove;
            }
        }

        return ClubCriteriaResult::Met;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createGroup(Organization $organization, array $attributes): ClubGroup {
        return ClubGroup::query()->create([
            'organization_id' => $organization->id,
        ] + $this->groupAttributes((int) $organization->id, $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateGroup(ClubGroup $group, array $attributes): ClubGroup {
        $group->update($this->groupAttributes($group->organization_id, $attributes));

        return $group->refresh();
    }

    public function deleteGroup(ClubGroup $group): void {
        if ($group->activeMemberships()->exists() || $group->requestedMemberships()->exists()) {
            throw ValidationException::withMessages(['name' => __('club.error.group_has_members')]);
        }

        $group->delete();
    }

    /** Direkte Aufnahme durch die Leitung; Ausnahme von den Kriterien nur mit Begründung. */
    public function admit(ClubGroup $group, ClubMember $member, CarbonInterface $from, User $actor, bool $override = false, ?string $note = null): ClubGroupMembership {
        return DB::transaction(function () use ($group, $member, $from, $actor, $override, $note): ClubGroupMembership {
            $group = $this->lockGroup($group->id);
            $day = CarbonImmutable::instance($from)->startOfDay();

            $this->assertAdmittable($group, $member, $day);
            $this->assertCriteria($group, $member, $day, $override, $note);
            $this->assertCapacity($group, $day);

            $membership = ClubGroupMembership::query()->create([
                'organization_id' => $group->organization_id,
                'club_group_id' => $group->id,
                'club_member_id' => $member->id,
                'status' => ClubGroupMembershipStatus::Active->value,
                'valid_from' => $day->toDateString(),
                'valid_to' => null,
                'note' => $this->nullableString($note),
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ]);
            $membership->audit('club.group.memberAdmitted', ['override' => $override, 'valid_from' => $day->toDateString()]);

            return $membership;
        });
    }

    /** Antrag ohne Kriterien-/Kapazitätsprüfung — die läuft bei der Freigabe. */
    public function request(ClubGroup $group, ClubMember $member, CarbonInterface $from, ?User $actor = null, ?string $note = null): ClubGroupMembership {
        return DB::transaction(function () use ($group, $member, $from, $actor, $note): ClubGroupMembership {
            $group = $this->lockGroup($group->id);
            $day = CarbonImmutable::instance($from)->startOfDay();
            $this->assertAdmittable($group, $member, $day);

            $membership = ClubGroupMembership::query()->create([
                'organization_id' => $group->organization_id,
                'club_group_id' => $group->id,
                'club_member_id' => $member->id,
                'status' => ClubGroupMembershipStatus::Requested->value,
                'valid_from' => $day->toDateString(),
                'note' => $this->nullableString($note),
            ]);
            $membership->audit('club.group.membershipRequested', ['valid_from' => $day->toDateString(), 'actor_id' => $actor?->id]);

            return $membership;
        });
    }

    public function approve(ClubGroupMembership $membership, User $actor, bool $override = false, ?string $note = null): ClubGroupMembership {
        return DB::transaction(function () use ($membership, $actor, $override, $note): ClubGroupMembership {
            $group = $this->lockGroup($membership->club_group_id);
            $this->assertTransition($membership, ClubGroupMembershipStatus::Active);

            $day = $membership->valid_from->greaterThan(CarbonImmutable::today()) ? CarbonImmutable::instance($membership->valid_from) : CarbonImmutable::today();
            /** @var ClubMember $member */
            $member = $membership->member;
            if ($member->hasLeftOn($day)) {
                throw ValidationException::withMessages(['club_member_id' => __('club.error.member_left')]);
            }
            $this->assertCriteria($group, $member, $day, $override, $note);
            $this->assertCapacity($group, $day);

            $membership->update([
                'status' => ClubGroupMembershipStatus::Active->value,
                'valid_from' => $day->toDateString(),
                'note' => $this->nullableString($note) ?? $membership->note,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ]);
            $membership->audit('club.group.membershipApproved', ['override' => $override, 'valid_from' => $day->toDateString()]);

            return $membership->refresh();
        });
    }

    public function reject(ClubGroupMembership $membership, User $actor, ?string $note = null): ClubGroupMembership {
        $this->assertTransition($membership, ClubGroupMembershipStatus::Rejected);

        $membership->update([
            'status' => ClubGroupMembershipStatus::Rejected->value,
            'note' => $this->nullableString($note) ?? $membership->note,
            'decided_by_user_id' => $actor->id,
            'decided_at' => now(),
        ]);
        $membership->audit('club.group.membershipRejected', ['note' => $this->nullableString($note)]);

        return $membership->refresh();
    }

    /** Beenden zum Datum (letzter gültiger Tag); offene Vorschläge dieses Paars werden verworfen. */
    public function end(ClubGroupMembership $membership, CarbonInterface $on, User $actor, ?string $note = null): ClubGroupMembership {
        return DB::transaction(function () use ($membership, $on, $actor, $note): ClubGroupMembership {
            $this->assertTransition($membership, ClubGroupMembershipStatus::Ended);
            $day = CarbonImmutable::instance($on)->startOfDay();
            if ($day->lessThan($membership->valid_from)) {
                $day = CarbonImmutable::instance($membership->valid_from);
            }

            $membership->update([
                'status' => ClubGroupMembershipStatus::Ended->value,
                'valid_to' => $day->toDateString(),
                'note' => $this->nullableString($note) ?? $membership->note,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ]);
            $membership->audit('club.group.membershipEnded', ['valid_to' => $day->toDateString()]);

            $this->openProposalQuery($membership->organization_id, $membership->club_member_id, $membership->club_group_id)
                ->get()
                ->each(fn(ClubGroupChangeProposal $proposal) => $this->dismissProposal($proposal, $actor, (string) __('club.note.membership_ended')));

            return $membership->refresh();
        });
    }

    /**
     * Täglicher Abgleich: Kriterien jeder aktiven Zuordnung gegen den Stichtag.
     * Verletzung → offener Vorschlag (einmal je Mitglied und Gruppe, Grund wird
     * nachgeführt); wieder erfüllt → offener Vorschlag wird verworfen. Kein
     * Mitglied wird entfernt.
     *
     * @return int neu angelegte Vorschläge
     */
    public function refreshProposals(Organization $organization, ?CarbonInterface $today = null, ?ClubMember $only = null): int {
        $day = CarbonImmutable::instance($today ?? CarbonImmutable::today())->startOfDay();
        $created = 0;

        $memberships = ClubGroupMembership::query()
            ->where('organization_id', $organization->id)
            ->when($only !== null, fn(Builder $query) => $query->where('club_member_id', $only?->id))
            ->where('status', ClubGroupMembershipStatus::Active->value)
            ->where('valid_from', '<', DateRange::dayAfter($day))
            ->where(function (Builder $query) use ($day): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($day));
            })
            ->whereHas('group', function (Builder $query): void {
                $query->where('is_active', true)->where(function (Builder $inner): void {
                    $inner->whereNotNull('min_age')->orWhereNotNull('max_age')->orWhereNotNull('min_grade_id')->orWhereNotNull('max_grade_id');
                });
            })
            ->with(['group', 'member'])
            ->get();

        foreach ($memberships as $membership) {
            /** @var ClubGroup $group */
            $group = $membership->group;
            /** @var ClubMember $member */
            $member = $membership->member;
            if ($member->hasLeftOn($day)) {
                continue;
            }

            $result = $this->evaluate($group, $member, $day);
            /** @var ClubGroupChangeProposal|null $open */
            $open = $this->openProposalQuery($organization->id, $member->id, $group->id)->first();

            if ($result->isMet()) {
                if ($open !== null) {
                    $open->update([
                        'status' => ClubProposalStatus::Dismissed->value,
                        'note' => (string) __('club.note.criteria_met_again'),
                        'decided_at' => now(),
                    ]);
                    $open->audit('club.proposal.dismissed', ['automatic' => true]);
                }

                continue;
            }

            if ($open !== null) {
                if ($open->reason !== $result) {
                    $open->update(['reason' => $result->value]);
                }

                continue;
            }

            $proposal = ClubGroupChangeProposal::query()->create([
                'organization_id' => $organization->id,
                'club_member_id' => $member->id,
                'club_group_id' => $group->id,
                'reason' => $result->value,
                'status' => ClubProposalStatus::Open->value,
                'suggested_group_id' => $this->suggestGroup($organization, $group, $member, $day)?->id,
            ]);
            $proposal->audit('club.proposal.created', ['reason' => $result->value]);
            $created++;
        }

        return $created;
    }

    /** Bestätigen mit Wirksamkeitsdatum: alte Zuordnung endet am Vortag, optional Aufnahme in die Zielgruppe. */
    public function confirmProposal(ClubGroupChangeProposal $proposal, User $actor, CarbonInterface $effectiveOn, ?ClubGroup $target, bool $override = false, ?string $note = null): ClubGroupChangeProposal {
        return DB::transaction(function () use ($proposal, $actor, $effectiveOn, $target, $override, $note): ClubGroupChangeProposal {
            if (! $proposal->isOpen()) {
                throw ValidationException::withMessages(['effective_on' => __('club.error.proposal_decided')]);
            }
            $day = CarbonImmutable::instance($effectiveOn)->startOfDay();
            /** @var ClubMember $member */
            $member = $proposal->member;

            $current = ClubGroupMembership::query()
                ->where('organization_id', $proposal->organization_id)
                ->where('club_member_id', $member->id)
                ->where('club_group_id', $proposal->club_group_id)
                ->where('status', ClubGroupMembershipStatus::Active->value)
                ->lockForUpdate()
                ->get();
            foreach ($current as $membership) {
                $lastDay = $day->subDay();
                if ($lastDay->lessThan($membership->valid_from)) {
                    $lastDay = CarbonImmutable::instance($membership->valid_from);
                }
                $membership->update([
                    'status' => ClubGroupMembershipStatus::Ended->value,
                    'valid_to' => $lastDay->toDateString(),
                    'note' => (string) __('club.note.proposal_confirmed'),
                    'decided_by_user_id' => $actor->id,
                    'decided_at' => now(),
                ]);
                $membership->audit('club.group.membershipEnded', ['valid_to' => $lastDay->toDateString(), 'proposal_id' => $proposal->id]);
            }

            if ($target !== null) {
                if ($target->organization_id !== $proposal->organization_id) {
                    throw ValidationException::withMessages(['suggested_group_id' => __('club.error.group_foreign')]);
                }
                $this->admit($target, $member, $day, $actor, $override, $note);
            }

            $proposal->update([
                'status' => ClubProposalStatus::Confirmed->value,
                'effective_on' => $day->toDateString(),
                'suggested_group_id' => $target?->id,
                'note' => $this->nullableString($note),
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ]);
            $proposal->audit('club.proposal.confirmed', ['effective_on' => $day->toDateString(), 'target_group_id' => $target?->id]);

            return $proposal->refresh();
        });
    }

    public function dismissProposal(ClubGroupChangeProposal $proposal, User $actor, ?string $note = null): ClubGroupChangeProposal {
        if (! $proposal->isOpen()) {
            return $proposal;
        }

        $proposal->update([
            'status' => ClubProposalStatus::Dismissed->value,
            'note' => $this->nullableString($note),
            'decided_by_user_id' => $actor->id,
            'decided_at' => now(),
        ]);
        $proposal->audit('club.proposal.dismissed', ['note' => $this->nullableString($note)]);

        return $proposal->refresh();
    }

    /** Zielgruppe nur, wenn in derselben Abteilung genau eine passende, freie Gruppe existiert — sonst entscheidet die Leitung. */
    private function suggestGroup(Organization $organization, ClubGroup $current, ClubMember $member, CarbonInterface $day): ?ClubGroup {
        $candidates = ClubGroup::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereKeyNot($current->id)
            ->where('club_department_id', $current->club_department_id)
            ->get()
            ->filter(fn(ClubGroup $group): bool => $group->hasCriteria()
                && $this->evaluate($group, $member, $day)->isMet()
                && $group->hasCapacityOn($day));

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /** @return Builder<ClubGroupChangeProposal> */
    private function openProposalQuery(int $organizationId, int $memberId, int $groupId): Builder {
        return ClubGroupChangeProposal::query()
            ->where('organization_id', $organizationId)
            ->where('club_member_id', $memberId)
            ->where('club_group_id', $groupId)
            ->where('status', ClubProposalStatus::Open->value);
    }

    private function lockGroup(int $groupId): ClubGroup {
        /** @var ClubGroup $locked */
        $locked = ClubGroup::query()->whereKey($groupId)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    private function assertAdmittable(ClubGroup $group, ClubMember $member, CarbonInterface $day): void {
        if ($group->organization_id !== $member->organization_id) {
            throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
        }
        if (! $group->is_active) {
            throw ValidationException::withMessages(['club_member_id' => __('club.error.group_inactive')]);
        }
        if ($member->hasLeftOn($day)) {
            throw ValidationException::withMessages(['club_member_id' => __('club.error.member_left')]);
        }

        $duplicate = ClubGroupMembership::query()
            ->where('club_group_id', $group->id)
            ->where('club_member_id', $member->id)
            ->where(function (Builder $query) use ($day): void {
                $query->where('status', ClubGroupMembershipStatus::Requested->value)
                    ->orWhere(function (Builder $active) use ($day): void {
                        $active->where('status', ClubGroupMembershipStatus::Active->value)
                            ->where(function (Builder $valid) use ($day): void {
                                $valid->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($day));
                            });
                    });
            })
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['club_member_id' => __('club.error.already_member')]);
        }
    }

    private function assertCriteria(ClubGroup $group, ClubMember $member, CarbonInterface $day, bool $override, ?string $note): void {
        $result = $this->evaluate($group, $member, $day);
        if ($result->isMet()) {
            return;
        }
        if (! $override) {
            throw ValidationException::withMessages(['club_member_id' => __('club.error.criteria.' . $result->value)]);
        }
        if ($this->nullableString($note) === null) {
            throw ValidationException::withMessages(['note' => __('club.error.override_note_required')]);
        }
    }

    private function assertCapacity(ClubGroup $group, CarbonInterface $day): void {
        if (! $group->hasCapacityOn($day)) {
            throw ValidationException::withMessages(['club_member_id' => __('club.error.group_full')]);
        }
    }

    private function assertTransition(ClubGroupMembership $membership, ClubGroupMembershipStatus $target): void {
        $this->assertStatusTransition($membership->status, $target);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function groupAttributes(int $organizationId, array $attributes): array {
        $minAge = $this->nullableInt($attributes['min_age'] ?? null);
        $maxAge = $this->nullableInt($attributes['max_age'] ?? null);
        if ($minAge !== null && $maxAge !== null && $maxAge < $minAge) {
            throw ValidationException::withMessages(['max_age' => __('club.error.age_range')]);
        }

        $leaderId = $this->nullableInt($attributes['leader_user_id'] ?? null);
        if ($leaderId !== null && ! User::query()->whereKey($leaderId)->where('organization_id', $organizationId)->exists()) {
            throw ValidationException::withMessages(['leader_user_id' => __('club.error.user_foreign')]);
        }

        $systemId = $this->nullableInt($attributes['club_grading_system_id'] ?? null);
        $minGradeId = $this->nullableInt($attributes['min_grade_id'] ?? null);
        $maxGradeId = $this->nullableInt($attributes['max_grade_id'] ?? null);
        if ($systemId === null) {
            $minGradeId = null;
            $maxGradeId = null;
        } else {
            $grades = ClubGrade::query()->whereIn('id', array_filter([$minGradeId, $maxGradeId]))->where('club_grading_system_id', $systemId)->get()->keyBy('id');
            if (($minGradeId !== null && ! $grades->has($minGradeId)) || ($maxGradeId !== null && ! $grades->has($maxGradeId))) {
                throw ValidationException::withMessages(['min_grade_id' => __('club.grading.error.grade_foreign')]);
            }
            $minGrade = $minGradeId !== null ? $grades->get($minGradeId) : null;
            $maxGrade = $maxGradeId !== null ? $grades->get($maxGradeId) : null;
            if ($minGrade !== null && $maxGrade !== null && $minGrade->rank > $maxGrade->rank) {
                throw ValidationException::withMessages(['max_grade_id' => __('club.grading.error.grade_range')]);
            }
        }

        $profileId = $this->nullableInt($attributes['club_sport_profile_id'] ?? null);
        if ($profileId !== null && ! \App\Models\Club\ClubSportProfile::query()->whereKey($profileId)->where('organization_id', $organizationId)->exists()) {
            throw ValidationException::withMessages(['club_sport_profile_id' => __('club.teams.error.profile_foreign')]);
        }

        $mode = $attributes['admission_mode'] ?? null;
        $mode = $mode instanceof \App\Enums\Club\ClubAdmissionMode ? $mode : \App\Enums\Club\ClubAdmissionMode::tryFrom((string) $mode);

        return [
            'club_department_id' => $this->nullableInt($attributes['club_department_id'] ?? null),
            'name' => trim((string) $attributes['name']),
            'description' => $this->nullableString($attributes['description'] ?? null),
            'leader_user_id' => $leaderId,
            'max_members' => $this->nullableInt($attributes['max_members'] ?? null),
            'admission_mode' => ($mode ?? \App\Enums\Club\ClubAdmissionMode::Leader)->value,
            'min_age' => $minAge,
            'max_age' => $maxAge,
            'criteria_note' => $this->nullableString($attributes['criteria_note'] ?? null),
            'discipline' => $this->nullableString($attributes['discipline'] ?? null),
            'club_grading_system_id' => $systemId,
            'min_grade_id' => $minGradeId,
            'max_grade_id' => $maxGradeId,
            'is_team' => (bool) ($attributes['is_team'] ?? false),
            'club_sport_profile_id' => $profileId,
            'age_class' => $this->nullableString($attributes['age_class'] ?? null),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
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
