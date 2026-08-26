<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyInstructionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Safety;

use App\Enums\Safety\InstructionSignatureMethod;
use App\Models\{Organization, User};
use App\Models\Safety\{SafetyInstruction, SafetyInstructionParticipant};
use App\Services\Concerns\AssignsSequentialNo;
use App\Services\Training\TrainingAssignmentService;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service der Unterweisungen (DGUV V1 § 4, Feature 132): Anlage mit
 * laufender instruction_no je Org und Teilnehmerliste, Abgleich der
 * Teilnehmer (signierte Nachweise bleiben immer stehen), Fälligkeit je
 * Person (held_on + Intervall) und der Signatur-Flow — das
 * ProtocolSignature-Muster in schlank: die angemeldete Person bestätigt
 * ihre eigene Teilnahme; Name, Zeitpunkt, IP und Inhalts-Hash werden
 * festgeschrieben. Niemand signiert für andere (Nachweis-Charakter).
 */
class SafetyInstructionService {
    use AssignsSequentialNo;

    public function __construct(
        private readonly TrainingAssignmentService $training,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $participantUserIds
     */
    public function create(Organization $organization, User $creator, array $attributes, array $participantUserIds): SafetyInstruction {
        return DB::transaction(function () use ($organization, $creator, $attributes, $participantUserIds): SafetyInstruction {
            $instruction = SafetyInstruction::query()->create([
                'organization_id' => $organization->id,
                'instruction_no' => $this->nextNo(SafetyInstruction::class, 'instruction_no', 'organization_id', (int) $organization->id),
                'topic' => $attributes['topic'],
                'hazard_assessment_id' => $attributes['hazard_assessment_id'] ?? null,
                'training_course_id' => $attributes['training_course_id'] ?? null,
                'training_course_version_id' => $attributes['training_course_version_id'] ?? null,
                'held_on' => $attributes['held_on'],
                'instructor_user_id' => $attributes['instructor_user_id'] ?? $creator->id,
                'repeat_interval_months' => $attributes['repeat_interval_months'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            $this->syncParticipants($instruction, $participantUserIds);
            // Feature 145: mit Kursbezug erfüllt die Teilnahme das Trainings-Soll.
            $this->training->applyInstruction($instruction->refresh());

            return $instruction->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>|null  $participantUserIds  null = Teilnehmer unverändert
     */
    public function update(SafetyInstruction $instruction, array $attributes, ?array $participantUserIds = null): SafetyInstruction {
        return DB::transaction(function () use ($instruction, $attributes, $participantUserIds): SafetyInstruction {
            $instruction->update([
                'topic' => $attributes['topic'] ?? $instruction->topic,
                'hazard_assessment_id' => array_key_exists('hazard_assessment_id', $attributes) ? $attributes['hazard_assessment_id'] : $instruction->hazard_assessment_id,
                'training_course_id' => array_key_exists('training_course_id', $attributes) ? $attributes['training_course_id'] : $instruction->training_course_id,
                'training_course_version_id' => array_key_exists('training_course_version_id', $attributes) ? $attributes['training_course_version_id'] : $instruction->training_course_version_id,
                'held_on' => $attributes['held_on'] ?? $instruction->held_on,
                'instructor_user_id' => array_key_exists('instructor_user_id', $attributes) ? $attributes['instructor_user_id'] : $instruction->instructor_user_id,
                'repeat_interval_months' => array_key_exists('repeat_interval_months', $attributes) ? $attributes['repeat_interval_months'] : $instruction->repeat_interval_months,
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $instruction->notes,
            ]);
            $instruction->refresh();

            if ($participantUserIds !== null) {
                $this->syncParticipants($instruction, $participantUserIds);
            }

            // Datum/Intervall geändert → Fälligkeit aller Teilnehmer nachziehen.
            $nextDue = $instruction->nextDueOn()?->toDateString();
            $instruction->participants()->update(['next_due_on' => $nextDue]);
            // Feature 145: Nachweise/Entfernungen ins Trainings-Soll spiegeln.
            $this->training->applyInstruction($instruction->refresh());

            return $instruction->refresh();
        });
    }

    /**
     * Teilnahme bestätigen — nur die Person selbst, nur einmal. Beim
     * Bestätigungs-Klick werden IP und Zeitpunkt festgehalten; eine
     * gezeichnete Unterschrift kommt als Bildpfad (Datenmodell, kein UI im MVP).
     */
    public function sign(
        SafetyInstructionParticipant $participant,
        User $actor,
        InstructionSignatureMethod $method = InstructionSignatureMethod::Confirmed,
        ?string $ip = null,
        ?string $signatureImagePath = null,
    ): SafetyInstructionParticipant {
        if ((int) $participant->user_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'participant' => (string) __('safety.register.error.sign_only_self'),
            ]);
        }
        if ($participant->isSigned()) {
            throw ValidationException::withMessages([
                'participant' => (string) __('safety.register.error.already_signed'),
            ]);
        }

        $instruction = $participant->instruction()->firstOrFail();
        $signedAt = Carbon::now();

        $participant->update([
            'signer_name' => $actor->name,
            'signed_at' => $signedAt,
            'method' => $method->value,
            'signature_image_path' => $signatureImagePath,
            'ip' => $ip,
            'hash' => CryptoHelper::hash(implode('|', [
                (string) $instruction->id,
                $instruction->topic,
                $instruction->held_on->toDateString(),
                (string) $actor->id,
                $actor->name,
                $signedAt->toIso8601String(),
            ])),
        ]);

        $participant->audit('safety.instruction_participant.signed', [
            'actor_user_id' => $actor->id,
            'method' => $method->value,
        ]);

        return $participant->refresh();
    }

    /** Unterweisungen mit signierten Nachweisen sind nicht löschbar. */
    public function delete(SafetyInstruction $instruction): void {
        if ($instruction->participants()->signed()->exists()) {
            throw ValidationException::withMessages([
                'instruction' => (string) __('safety.register.error.delete_with_signatures'),
            ]);
        }

        DB::transaction(function () use ($instruction): void {
            // Feature 145: ohne Nachweis ist das Trainings-Soll wieder offen.
            $this->training->releaseInstruction($instruction);
            $instruction->participants()->delete();
            $instruction->delete();
        });
    }

    /**
     * Teilnehmer abgleichen: neue anlegen (mit Fälligkeit), nicht mehr
     * gelistete UNSIGNIERTE entfernen — signierte bleiben als Nachweis.
     *
     * @param  list<int>  $userIds
     */
    private function syncParticipants(SafetyInstruction $instruction, array $userIds): void {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $nextDue = $instruction->nextDueOn()?->toDateString();

        $existing = $instruction->participants()->get()->keyBy('user_id');

        foreach ($userIds as $userId) {
            if ($existing->has($userId)) {
                continue;
            }
            $instruction->participants()->create([
                'organization_id' => $instruction->organization_id,
                'user_id' => $userId,
                'next_due_on' => $nextDue,
            ]);
        }

        $instruction->participants()
            ->whereNull('signed_at')
            ->whereNotIn('user_id', $userIds)
            ->get()
            ->each(fn(SafetyInstructionParticipant $participant) => $participant->delete());
    }
}
