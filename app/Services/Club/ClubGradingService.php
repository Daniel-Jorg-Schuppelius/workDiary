<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGradingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubCountingBasis, ClubEventKind, ClubGradeSource, ClubGradingVersionStatus, ClubProofKind};
use App\Models\Club\{ClubGrade, ClubGradeRequirement, ClubGradingSystem, ClubGradingVersion, ClubMember, ClubMemberGrade, ClubMemberProof};
use App\Models\{Organization, User};
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Graduierungsordnungen, Regelversionen, Grade, Anerkennungen und Nachweise
 * (Feature 159, MVP-846) — einzige Schreibstelle. Das Modul ist je
 * Organisation zuschaltbar; Regeln sind Vereinskonfiguration, keine
 * Verbandsvorgaben. Referenzierte Grade/Versionen sind nicht löschbar.
 */
class ClubGradingService {
    public function __construct(
        private readonly ClubGroupService $groups,
    ) {}

    public function isEnabled(?Organization $organization): bool {
        return (bool) ($organization?->settings['club']['graduation']['enabled'] ?? false);
    }

    public function setEnabled(Organization $organization, bool $enabled): void {
        $settings = (array) ($organization->settings ?? []);
        data_set($settings, 'club.graduation.enabled', $enabled);
        $organization->update(['settings' => $settings]);
    }

    // ── Ordnungen und Grade ──────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function createSystem(Organization $organization, array $attributes): ClubGradingSystem {
        $system = ClubGradingSystem::query()->create(['organization_id' => $organization->id] + $this->systemAttributes($attributes));
        $system->audit('club.grading.systemCreated');

