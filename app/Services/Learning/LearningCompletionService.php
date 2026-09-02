<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCompletionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\ExternalParticipant\ExternalParty;
use App\Enums\Numbering\NumberScope;
use App\Enums\Safety\InstructionSignatureMethod;
use App\Models\Learning\{LearningCertificate, LearningEnrollment};
use App\Models\Safety\{SafetyInstruction, SafetyInstructionParticipant};
use App\Models\Supplier\{SupplierCredential, SupplierCredentialType};
use App\Models\{Supplier, UserQualification};
use App\Services\Numbering\NumberSequenceService;
use App\Services\Safety\SafetyInstructionService;
use App\Services\Training\TrainingAssignmentService;
use App\Support\Query\DateRange;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;

/**
 * Rückfluss eines Kursabschlusses (Feature 149, MVP-740) — **die einzige
 * Stelle**, an der ein Abschluss Wirkung außerhalb der Lernplattform
 * entfaltet.
 *
 * Vier Wirkungen, alle über die vorhandenen Wege:
 *  1. **Zertifikat** (dieses Modul) mit lückenloser Nummer und Prüfcode.
 *  2. **Unterweisungsnachweis** im Arbeitsschutz-Register (Feature 132) —
 *     erzeugt über den regulären `SafetyInstructionService`, inklusive
 *     Bestätigung durch die Person selbst und Hash. Keine Kopie, keine
 *     zweite Signaturlogik.
 *  3. **Soll erfüllt** in Feature 145 über
 *     `TrainingAssignmentService::applyInstruction()` — derselbe Weg wie
 *     bei einer Präsenzunterweisung.
 *  4. **Qualifikation** (Feature 013) angelegt oder verlängert. Die
 *     Sperrwirkung bleibt dort; hier entsteht kein zweiter Guard.
 *
 * Externe Lernende ohne Benutzerkonto bekommen ein Zertifikat, aber weder
 * Unterweisungsnachweis noch Qualifikation — beides setzt ein
 * Beschäftigungsverhältnis voraus.
 */
