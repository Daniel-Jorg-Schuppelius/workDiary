<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingAssignmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Training;

use App\Enums\Training\TrainingRequirementSubject;
use App\Models\{Organization, Team, User, UserGroup};
use App\Models\Safety\{SafetyInstruction, SafetyInstructionParticipant};
use App\Models\Training\{TrainingAssignment, TrainingCourse, TrainingRequirement};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Soll-Einträge des Trainingsmanagements (Feature 145).
 *
 * Zwei Richtungen, eine Datenzeile je (Person, Kurs):
 *  - {@see syncOrganization()} zieht die Pflichtmatrix in konkrete Soll-
 *    Einträge (Rolle bzw. Team → Mitarbeitende); entfällt eine Zuordnung,
 *    verschwinden nur die NOCH NICHT nachgewiesenen Einträge.
 *  - {@see applyInstruction()} verrechnet den Nachweis aus dem Arbeitsschutz-
 *    Register (Feature 132): die Teilnehmerzeile IST der Nachweis, hier
 *    landen nur Zeiger darauf plus die nächste Fälligkeit aus der
 *    Kursgültigkeit. Keine Kopie von Signatur oder Datum als eigener Beleg.
 *
 * Der Service sperrt nichts — die Sperrwirkung bleibt beim
 * Qualifikationsstatus (Feature 013), kein zweiter Guard.
 */
class TrainingAssignmentService {
    /**
     * Pflichtmatrix → Soll-Einträge einer Organisation.
     *
     * @return array{created: int, removed: int}
     */
    public function syncOrganization(Organization $organization): array {
        /** @var \Illuminate\Database\Eloquent\Collection<int, TrainingRequirement> $requirements */
        $requirements = TrainingRequirement::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereIn('training_course_id', TrainingCourse::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->select('id'))
            ->with('course')
            ->get();

        /** @var array<int, array<int, TrainingRequirement>> $wanted */
        $wanted = [];
        foreach ($requirements as $requirement) {
            foreach ($this->userIdsFor($organization, $requirement) as $userId) {
                $wanted[$userId][(int) $requirement->training_course_id] ??= $requirement;
            }
        }

        $today = Carbon::today();
        $created = 0;

        foreach ($wanted as $userId => $courses) {
            foreach ($courses as $courseId => $requirement) {
                $exists = TrainingAssignment::query()->withoutGlobalScopes()
                    ->where('user_id', $userId)
                    ->where('training_course_id', $courseId)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $course = $requirement->course;
                $due = $today->copy()->addDays(max(0, (int) $requirement->first_due_days));
                TrainingAssignment::query()->create([
                    'organization_id' => $organization->id,
                    'user_id' => $userId,
                    'training_course_id' => $courseId,
                    'training_requirement_id' => $requirement->id,
                    'source' => 'requirement',
                    'due_at' => $due->toDateString(),
                    'notify_from' => $due->copy()->subDays($course?->leadDays() ?? 30)->toDateString(),
                ]);
                $created++;
            }
        }

        // Nicht mehr geforderte Soll-Einträge OHNE Nachweis fallen weg;
        // nachgewiesene bleiben als Historie stehen.
        $removed = 0;
        $obsolete = TrainingAssignment::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('source', 'requirement')
            ->whereNull('fulfilled_at')
            ->get();
        foreach ($obsolete as $assignment) {
            if (isset($wanted[(int) $assignment->user_id][(int) $assignment->training_course_id])) {
                continue;
            }
            $assignment->delete();
            $removed++;
        }

        return ['created' => $created, 'removed' => $removed];
    }

    /** Einzelnes Soll von Hand setzen (Sonderfall außerhalb der Matrix). */
    public function assignManually(Organization $organization, User $user, TrainingCourse $course, ?string $dueAt = null): TrainingAssignment {
        $due = Carbon::parse($dueAt ?? Carbon::today()->addDays(30)->toDateString());

        /** @var TrainingAssignment $assignment */
        $assignment = TrainingAssignment::query()->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('training_course_id', $course->id)
            ->first() ?? new TrainingAssignment();

        $assignment->fill([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'training_course_id' => $course->id,
            'source' => $assignment->exists ? $assignment->source : 'manual',
            'due_at' => $due->toDateString(),
            'notify_from' => $due->copy()->subDays($course->leadDays())->toDateString(),
        ])->save();

        return $assignment->refresh();
    }