        return $system;
    }

    /** @param array<string, mixed> $attributes */
    public function updateSystem(ClubGradingSystem $system, array $attributes): ClubGradingSystem {
        $system->update($this->systemAttributes($attributes));
        $system->audit('club.grading.systemUpdated');

        return $system->refresh();
    }

    public function deleteSystem(ClubGradingSystem $system): void {
        if ($system->memberGrades()->exists()) {
            throw ValidationException::withMessages(['name' => __('club.grading.error.system_in_use')]);
        }
        $system->audit('club.grading.systemDeleted');
        $system->delete();
    }

    /** @param array<string, mixed> $attributes */
    public function createGrade(ClubGradingSystem $system, array $attributes): ClubGrade {
        $rank = isset($attributes['rank']) && $attributes['rank'] !== '' ? (int) $attributes['rank'] : ((int) $system->grades()->max('rank') + 1);
        $grade = ClubGrade::query()->create([
            'organization_id' => $system->organization_id,
            'club_grading_system_id' => $system->id,
            'name' => trim((string) $attributes['name']),
            'rank' => $rank,
            'color' => $this->nullableString($attributes['color'] ?? null),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
        ]);
        $grade->audit('club.grading.gradeCreated', ['rank' => $rank]);

        return $grade;
    }

    /** @param array<string, mixed> $attributes */
    public function updateGrade(ClubGrade $grade, array $attributes): ClubGrade {
        $grade->update([
            'name' => trim((string) $attributes['name']),
            'rank' => isset($attributes['rank']) && $attributes['rank'] !== '' ? (int) $attributes['rank'] : $grade->rank,
            'color' => $this->nullableString($attributes['color'] ?? null),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
        ]);
        $grade->audit('club.grading.gradeUpdated');

        return $grade->refresh();
    }

    public function deleteGrade(ClubGrade $grade): void {
        $referenced = $grade->memberGrades()->exists()
            || ClubGradeRequirement::query()->where(fn($q) => $q->where('club_grade_id', $grade->id)->orWhere('previous_grade_id', $grade->id))->exists();
        if ($referenced) {
            throw ValidationException::withMessages(['name' => __('club.grading.error.grade_in_use')]);
        }
        $grade->audit('club.grading.gradeDeleted');
        $grade->delete();
    }

    // ── Regelversionen ───────────────────────────────────────────────────

    /**
     * Neue Entwurfsversion; Voraussetzungen der zuletzt aktiven Version werden kopiert.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createVersion(ClubGradingSystem $system, array $attributes = []): ClubGradingVersion {
        return DB::transaction(function () use ($system, $attributes): ClubGradingVersion {
            $source = $system->activeVersion() ?? $system->versions()->first();
            $version = ClubGradingVersion::query()->create([
                'organization_id' => $system->organization_id,
                'club_grading_system_id' => $system->id,
                'version_no' => (int) $system->versions()->max('version_no') + 1,
                'status' => ClubGradingVersionStatus::Draft->value,
                'valid_from' => $this->nullableString($attributes['valid_from'] ?? null),
                'unit_minutes' => $this->nullableInt($attributes['unit_minutes'] ?? ($source?->unit_minutes)),
                'accepts_external_credits' => (bool) ($attributes['accepts_external_credits'] ?? ($source !== null ? $source->accepts_external_credits : false)),
                'notes' => $this->nullableString($attributes['notes'] ?? null),
            ]);
            if ($source !== null) {
                foreach ($source->requirements as $requirement) {
                    ClubGradeRequirement::query()->create(['club_grading_version_id' => $version->id] + $requirement->only([
                        'organization_id', 'club_grade_id', 'previous_grade_id', 'min_minutes', 'min_sessions', 'min_minutes_per_session', 'counting_basis',
                        'window_months', 'wait_months', 'min_age', 'counted_event_kinds', 'counted_group_ids', 'required_proof_label', 'requires_approval', 'allows_exception',
                    ]));
                }
            }
            $version->audit('club.grading.versionCreated', ['version_no' => $version->version_no, 'copied_from' => $source?->version_no]);

            return $version;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateVersion(ClubGradingVersion $version, array $attributes): ClubGradingVersion {
        $version->update([
            'valid_from' => $this->nullableString($attributes['valid_from'] ?? null),
            'unit_minutes' => $this->nullableInt($attributes['unit_minutes'] ?? null),
            'accepts_external_credits' => (bool) ($attributes['accepts_external_credits'] ?? false),
            'notes' => $this->nullableString($attributes['notes'] ?? null),
        ]);
        $version->audit('club.grading.versionUpdated');

        return $version->refresh();
    }

    /** Aktivieren: die bisher aktive Version wird abgelöst; Prüfungsangebote behalten ihre Version. */
    public function activateVersion(ClubGradingVersion $version, User $actor): ClubGradingVersion {
        return DB::transaction(function () use ($version, $actor): ClubGradingVersion {
            if ($version->isActive()) {
                return $version;
            }
            ClubGradingVersion::query()
                ->where('club_grading_system_id', $version->club_grading_system_id)
                ->where('status', ClubGradingVersionStatus::Active->value)
                ->update(['status' => ClubGradingVersionStatus::Superseded->value]);
            $version->update(['status' => ClubGradingVersionStatus::Active->value, 'activated_at' => now()]);
            $version->audit('club.grading.versionActivated', ['actor_id' => $actor->id]);

            return $version->refresh();
        });
    }

    /**
     * Voraussetzung je Zielgrad speichern (eine je Version und Grad).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function saveRequirement(ClubGradingVersion $version, ClubGrade $grade, array $attributes): ClubGradeRequirement {
        if ($grade->club_grading_system_id !== $version->club_grading_system_id) {
            throw ValidationException::withMessages(['club_grade_id' => __('club.grading.error.grade_foreign')]);
        }
        $previousId = $this->nullableInt($attributes['previous_grade_id'] ?? null);
        if ($previousId !== null) {
            $previous = ClubGrade::query()->whereKey($previousId)->where('club_grading_system_id', $version->club_grading_system_id)->first();
            if ($previous === null || $previous->rank >= $grade->rank) {
                throw ValidationException::withMessages(['previous_grade_id' => __('club.grading.error.previous_rank')]);
            }
        }
        $basis = ClubCountingBasis::tryFrom((string) ($attributes['counting_basis'] ?? '')) ?? ClubCountingBasis::SincePreviousGrade;
        $windowMonths = $this->nullableInt($attributes['window_months'] ?? null);
        if ($basis === ClubCountingBasis::WindowMonths && ($windowMonths === null || $windowMonths < 1)) {
            throw ValidationException::withMessages(['window_months' => __('club.grading.error.window_required')]);
        }
        $kinds = array_values(array_filter(array_map('strval', (array) ($attributes['counted_event_kinds'] ?? [])), fn(string $k): bool => ClubEventKind::tryFrom($k) !== null));
        $groupIds = array_values(array_filter(array_map('intval', (array) ($attributes['counted_group_ids'] ?? []))));

        $requirement = ClubGradeRequirement::query()->updateOrCreate(
            ['club_grading_version_id' => $version->id, 'club_grade_id' => $grade->id],
            [
                'organization_id' => $version->organization_id,
                'previous_grade_id' => $previousId,
                'min_minutes' => $this->nullableInt($attributes['min_minutes'] ?? null),
                'min_sessions' => $this->nullableInt($attributes['min_sessions'] ?? null),
                'min_minutes_per_session' => $this->nullableInt($attributes['min_minutes_per_session'] ?? null),
                'counting_basis' => $basis->value,
                'window_months' => $basis === ClubCountingBasis::WindowMonths ? $windowMonths : null,
                'wait_months' => $this->nullableInt($attributes['wait_months'] ?? null),
                'min_age' => $this->nullableInt($attributes['min_age'] ?? null),
                'counted_event_kinds' => $kinds !== [] ? $kinds : null,
                'counted_group_ids' => $groupIds !== [] ? $groupIds : null,
                'required_proof_label' => $this->nullableString($attributes['required_proof_label'] ?? null),
                'requires_approval' => (bool) ($attributes['requires_approval'] ?? false),
                'allows_exception' => (bool) ($attributes['allows_exception'] ?? false),
            ],
        );
        $requirement->audit('club.grading.requirementSaved', ['grade_id' => $grade->id]);

        return $requirement;
    }

    public function deleteRequirement(ClubGradeRequirement $requirement): void {
        $requirement->audit('club.grading.requirementDeleted');
        $requirement->delete();
    }

    /** Voraussetzung für den nächsthöheren Grad des Mitglieds in der aktiven Version, sonst null. */
    public function nextRequirement(ClubMember $member, ClubGradingSystem $system, ?CarbonInterface $on = null): ?ClubGradeRequirement {
        $version = $system->activeVersion();
        if ($version === null) {
            return null;
        }
        $current = $this->currentGrade($member, $system, $on ?? CarbonImmutable::today());
        $currentRank = $current?->grade->rank ?? -1;
        /** @var ClubGrade|null $next */
        $next = $system->grades()->where('is_active', true)->where('rank', '>', $currentRank)->orderBy('rank')->first();
        if ($next === null) {
            return null;
        }
        /** @var ClubGradeRequirement|null $requirement */
        $requirement = $version->requirements()->where('club_grade_id', $next->id)->with(['version.system', 'grade', 'previousGrade'])->first();

        return $requirement;
    }

    // ── Grade je Mitglied ────────────────────────────────────────────────

    /** Gültiger höchster Grad des Mitglieds in dieser Ordnung am Stichtag. */
    public function currentGrade(ClubMember $member, ClubGradingSystem $system, CarbonInterface $on): ?ClubMemberGrade {
        /** @var ClubMemberGrade|null $grade */
        $grade = ClubMemberGrade::query()
            ->valid()
            ->where('club_member_grades.club_member_id', $member->id)
            ->where('club_member_grades.club_grading_system_id', $system->id)
            ->where('club_member_grades.obtained_on', '<', DateRange::dayAfter($on))
            ->join('club_grades', 'club_grades.id', '=', 'club_member_grades.club_grade_id')
            ->orderByDesc('club_grades.rank')
            ->orderByDesc('club_member_grades.obtained_on')
            ->select('club_member_grades.*')
            ->with('grade')
            ->first();

        return $grade;
    }

    /** Anerkennung eines vorhandenen Grades (Vereinswechsel) mit Datum und Beleg; danach Gruppenvorschläge prüfen. */
    public function recognizeGrade(ClubMember $member, ClubGrade $grade, CarbonInterface $on, ?string $evidence, User $actor): ClubMemberGrade {
        return $this->awardGrade($member, $grade, $on, ClubGradeSource::Recognized, $actor, $evidence);
    }

    /** Gradvergabe (Prüfung oder Anerkennung): genau einmal je Mitglied, Grad und Datum. */
    public function awardGrade(ClubMember $member, ClubGrade $grade, CarbonInterface $on, ClubGradeSource $source, User $actor, ?string $evidence = null, ?int $candidateId = null): ClubMemberGrade {
        return DB::transaction(function () use ($member, $grade, $on, $source, $actor, $evidence, $candidateId): ClubMemberGrade {
            if ($member->organization_id !== $grade->organization_id) {
                throw ValidationException::withMessages(['club_grade_id' => __('club.grading.error.grade_foreign')]);
            }
            $day = CarbonImmutable::instance($on)->startOfDay();
            /** @var ClubMemberGrade|null $existing */
            $existing = ClubMemberGrade::query()->valid()
                ->where('club_member_id', $member->id)
                ->where('club_grade_id', $grade->id)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $awarded = ClubMemberGrade::query()->create([
                'organization_id' => $member->organization_id,
                'club_member_id' => $member->id,
                'club_grading_system_id' => $grade->club_grading_system_id,
                'club_grade_id' => $grade->id,
                'obtained_on' => $day->toDateString(),
                'source' => $source->value,
                'club_exam_candidate_id' => $candidateId,
                'evidence' => $this->nullableString($evidence),
                'confirmed_by_user_id' => $actor->id,
            ]);
            $awarded->audit($source === ClubGradeSource::Recognized ? 'club.grading.gradeRecognized' : 'club.grading.gradeAwarded', ['grade' => $grade->name, 'obtained_on' => $day->toDateString()]);

            // Neuer Grad → Gruppenkriterien neu bewerten (Wechselvorschlag, keine automatische Entfernung).
            $organization = Organization::query()->whereKey($member->organization_id)->first();
            if ($organization !== null) {
                $this->groups->refreshProposals($organization, CarbonImmutable::today(), $member);
            }

            return $awarded;
        });
    }

    /** Widerruf als eigener, begründeter Vorgang — löscht nichts. */
    public function revokeGrade(ClubMemberGrade $memberGrade, string $reason, User $actor): ClubMemberGrade {
        if ($memberGrade->isRevoked()) {
            return $memberGrade;
        }
        $memberGrade->update(['revoked_at' => now(), 'revoked_by_user_id' => $actor->id, 'revoke_reason' => trim($reason)]);
        $memberGrade->audit('club.grading.gradeRevoked', ['reason' => trim($reason)]);

        return $memberGrade->refresh();
    }

    // ── Nachweise ────────────────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function addProof(ClubMember $member, array $attributes, User $actor): ClubMemberProof {
        $proof = ClubMemberProof::query()->create(['organization_id' => $member->organization_id, 'club_member_id' => $member->id, 'confirmed_by_user_id' => $actor->id] + $this->proofAttributes($attributes));
        $proof->audit('club.grading.proofAdded', ['kind' => $proof->kind->value, 'label' => $proof->label]);

        return $proof;
    }

    /** @param array<string, mixed> $attributes */
    public function updateProof(ClubMemberProof $proof, array $attributes, User $actor): ClubMemberProof {
        $proof->update($this->proofAttributes($attributes) + ['confirmed_by_user_id' => $actor->id]);
        $proof->audit('club.grading.proofUpdated');

        return $proof->refresh();
    }

    public function deleteProof(ClubMemberProof $proof): void {
        $proof->audit('club.grading.proofDeleted', ['label' => $proof->label]);
        $proof->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function systemAttributes(array $attributes): array {
        return [
            'name' => trim((string) $attributes['name']),
            'discipline' => trim((string) $attributes['discipline']),
            'description' => $this->nullableString($attributes['description'] ?? null),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function proofAttributes(array $attributes): array {
        $kind = ClubProofKind::tryFrom((string) ($attributes['kind'] ?? '')) ?? ClubProofKind::Course;
        $obtained = $this->nullableString($attributes['obtained_on'] ?? null);
        if ($obtained === null) {
            throw ValidationException::withMessages(['obtained_on' => __('club.grading.error.obtained_required')]);
        }

        return [
            'kind' => $kind->value,
            'label' => trim((string) $attributes['label']),
            'discipline' => $this->nullableString($attributes['discipline'] ?? null),
            'minutes' => $kind === ClubProofKind::ExternalTraining ? $this->nullableInt($attributes['minutes'] ?? null) : null,
            'sessions' => $kind === ClubProofKind::ExternalTraining ? $this->nullableInt($attributes['sessions'] ?? null) : null,
            'obtained_on' => $obtained,
            'valid_until' => $this->nullableString($attributes['valid_until'] ?? null),
            'origin' => $this->nullableString($attributes['origin'] ?? null),
            'note' => $this->nullableString($attributes['note'] ?? null),
        ];
    }

    private function nullableInt(mixed $value): ?int {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableString(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
