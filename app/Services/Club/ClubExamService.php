<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubExamService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubEventKind, ClubExamCandidateStatus, ClubGradeSource, ClubParticipationSource};
use App\Models\Club\{ClubAttendanceRecord, ClubExamCandidate, ClubExamOffer, ClubGrade, ClubGradeRequirement, ClubMember};
use App\Models\{Event, Organization, User};
use App\Services\Concerns\AssertsStatusTransition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prüfungen und Gradvergabe (Feature 159, MVP-847) — einzige Schreibstelle.
 * Ein Angebot friert die Regelversion ein; Kandidaten tragen den
 * Zulassungsbericht als Schnappschuss und die verwendeten Nachweise. Nur ein
 * bestandenes Ergebnis vergibt den Zielgrad, transaktional und genau einmal;
 * Fehlversuche ändern nichts. Verschiebung und Nachweiskorrektur markieren
 * betroffene Kandidaten zur fachlichen Überprüfung, löschen aber nichts.
 */
class ClubExamService {
    use AssertsStatusTransition;

    public function __construct(
        private readonly ClubEventService $events,
        private readonly ClubGradingService $grading,
        private readonly ClubEligibilityService $eligibility,
    ) {}

    // ── Angebote ─────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data  Termindaten (ClubEventService::create) + club_grading_system_id, target_grade_ids, examiner_user_ids, notes
     */
    public function createOffer(Organization $organization, User $actor, array $data): ClubExamOffer {
        return DB::transaction(function () use ($organization, $actor, $data): ClubExamOffer {
            $system = $this->systemOf($organization, (int) ($data['club_grading_system_id'] ?? 0));
            $version = $system->activeVersion();
            if ($version === null) {
                throw ValidationException::withMessages(['club_grading_system_id' => __('club.exams.error.no_active_version')]);
            }
            $event = $this->events->create($organization, $actor, ['kind' => ClubEventKind::Exam->value, 'discipline' => $system->discipline] + $data);
            $offer = ClubExamOffer::query()->create([
                'organization_id' => $organization->id,
                'event_id' => $event->id,
                'club_grading_system_id' => $system->id,
                'club_grading_version_id' => $version->id,
                'examiner_user_ids' => $this->examinerIds($organization, $data),
                'notes' => $this->nullableString($data['notes'] ?? null),
            ]);
            $offer->targetGrades()->sync($this->gradeIds($system, $data));
            $offer->audit('club.exam.offerCreated', ['version_no' => $version->version_no]);

            return $offer;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateOffer(ClubExamOffer $offer, User $actor, array $data): ClubExamOffer {
        return DB::transaction(function () use ($offer, $actor, $data): ClubExamOffer {
            /** @var Event $event */
            $event = $offer->event()->firstOrFail();
            /** @var Organization $organization */
            $organization = Organization::query()->findOrFail($offer->organization_id);
            $system = $offer->system()->firstOrFail();
            $this->events->update($event, $actor, ['kind' => ClubEventKind::Exam->value, 'discipline' => $system->discipline] + $data);
            $offer->update([
                'examiner_user_ids' => $this->examinerIds($organization, $data),
                'notes' => $this->nullableString($data['notes'] ?? null),
            ]);
            $offer->targetGrades()->sync($this->gradeIds($system, $data));
            $offer->audit('club.exam.offerUpdated');

            return $offer->refresh();
        });
    }

    // ── Kandidaten ───────────────────────────────────────────────────────

    /**
     * Kandidat anlegen (Leitung) oder anfragen (Mitglied/Vertretung). Bei
     * Selbstanfrage wird sofort geprüft: erfüllte Voraussetzungen führen zur
     * Zulassung mit Platz, fehlende bleiben eine Anfrage ohne Platz.
     */
    public function addCandidate(ClubExamOffer $offer, ClubMember $member, ClubGrade $targetGrade, User $actor, bool $selfService = false): ClubExamCandidate {
        return DB::transaction(function () use ($offer, $member, $targetGrade, $actor, $selfService): ClubExamCandidate {
            if ($member->organization_id !== $offer->organization_id) {
                throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
            }
            if (! $offer->targetGrades()->whereKey($targetGrade->id)->exists()) {
                throw ValidationException::withMessages(['target_grade_id' => __('club.exams.error.grade_not_offered')]);
            }
            /** @var ClubExamCandidate|null $existing */
            $existing = $offer->candidates()->where('club_member_id', $member->id)->first();
            if ($existing !== null) {
                if ($existing->status->isOpen() || $existing->status->isResult()) {
                    return $existing;
                }
                $this->assertStatusTransition($existing->status, ClubExamCandidateStatus::Requested);
                $existing->update(['status' => ClubExamCandidateStatus::Requested->value, 'target_grade_id' => $targetGrade->id, 'requested_at' => now(), 'requested_by_user_id' => $actor->id, 'review_required_at' => null, 'review_note' => null]);
                $candidate = $existing->refresh();
            } else {
                $candidate = ClubExamCandidate::query()->create([
                    'organization_id' => $offer->organization_id,
                    'club_exam_offer_id' => $offer->id,
                    'club_member_id' => $member->id,
                    'target_grade_id' => $targetGrade->id,
                    'status' => ClubExamCandidateStatus::Requested->value,
                    'requested_at' => now(),
                    'requested_by_user_id' => $actor->id,
                ]);
            }
            $candidate->audit('club.exam.candidateRequested', ['self' => $selfService, 'grade' => $targetGrade->name]);
            $report = $this->check($candidate);

            if ($selfService && $report->met) {
                $this->admit($candidate, $actor, ClubParticipationSource::Self);
            }

            return $candidate->refresh();
        });
    }

    /** Zulassungsprüfung zum Prüfungsbeginn; Bericht und verwendete Nachweise werden am Kandidaten festgehalten. */
    public function check(ClubExamCandidate $candidate): ClubEligibilityReport {
        $offer = $candidate->offer()->with(['event', 'version'])->firstOrFail();
        /** @var Event $event */
        $event = $offer->event;
        $requirement = $this->requirementFor($offer, $candidate->target_grade_id);
        /** @var ClubMember $member */
        $member = $candidate->member()->firstOrFail();
        $report = $this->eligibility->evaluate($member, $requirement, CarbonImmutable::instance($event->started_at), $candidate->approved_at !== null);

        $candidate->update([
            'eligibility_met' => $report->met,
            'eligibility_report' => [
                'exam_day' => $report->examDay->toDateString(),
                'counting_from' => $report->countingFrom?->toDateString(),
                'minutes' => $report->minutes,
                'sessions' => $report->sessions,
                'required_minutes' => $report->requiredMinutes,
                'items' => $report->items,
            ],
            'checked_at' => now(),
        ]);
        $candidate->usedRecords()->sync($report->usedRecordIds);

        return $report;
    }

    /** Fachliche Freigabe der Leitung (nur relevant, wenn die Voraussetzung sie verlangt). */
    public function approve(ClubExamCandidate $candidate, User $actor): ClubExamCandidate {
        $candidate->update(['approved_at' => now(), 'approved_by_user_id' => $actor->id]);
        $candidate->audit('club.exam.candidateApproved');
        $this->check($candidate);

        return $candidate->refresh();
    }

    /**
     * Zulassung: Voraussetzungen erfüllt oder Ausnahme (nur wenn die Regel sie
     * erlaubt, mit Begründung). Belegt den Platz am Termin; Zulassung und
     * Platzstatus bleiben getrennte Angaben.
     */
    public function admit(ClubExamCandidate $candidate, User $actor, ClubParticipationSource $source = ClubParticipationSource::Leader, ?string $exceptionReason = null): ClubExamCandidate {
        return DB::transaction(function () use ($candidate, $actor, $source, $exceptionReason): ClubExamCandidate {
            $this->assertStatusTransition($candidate->status, ClubExamCandidateStatus::Admitted);
            $report = $this->check($candidate);
            $exceptionReason = $this->nullableString($exceptionReason);
            if (! $report->met) {
                $requirement = $this->requirementFor($candidate->offer()->firstOrFail(), $candidate->target_grade_id);
                if ($exceptionReason === null) {
                    throw ValidationException::withMessages(['exception_reason' => __('club.exams.error.not_eligible')]);
                }
                if (! $requirement->allows_exception) {
                    throw ValidationException::withMessages(['exception_reason' => __('club.exams.error.exception_forbidden')]);
                }
            }

            $offer = $candidate->offer()->with('event')->firstOrFail();
            /** @var Event $event */
            $event = $offer->event;
            /** @var ClubMember $member */
            $member = $candidate->member()->firstOrFail();
            $this->events->register($event, $member, $actor, $source, null, true);

            $candidate->update([
                'status' => ClubExamCandidateStatus::Admitted->value,
                'admitted_at' => now(),
                'admitted_by_user_id' => $actor->id,
                'exception_reason' => $report->met ? null : $exceptionReason,
                'exception_by_user_id' => $report->met ? null : $actor->id,
                'review_required_at' => null,
                'review_note' => null,
            ]);
            $candidate->audit($report->met ? 'club.exam.candidateAdmitted' : 'club.exam.candidateAdmittedByException', ['reason' => $exceptionReason]);

            return $candidate->refresh();
        });
    }

    public function reject(ClubExamCandidate $candidate, User $actor, ?string $note = null): ClubExamCandidate {
        return $this->close($candidate, ClubExamCandidateStatus::Rejected, $actor, $note, 'club.exam.candidateRejected');
    }

    public function withdraw(ClubExamCandidate $candidate, User $actor, ?string $note = null): ClubExamCandidate {
        return $this->close($candidate, ClubExamCandidateStatus::Withdrawn, $actor, $note, 'club.exam.candidateWithdrawn');
    }

    /**
     * Ergebnis: nur „bestanden“ vergibt den Zielgrad, transaktional und genau
     * einmal; Fehlversuch und Nichtantritt ändern weder Grad noch Trainingszeit.
     * Der Bericht wird mit dem Ergebnis eingefroren.
     */
    public function recordResult(ClubExamCandidate $candidate, ClubExamCandidateStatus $result, User $actor, ?string $note = null): ClubExamCandidate {
        if (! $result->isResult()) {
            throw new \InvalidArgumentException('Kein Prüfungsergebnis: ' . $result->value);
        }

        return DB::transaction(function () use ($candidate, $result, $actor, $note): ClubExamCandidate {
            /** @var ClubExamCandidate $candidate */
            $candidate = ClubExamCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if ($candidate->status === $result) {
                return $candidate;
            }
            $this->assertStatusTransition($candidate->status, $result);
            $this->check($candidate);

            $awardedId = null;
            if ($result === ClubExamCandidateStatus::Passed) {
                $offer = $candidate->offer()->with('event')->firstOrFail();
                /** @var Event $event */
                $event = $offer->event;
                /** @var ClubMember $member */
                $member = $candidate->member()->firstOrFail();
                /** @var ClubGrade $grade */
                $grade = $candidate->targetGrade()->firstOrFail();
                $awarded = $this->grading->awardGrade($member, $grade, $this->events->localDay($event), ClubGradeSource::Exam, $actor, (string) __('club.exams.label.evidence', ['title' => $event->title]), $candidate->id);
                $awardedId = $awarded->id;
            }

            $candidate->update([
                'status' => $result->value,
                'result_recorded_at' => now(),
                'result_by_user_id' => $actor->id,
                'result_note' => $this->nullableString($note),
                'awarded_member_grade_id' => $awardedId,
                'review_required_at' => null,
            ]);
            $candidate->audit('club.exam.resultRecorded', ['result' => $result->value, 'awarded' => $awardedId !== null]);

            return $candidate->refresh();
        });
    }

    /**
     * Nach Verschiebung: alle offenen Kandidaten neu prüfen; wer die
     * Voraussetzungen am neuen Termin nicht mehr erfüllt (und keine Ausnahme
     * hat), wird zur fachlichen Überprüfung markiert — nicht still entfernt.
     */
    public function recheckOffer(ClubExamOffer $offer, string $reason): int {
        $flagged = 0;
        foreach ($offer->candidates()->get() as $candidate) {
            if (! $candidate->status->isOpen()) {
                continue;
            }
            $report = $this->check($candidate);
            if (! $report->met && ! $candidate->hasException()) {
                $candidate->update(['review_required_at' => now(), 'review_note' => $reason]);
                $candidate->audit('club.exam.reviewRequired', ['reason' => $reason]);
                $flagged++;
            }
        }
        $offer->audit('club.exam.rechecked', ['reason' => $reason, 'flagged' => $flagged]);

        return $flagged;
    }

    /** Termin der Art Prüfung verschoben → Kandidaten neu prüfen (Aufruf aus dem Termin-Service). */
    public function recheckForEvent(Event $event): void {
        $offer = ClubExamOffer::query()->where('event_id', $event->id)->first();
        if ($offer !== null) {
            $this->recheckOffer($offer, (string) __('club.exams.label.review_rescheduled'));
        }
    }

    /** Korrigierter Nachweis → betroffene Kandidaten zur Überprüfung markieren; erteilte Grade bleiben. */
    public function flagReviewForRecord(ClubAttendanceRecord $record): int {
        $flagged = 0;
        $candidates = ClubExamCandidate::query()
            ->whereHas('usedRecords', fn($q) => $q->where('club_attendance_records.id', $record->id))
            ->get();
        foreach ($candidates as $candidate) {
            $candidate->update(['review_required_at' => now(), 'review_note' => (string) __('club.exams.label.review_record_corrected')]);
            $candidate->audit('club.exam.reviewRequired', ['record_id' => $record->id]);
            $flagged++;
        }

        return $flagged;
    }

    public function clearReview(ClubExamCandidate $candidate, User $actor, string $note): ClubExamCandidate {
        $candidate->update(['review_required_at' => null, 'review_note' => null]);
        $candidate->audit('club.exam.reviewCleared', ['note' => $note, 'actor_id' => $actor->id]);

        return $candidate->refresh();
    }

    public function requirementFor(ClubExamOffer $offer, int $gradeId): ClubGradeRequirement {
        /** @var ClubGradeRequirement|null $requirement */
        $requirement = ClubGradeRequirement::query()
            ->where('club_grading_version_id', $offer->club_grading_version_id)
            ->where('club_grade_id', $gradeId)
            ->with(['version.system', 'grade', 'previousGrade'])
            ->first();
        if ($requirement === null) {
            throw ValidationException::withMessages(['target_grade_id' => __('club.exams.error.no_requirement')]);
        }

        return $requirement;
    }

    private function close(ClubExamCandidate $candidate, ClubExamCandidateStatus $status, User $actor, ?string $note, string $auditKey): ClubExamCandidate {
        return DB::transaction(function () use ($candidate, $status, $actor, $note, $auditKey): ClubExamCandidate {
            $this->assertStatusTransition($candidate->status, $status);
            if ($candidate->status === ClubExamCandidateStatus::Admitted) {
                $offer = $candidate->offer()->with('event')->firstOrFail();
                /** @var Event $event */
                $event = $offer->event;
                /** @var ClubMember $member */
                $member = $candidate->member()->firstOrFail();
                $this->events->cancelRegistration($event, $member, $actor, true, $this->nullableString($note));
            }
            $candidate->update(['status' => $status->value, 'result_note' => $this->nullableString($note)]);
            $candidate->audit($auditKey, ['note' => $note, 'actor_id' => $actor->id]);

            return $candidate->refresh();
        });
    }

    private function systemOf(Organization $organization, int $systemId): \App\Models\Club\ClubGradingSystem {
        $system = \App\Models\Club\ClubGradingSystem::query()->whereKey($systemId)->where('organization_id', $organization->id)->first();
        if ($system === null) {
            throw ValidationException::withMessages(['club_grading_system_id' => __('club.grading.error.grade_foreign')]);
        }

        return $system;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function gradeIds(\App\Models\Club\ClubGradingSystem $system, array $data): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($data['target_grade_ids'] ?? [])))));
        if ($ids === []) {
            throw ValidationException::withMessages(['target_grade_ids' => __('club.exams.error.grades_required')]);
        }
        $valid = ClubGrade::query()->whereIn('id', $ids)->where('club_grading_system_id', $system->id)->count();
        if ($valid !== count($ids)) {
            throw ValidationException::withMessages(['target_grade_ids' => __('club.grading.error.grade_foreign')]);
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function examinerIds(Organization $organization, array $data): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($data['examiner_user_ids'] ?? [])))));
        if ($ids === []) {
            return [];
        }

        return array_values(array_map('intval', User::query()->whereIn('id', $ids)->where('organization_id', $organization->id)->pluck('id')->all()));
    }

    private function nullableString(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