    /**
     * Nachweise einer Unterweisung verrechnen (Feature 132 → 145): jede
     * Teilnahme erfüllt das Soll ihres Kurses, entfernte Teilnehmer öffnen
     * es wieder. Ohne Kursbezug an der Unterweisung passiert nichts.
     */
    public function applyInstruction(SafetyInstruction $instruction): int {
        $courseId = (int) $instruction->training_course_id;
        if ($courseId < 1) {
            return 0;
        }

        /** @var TrainingCourse|null $course */
        $course = TrainingCourse::query()->withoutGlobalScopes()->find($courseId);
        if ($course === null) {
            return 0;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, SafetyInstructionParticipant> $participants */
        $participants = $instruction->participants()->get();
        $userIds = array_values($participants->pluck('user_id')->map(static fn($id): int => (int) $id)->all());

        return DB::transaction(function () use ($course, $instruction, $participants, $userIds): int {
            $this->reopenDetached($instruction, $userIds);

            $touched = 0;
            foreach ($participants as $participant) {
                $touched += $this->fulfil($course, $instruction, $participant) ? 1 : 0;
            }

            return $touched;
        });
    }

    /** Unterweisung entfällt → alle darüber erfüllten Soll-Einträge öffnen. */
    public function releaseInstruction(SafetyInstruction $instruction): void {
        $this->reopenDetached($instruction, []);
    }

    /** Meldefenster/Fälligkeit eines Soll-Eintrags aus einem Nachweis fortschreiben. */
    private function fulfil(TrainingCourse $course, SafetyInstruction $instruction, SafetyInstructionParticipant $participant): bool {
        $heldOn = $instruction->held_on->copy()->startOfDay();

        /** @var TrainingAssignment $assignment */
        $assignment = TrainingAssignment::query()->withoutGlobalScopes()
            ->where('user_id', $participant->user_id)
            ->where('training_course_id', $course->id)
            ->first() ?? new TrainingAssignment([
                'organization_id' => $instruction->organization_id,
                'user_id' => $participant->user_id,
                'training_course_id' => $course->id,
                // Teilnahme ohne Pflichtzuordnung: der Nachweis startet den
                // Wiederholungszyklus trotzdem (Gültigkeit läuft ab Tag 1).
                'source' => 'manual',
            ]);

        // Nur der jüngste Nachweis zählt (wie im Register, Feature 132).
        if ($assignment->fulfilled_at !== null && $assignment->fulfilled_at->startOfDay()->gt($heldOn)) {
            return false;
        }

        $validity = (int) ($course->validity_months ?? 0);
        $next = $validity >= 1 ? $heldOn->copy()->addMonthsNoOverflow($validity) : null;

        $assignment->fill([
            'organization_id' => $instruction->organization_id,
            'user_id' => $participant->user_id,
            'training_course_id' => $course->id,
            'fulfilled_at' => $heldOn->toDateString(),
            'fulfilled_participant_id' => $participant->id,
            'fulfilled_instruction_id' => $instruction->id,
            'fulfilled_course_version' => $this->versionNumber($instruction),
            'due_at' => $next?->toDateString(),
            'notify_from' => $next?->copy()->subDays($course->leadDays())->toDateString(),
        ])->save();

        return true;
    }

    /**
     * Über diese Unterweisung erfüllte Soll-Einträge zurücksetzen, deren
     * Person nicht (mehr) Teilnehmer ist. Ohne Nachweis ist das Soll sofort
     * wieder offen — nichts wird still als erledigt geführt.
     *
     * @param  list<int>  $keepUserIds
     */
    private function reopenDetached(SafetyInstruction $instruction, array $keepUserIds): void {
        $today = Carbon::today()->toDateString();

        TrainingAssignment::query()->withoutGlobalScopes()
            ->where('fulfilled_instruction_id', $instruction->id)
            ->when($keepUserIds !== [], fn($query) => $query->whereNotIn('user_id', $keepUserIds))
            ->get()
            ->each(function (TrainingAssignment $assignment) use ($today): void {
                $assignment->update([
                    'fulfilled_at' => null,
                    'fulfilled_participant_id' => null,
                    'fulfilled_instruction_id' => null,
                    'fulfilled_course_version' => null,
                    'due_at' => $today,
                    'notify_from' => $today,
                ]);
            });
    }

    private function versionNumber(SafetyInstruction $instruction): ?int {
        $versionId = (int) $instruction->training_course_version_id;
        if ($versionId < 1) {
            return null;
        }

        return $instruction->trainingCourseVersion()->withoutGlobalScopes()->value('version');
    }

    /**
     * Mitarbeitende einer Zuordnung: Rolle (direkt oder über Benutzergruppe)
     * bzw. Team-Mitgliedschaft. Ausgeschiedene und anonymisierte Personen
     * bekommen kein neues Soll.
     *
     * @return list<int>
     */
    private function userIdsFor(Organization $organization, TrainingRequirement $requirement): array {
        $query = User::query()
            ->where('users.organization_id', $organization->id)
            ->whereNull('users.anonymized_at')
            ->where(function ($inner): void {
                $inner->whereNull('users.employment_end_date')
                    ->orWhere('users.employment_end_date', '>=', Carbon::today()->toDateString());
            });

        if ($requirement->subject_kind === TrainingRequirementSubject::Team) {
            $teamId = (int) $requirement->subject_key;
            $team = Team::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->find($teamId);
            if ($team === null) {
                return [];
            }

            $query->whereIn('users.id', DB::table('team_user')->where('team_id', $teamId)->select('user_id'));

            return array_values($query->pluck('users.id')->map(static fn($id): int => (int) $id)->all());
        }

        $roleName = $requirement->subject_key;
        $rolesTable = (string) config('permission.table_names.roles', 'roles');
        $pivotTable = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $teamKey = (string) config('permission.column_names.team_foreign_key', 'team_id');
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');

        $roleIds = DB::table($rolesTable)
            ->where('name', $roleName)
            ->where(function ($inner) use ($teamKey, $organization): void {
                $inner->where($teamKey, $organization->id)->orWhereNull($teamKey);
            })
            ->pluck('id');
        if ($roleIds->isEmpty()) {
            return [];
        }

        $directIds = DB::table($pivotTable)
            ->whereIn('role_id', $roleIds)
            ->where('model_type', User::class)
            ->pluck($morphKey);

        $groupIds = DB::table($pivotTable)
            ->whereIn('role_id', $roleIds)
            ->where('model_type', UserGroup::class)
            ->pluck($morphKey);
        $viaGroups = $groupIds->isEmpty()
            ? collect()
            : DB::table('user_user_group')->whereIn('user_group_id', $groupIds)->pluck('user_id');

        $candidates = array_values($directIds->merge($viaGroups)->map(static fn($id): int => (int) $id)->unique()->all());
        if ($candidates === []) {
            return [];
        }

        return array_values($query->whereIn('users.id', $candidates)->pluck('users.id')->map(static fn($id): int => (int) $id)->all());
    }
}