class LearningCompletionService {
    /** Nachweisart für gespiegelte Schulungszertifikate (Feature 117). */
    public const SUPPLIER_CREDENTIAL_CODE = 'lms-schulung';

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly SafetyInstructionService $instructions,
        private readonly TrainingAssignmentService $trainingAssignments,
        private readonly LearningCompetencyService $competencies,
        private readonly LearningSurveyTrigger $survey,
    ) {}

    /**
     * @return array{certificate: LearningCertificate|null, instruction: SafetyInstruction|null, qualification: UserQualification|null}
     */
    public function apply(LearningEnrollment $enrollment, ?Carbon $now = null): array {
        $now ??= Carbon::now();
        $course = $enrollment->course;

        if ($course === null || $enrollment->completed_at === null) {
            return ['certificate' => null, 'instruction' => null, 'qualification' => null];
        }

        return DB::transaction(function () use ($enrollment, $course, $now): array {
            $certificate = $course->certificate_enabled ? $this->issueCertificate($enrollment, $now) : null;
            $instruction = $course->creates_instruction_proof ? $this->recordInstructionProof($enrollment, $now) : null;
            $qualification = $course->qualification_id !== null ? $this->grantQualification($enrollment, $now) : null;

            // Subunternehmer-Nachweis (Konzept 11 Nr. 6): schließt eine
            // externe Person eines Subunternehmers ab, erscheint der
            // Nachweis in dessen Pflichtnachweisen (Feature 117) — dort
            // wirkt er, nicht im LMS.
            if ($certificate !== null) {
                $this->mirrorToSupplierCredential($enrollment, $certificate);
            }

            // Kompetenzstufe belegen (MVP-745) — sie sperrt nichts, sie
            // zeigt Lücken; die Sperrwirkung bleibt bei der Qualifikation.
            $this->competencies->grantFromCourse($enrollment, $now);

            // Kursfeedback (MVP-747) über die vorhandene Umfrage-Engine —
            // mit deren Anonymität und Ermüdungsschutz.
            $this->survey->onCourseCompleted($enrollment);

            return [
                'certificate' => $certificate,
                'instruction' => $instruction,
                'qualification' => $qualification,
            ];
        });
    }

    /**
     * Zertifikat einer externen Person als Pflichtnachweis des
     * Subunternehmers spiegeln (Feature 117).
     *
     * **Nur bei Subunternehmern und nur mit Lieferantenbezug** — ein
     * Prüfer oder Gutachter hat keine Pflichtnachweisakte, und ohne
     * Lieferanten gäbe es keinen Ort für den Nachweis.
     *
     * Die Sperrwirkung bleibt bei 117: das LMS legt den Nachweis ab, es
     * entscheidet nicht über Zutritt.
     */
    private function mirrorToSupplierCredential(LearningEnrollment $enrollment, LearningCertificate $certificate): ?SupplierCredential {
        $participant = $enrollment->externalParticipant;

        if ($participant === null || $participant->party !== ExternalParty::Subcontractor) {
            return null;
        }

        if ($participant->subject_type !== (new Supplier())->getMorphClass()) {
            return null;
        }

        $type = SupplierCredentialType::query()->firstOrCreate(
            [
                'organization_id' => $enrollment->organization_id,
                'code' => self::SUPPLIER_CREDENTIAL_CODE,
            ],
            [
                'name' => (string) __('learning.proof.supplier_credential_type'),
                'default_validity_months' => $enrollment->course?->validity_months,
                'is_required_default' => false,
                'is_active' => true,
            ]
        );

        return SupplierCredential::query()->updateOrCreate(
            [
                'organization_id' => $enrollment->organization_id,
                'supplier_id' => $participant->subject_id,
                'supplier_credential_type_id' => $type->id,
                'reference' => $certificate->number,
            ],
            [
                'issuer' => $enrollment->organization?->name,
                'issued_on' => $certificate->issued_on,
                'valid_until' => $certificate->valid_until,
                'note' => (string) __('learning.proof.supplier_credential_note', [
                    'course' => $enrollment->course->title ?? '',
                    'name' => $participant->name,
                ]),
            ]
        );
    }

    /** Zertifikat ausstellen — höchstens eines je Einschreibung. */
    public function issueCertificate(LearningEnrollment $enrollment, ?Carbon $now = null): LearningCertificate {
        $now ??= Carbon::now();
        $course = $enrollment->course;

        $existing = LearningCertificate::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->whereNull('revoked_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $validityMonths = $course->validity_months ?? $course?->trainingCourse?->validity_months;

        return LearningCertificate::query()->create([
            'organization_id' => $enrollment->organization_id,
            'learning_enrollment_id' => $enrollment->id,
            'learning_course_id' => $enrollment->learning_course_id,
            'learning_course_version_id' => $enrollment->learning_course_version_id,
            'user_id' => $enrollment->user_id,
            'external_participant_id' => $enrollment->external_participant_id,
            'number' => $this->numbers->next($enrollment->organization_id, NumberScope::Certificate, $now),
            // 32 Zeichen aus dem sicheren Zufall — nicht ableitbar aus Nummer
            // oder Person, sonst wäre die Prüfseite eine Aufzählung.
            'verification_code' => Str::lower(Str::random(32)),
            'holder_name' => $enrollment->learnerName(),
            'issued_on' => $now->toDateString(),
            'valid_until' => $validityMonths !== null
                ? $now->copy()->addMonths($validityMonths)->toDateString()
                : null,
            'score_percent' => $enrollment->score_percent,
        ]);
    }

    public function revoke(LearningCertificate $certificate, string $reason, ?int $actorUserId = null, ?Carbon $now = null): LearningCertificate {
        $certificate->update([
            'revoked_at' => $now ?? Carbon::now(),
            'revoked_reason' => $reason,
            'revoked_by_user_id' => $actorUserId,
        ]);

        return $certificate->refresh();
    }

    /**
     * Unterweisungsnachweis im Register (132). Je Kurs, Kursversion und Tag
     * entsteht **eine** Unterweisung, in der sich die Teilnehmenden
     * sammeln — so, wie eine Präsenzunterweisung geführt würde.
     */
    private function recordInstructionProof(LearningEnrollment $enrollment, Carbon $now): ?SafetyInstruction {
        $user = $enrollment->user;
        $course = $enrollment->course;

        if ($user === null || $course === null) {
            return null;
        }

        $version = $enrollment->courseVersion;
        $instruction = SafetyInstruction::query()
            ->where('organization_id', $enrollment->organization_id)
            ->where('training_course_id', $course->training_course_id)
            ->where('training_course_version_id', $version?->training_course_version_id)
            // Geräteeinweisung: Nachweise verschiedener Geräte sind
            // verschiedene Unterweisungen, auch am selben Tag.
            ->where('asset_id', $course->asset_id)
            // Tagesbereich statt whereDate() (Index) und statt `=` gegen
            // `Y-m-d` (SQLite speichert den date-Cast mit Zeitanteil).
            ->whereBetween('held_on', DateRange::days($now, $now))
            ->first();

        $creator = $course->owner ?? $user;
        $organization = $enrollment->organization;

        if ($organization === null) {
            return null;
        }

        if ($instruction === null) {
            $instruction = $this->instructions->create(
                $organization,
                $creator,
                [
                    'topic' => $course->title,
                    'held_on' => $now->toDateString(),
                    'instructor_user_id' => $course->owner_user_id,
                    'training_course_id' => $course->training_course_id,
                    'training_course_version_id' => $version?->training_course_version_id,
                    'asset_id' => $course->asset_id,
                    'repeat_interval_months' => $course->validity_months,
                    'notes' => (string) __('learning.proof.note', ['course' => $course->title]),
                ],
                [$user->id],
            );
        }

        $participant = SafetyInstructionParticipant::query()
            ->where('safety_instruction_id', $instruction->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant === null) {
            $participant = SafetyInstructionParticipant::query()->create([
                'organization_id' => $enrollment->organization_id,
                'safety_instruction_id' => $instruction->id,
                'user_id' => $user->id,
            ]);
        }

        // Die Bestätigung leistet die Person selbst — der Kursabschluss IST
        // ihre Handlung. Signatur und Hash baut der reguläre Service.
        if ($participant->signed_at === null) {
            $this->instructions->sign($participant, $user, InstructionSignatureMethod::Confirmed);
        }

        // Erfüllt das Soll aus Feature 145 — derselbe Weg wie bei einer
        // Präsenzunterweisung.
        $this->trainingAssignments->applyInstruction($instruction->refresh());

        return $instruction;
    }

    /**
     * Qualifikation anlegen oder verlängern (013). Bei mehreren Abschlüssen
     * gewinnt die spätere Gültigkeit — eine Verlängerung darf nie kürzen.
     */
    private function grantQualification(LearningEnrollment $enrollment, Carbon $now): ?UserQualification {
        $user = $enrollment->user;
        $course = $enrollment->course;

        if ($user === null || $course?->qualification_id === null) {
            return null;
        }

        $validUntil = $course->validity_months !== null
            ? $now->copy()->addMonths($course->validity_months)->toDateString()
            : null;

        $existing = UserQualification::query()
            ->where('user_id', $user->id)
            ->where('qualification_id', $course->qualification_id)
            ->first();

        if ($existing === null) {
            return UserQualification::query()->create([
                'user_id' => $user->id,
                'qualification_id' => $course->qualification_id,
                'valid_from' => $now->toDateString(),
                'valid_until' => $validUntil,
            ]);
        }

        $keepLonger = $existing->valid_until !== null
            && $validUntil !== null
            && $existing->valid_until->greaterThan(Carbon::parse($validUntil));

        $existing->update([
            'valid_from' => $existing->valid_from ?? $now->toDateString(),
            'valid_until' => $keepLonger ? $existing->valid_until->toDateString() : $validUntil,
        ]);

        return $existing->refresh();
    }
}
