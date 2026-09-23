<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubExamTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubEventVisibility, ClubExamCandidateStatus, ClubGradeSource, ClubParticipationStatus};
use App\Enums\User\UserRole;
use App\Models\Calendar\Event;
use App\Models\Club\{ClubAttendanceRecord, ClubEventParticipation, ClubExamCandidate, ClubExamOffer, ClubGrade, ClubGradingSystem, ClubGradingVersion, ClubGroup, ClubMember, ClubMemberGrade};
use App\Models\Platform\User;
use App\Services\Club\{ClubAttendanceService, ClubEventService, ClubExamService, ClubGradingService, ClubGroupService, ClubMemberService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Prüfungen und Gradvergabe (Feature 159, MVP-847): bestandene Prüfung
 * vergibt genau einen Grad, Fehlversuch keinen; Verschiebung prüft neu;
 * Nachweiskorrektur bleibt nachvollziehbar; Selbstanfrage, Ausnahme,
 * Bescheinigung, Rechte.
 */
class ClubExamTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private ClubGradingSystem $judo;

    private ClubGrade $white;

    private ClubGrade $yellow;

    private ClubGradingVersion $version;

    private ClubGroup $group;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->grading()->setEnabled($this->organization, true);
        $this->judo = $this->grading()->createSystem($this->organization, ['name' => 'Judo-Ordnung', 'discipline' => 'Judo']);
        $this->white = $this->grading()->createGrade($this->judo, ['name' => 'Weiß', 'rank' => 0]);
        $this->yellow = $this->grading()->createGrade($this->judo, ['name' => 'Gelb', 'rank' => 1]);
        $this->version = $this->grading()->activateVersion($this->grading()->createVersion($this->judo), $this->admin);
        $this->grading()->saveRequirement($this->version, $this->yellow, ['previous_grade_id' => $this->white->id, 'min_minutes' => 150, 'wait_months' => 1, 'counting_basis' => 'since_previous_grade', 'allows_exception' => true]);
        $this->group = ClubGroup::factory()->create(['name' => 'Judo', 'discipline' => 'Judo']);
    }

    private function grading(): ClubGradingService {
        return app(ClubGradingService::class);
    }

    private function exams(): ClubExamService {
        return app(ClubExamService::class);
    }

    private function member(): ClubMember {
        $member = ClubMember::factory()->aged(20)->create(['joined_on' => '2026-01-01']);
        app(ClubGroupService::class)->admit($this->group, $member, CarbonImmutable::parse('2026-01-01'), $this->admin);
        $this->grading()->recognizeGrade($member, $this->white, CarbonImmutable::parse('2026-03-01'), null, $this->admin);

        return $member;
    }

    private function training(ClubMember $member, string $start, int $minutes = 90): ClubAttendanceRecord {
        $begin = CarbonImmutable::parse($start, 'Europe/Berlin');
        $event = app(ClubEventService::class)->create($this->organization, $this->admin, [
            'title' => 'Training', 'kind' => 'training', 'visibility' => ClubEventVisibility::Groups->value, 'club_group_ids' => [$this->group->id],
            'started_at' => $begin->utc()->format('Y-m-d H:i:s'), 'ended_at' => $begin->addMinutes($minutes)->utc()->format('Y-m-d H:i:s'), 'timezone' => 'Europe/Berlin',
        ]);
        $attendance = app(ClubAttendanceService::class);
        $sheet = $attendance->sheetFor($event);
        $sheet = $attendance->saveRows($sheet, [$member->id => ['status' => 'present']], $this->admin, $sheet->version);
        $attendance->confirm($sheet, $this->admin, $sheet->version);

        return ClubAttendanceRecord::query()->where('event_id', $event->id)->where('club_member_id', $member->id)->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function offer(?CarbonImmutable $start = null, array $overrides = []): ClubExamOffer {
        $start ??= CarbonImmutable::now('Europe/Berlin')->addDays(10)->setTime(10, 0);

        return $this->exams()->createOffer($this->organization, $this->admin, array_merge([
            'title' => 'Gürtelprüfung', 'visibility' => ClubEventVisibility::Groups->value, 'club_group_ids' => [$this->group->id],
            'started_at' => $start->utc()->format('Y-m-d H:i:s'), 'ended_at' => $start->addHours(3)->utc()->format('Y-m-d H:i:s'), 'timezone' => 'Europe/Berlin',
            'club_grading_system_id' => $this->judo->id, 'target_grade_ids' => [$this->yellow->id], 'examiner_user_ids' => [],
        ], $overrides));
    }

    public function test_passed_exam_awards_exactly_one_grade_and_failure_none(): void {
        $offer = $this->offer();
        $this->assertSame($this->version->id, $offer->club_grading_version_id, 'Aktive Regelversion eingefroren.');
        $passer = $this->member();
        $loser = $this->member();
        $absent = $this->member();
        foreach ([$passer, $loser, $absent] as $member) {
            $this->training($member, '2026-05-01 18:00', 90);
            $this->training($member, '2026-05-08 18:00', 90);
        }

        $candidates = [];
        foreach ([$passer, $loser, $absent] as $member) {
            $candidate = $this->exams()->addCandidate($offer, $member, $this->yellow, $this->admin);
            $this->assertTrue($candidate->eligibility_met);
            $this->assertSame(2, $candidate->usedRecords()->count(), 'Verwendete Nachweise bleiben referenziert.');
            $candidates[$member->id] = $this->exams()->admit($candidate, $this->admin);
            $this->assertSame(ClubExamCandidateStatus::Admitted, $candidates[$member->id]->status);
            $this->assertSame(ClubParticipationStatus::Registered, ClubEventParticipation::query()->where('event_id', $offer->event_id)->where('club_member_id', $member->id)->firstOrFail()->status, 'Zulassung belegt den Platz.');
        }

        $passed = $this->exams()->recordResult($candidates[$passer->id], ClubExamCandidateStatus::Passed, $this->admin);
        $passed = $this->exams()->recordResult($passed, ClubExamCandidateStatus::Passed, $this->admin);
        $this->assertSame(1, ClubMemberGrade::query()->where('club_member_id', $passer->id)->where('club_grade_id', $this->yellow->id)->count(), 'Genau ein Grad, auch bei doppelter Erfassung.');
        $awarded = ClubMemberGrade::query()->where('club_member_id', $passer->id)->where('club_grade_id', $this->yellow->id)->firstOrFail();
        $this->assertSame(ClubGradeSource::Exam, $awarded->source);
        $this->assertSame($passed->id, $awarded->club_exam_candidate_id);
        $this->assertSame($awarded->id, $passed->awarded_member_grade_id);
        $this->assertSame($this->yellow->id, $this->grading()->currentGrade($passer, $this->judo, CarbonImmutable::now()->addDays(11))?->club_grade_id);

        $this->exams()->recordResult($candidates[$loser->id], ClubExamCandidateStatus::Failed, $this->admin, 'Kata unvollständig');
        $this->exams()->recordResult($candidates[$absent->id], ClubExamCandidateStatus::NoShow, $this->admin);
        foreach ([$loser, $absent] as $member) {
            $this->assertSame($this->white->id, $this->grading()->currentGrade($member, $this->judo, CarbonImmutable::now()->addDays(11))?->club_grade_id, 'Fehlversuch vergibt nichts und entfernt nichts.');
            $this->assertSame(180, $this->exams()->check(ClubExamCandidate::query()->where('club_member_id', $member->id)->firstOrFail())->minutes, 'Trainingszeit wird nicht zurückgesetzt.');
        }

        try {
            $this->exams()->recordResult($candidates[$loser->id]->refresh(), ClubExamCandidateStatus::Passed, $this->admin);
            $this->fail('Ein erfasstes Ergebnis ist endgültig.');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_reschedule_rechecks_admission_and_flags_candidates_for_review(): void {
        $member = $this->member();
        $this->training($member, '2026-05-01 18:00', 90);
        $this->training($member, '2026-05-08 18:00', 90);
        $offer = $this->offer(CarbonImmutable::parse('2026-04-15 10:00', 'Europe/Berlin'));
        // Wartefrist ab 01.03. + 1 Monat = 01.04. erfüllt; die Trainings vom Mai liegen nach dem Prüfungstag.
        $candidate = $this->exams()->addCandidate($offer, $member, $this->yellow, $this->admin);
        $this->assertFalse($candidate->eligibility_met, 'Trainings nach dem Prüfungstag zählen nicht.');
        $candidate = $this->exams()->admit($candidate, $this->admin, exceptionReason: 'Sonderfall Vereinswechsel');
        $this->assertTrue($candidate->hasException());

        $later = CarbonImmutable::parse('2026-06-15 10:00', 'Europe/Berlin');
        app(ClubEventService::class)->update($offer->event()->firstOrFail(), $this->admin, [
            'title' => 'Gürtelprüfung', 'kind' => 'exam', 'visibility' => 'groups', 'club_group_ids' => [$this->group->id],
            'started_at' => $later->utc()->format('Y-m-d H:i:s'), 'ended_at' => $later->addHours(3)->utc()->format('Y-m-d H:i:s'), 'timezone' => 'Europe/Berlin',
        ]);
        $candidate->refresh();
        $this->assertTrue($candidate->eligibility_met, 'Zum neuen Stichtag zählen die Mai-Trainings.');
        $this->assertNull($candidate->review_required_at, 'Erfüllt → keine Überprüfungsmarke.');

        $earlier = CarbonImmutable::parse('2026-03-20 10:00', 'Europe/Berlin');
        $plain = $this->exams()->addCandidate($this->offer($later, ['title' => 'Zweite Prüfung']), $this->member(), $this->yellow, $this->admin);
        $this->assertFalse($plain->eligibility_met);
        $this->exams()->admit($plain->refresh(), $this->admin, exceptionReason: 'x');
        $regular = $this->exams()->addCandidate($plain->offer()->firstOrFail(), $member, $this->yellow, $this->admin);
        $regular = $this->exams()->admit($regular, $this->admin);
        $this->assertFalse($regular->hasException());
        app(ClubEventService::class)->update($plain->offer()->firstOrFail()->event()->firstOrFail(), $this->admin, [
            'title' => 'Zweite Prüfung', 'kind' => 'exam', 'visibility' => 'groups', 'club_group_ids' => [$this->group->id],
            'started_at' => $earlier->utc()->format('Y-m-d H:i:s'), 'ended_at' => $earlier->addHours(3)->utc()->format('Y-m-d H:i:s'), 'timezone' => 'Europe/Berlin',
        ]);
        $regular->refresh();
        $this->assertFalse($regular->eligibility_met);
        $this->assertNotNull($regular->review_required_at, 'Verschiebung nach vorn: Wartefrist nicht mehr erfüllt → Überprüfung, keine stille Entfernung.');
        $this->assertSame(ClubExamCandidateStatus::Admitted, $regular->status);
        $this->assertNull($plain->refresh()->review_required_at, 'Ausnahmezulassung wird nicht markiert.');
    }

    public function test_attendance_correction_flags_the_candidate_and_stays_traceable(): void {
        $member = $this->member();
        $record = $this->training($member, '2026-05-01 18:00', 90);
        $this->training($member, '2026-05-08 18:00', 90);
        $offer = $this->offer();
        $candidate = $this->exams()->admit($this->exams()->addCandidate($offer, $member, $this->yellow, $this->admin), $this->admin);
        $this->assertNull($candidate->review_required_at);

        $attendance = app(ClubAttendanceService::class);
        $sheet = $record->sheet()->firstOrFail();
        $attendance->correct($record, ['status' => 'partial', 'minutes' => 30], $this->admin, 'Früher gegangen', $sheet->version);

        $candidate->refresh();
        $this->assertNotNull($candidate->review_required_at, 'Korrigierter Nachweis markiert den Kandidaten.');
        $this->assertSame(1, $record->revisions()->count(), 'Die Korrektur bleibt als Revision nachvollziehbar.');
        $this->assertSame(120, $this->exams()->check($candidate)->minutes);
        $this->assertFalse($candidate->refresh()->eligibility_met);

        $awarded = $this->grading()->recognizeGrade($member, $this->yellow, CarbonImmutable::parse('2026-05-20'), 'Vorher bestanden', $this->admin);
        $attendance->correct($record->refresh(), ['status' => 'present', 'minutes' => 90], $this->admin, 'Doch dabei', $sheet->refresh()->version);
        $this->assertFalse($awarded->refresh()->isRevoked(), 'Nachträgliche Korrekturen löschen keinen erteilten Grad.');
    }

    public function test_member_requests_from_my_club_and_leadership_admits_or_rejects(): void {
        $self = $this->orgUser();
        $eligible = $this->member();
        app(ClubMemberService::class)->update($eligible, ['user_id' => $self->id]);
        $this->training($eligible, '2026-05-01 18:00', 90);
        $this->training($eligible, '2026-05-08 18:00', 90);
        $other = $this->orgUser();
        $novice = $this->member();
        app(ClubMemberService::class)->update($novice, ['user_id' => $other->id]);
        $offer = $this->offer();
        $event = $offer->event()->firstOrFail();

        $this->actingAs($self)->get(route('club.my.index'))->assertOk()->assertSee(route('club.my.exam.request', $event));
        $this->actingAs($self)->post(route('club.my.exam.request', $event))->assertRedirect(route('club.my.index'));
        $mine = ClubExamCandidate::query()->where('club_member_id', $eligible->id)->firstOrFail();
        $this->assertSame(ClubExamCandidateStatus::Admitted, $mine->status, 'Erfüllte Voraussetzungen → Zulassung mit Platz.');
        $this->assertTrue(ClubEventParticipation::query()->where('event_id', $event->id)->where('club_member_id', $eligible->id)->exists());

        $this->actingAs($other)->post(route('club.my.exam.request', $event))->assertRedirect(route('club.my.index'));
        $request = ClubExamCandidate::query()->where('club_member_id', $novice->id)->firstOrFail();
        $this->assertSame(ClubExamCandidateStatus::Requested, $request->status, 'Fehlende Voraussetzungen → Anfrage ohne Platz.');
        $this->assertFalse(ClubEventParticipation::query()->where('event_id', $event->id)->where('club_member_id', $novice->id)->exists());
        $this->actingAs($other)->get(route('club.my.index'))->assertOk()->assertSee(ClubExamCandidateStatus::Requested->label());

        $this->actingAs($this->admin)->post(route('club.exams.candidates.admit', [$offer, $request]))->assertSessionHasErrors('exception_reason');
        $this->actingAs($this->admin)->post(route('club.exams.candidates.admit', [$offer, $request]), ['exception_reason' => 'Nachholprüfung'])->assertRedirect(route('club.exams.show', $offer));
        $this->assertSame(ClubExamCandidateStatus::Admitted, $request->refresh()->status);
        $this->actingAs($this->admin)->post(route('club.exams.candidates.reject', [$offer, $request]), ['note' => 'Zurückgezogen'])->assertRedirect();
        $this->assertSame(ClubExamCandidateStatus::Rejected, $request->refresh()->status);
        $this->assertSame(ClubParticipationStatus::Cancelled, ClubEventParticipation::query()->where('event_id', $event->id)->where('club_member_id', $novice->id)->firstOrFail()->status, 'Ablehnung gibt den Platz frei.');
    }

    public function test_pages_certificate_and_examiner_rights(): void {
        // Gelisteter Prüfer ohne jedes Vereinsrecht; ein Nutzer ohne Recht und ohne Listung bleibt außen vor.
        $examiner = $this->orgUser();
        $otherLead = $this->orgUser();
        $leadWithRight = $this->userWithRole(UserRole::Teamleitung->value);
        $member = $this->member();
        $self = $this->orgUser();
        app(ClubMemberService::class)->update($member, ['user_id' => $self->id]);
        $this->training($member, '2026-05-01 18:00', 90);
        $this->training($member, '2026-05-08 18:00', 90);
        $offer = $this->offer(null, ['examiner_user_ids' => [$examiner->id]]);
        $candidate = $this->exams()->admit($this->exams()->addCandidate($offer, $member, $this->yellow, $this->admin), $this->admin);

        $this->actingAs($this->admin)->get(route('club.exams.index'))->assertOk()->assertSee('Gürtelprüfung');
        $this->actingAs($this->admin)->get(route('club.exams.index', ['period' => 'range']))->assertOk();
        $this->actingAs($this->admin)->get(route('club.exams.show', $offer))->assertOk()->assertSee($member->fullName())->assertSee($examiner->name);
        $this->actingAs($this->admin)->get(route('club.exams.create'))->assertOk()->assertSee('name="target_grade_ids[]"', false);
        $this->actingAs($this->admin)->get(route('club.exams.edit', $offer))->assertOk();
        $this->actingAs($this->admin)->get(route('club.exams.candidates.create', $offer))->assertOk();
        $this->actingAs($this->admin)->get(route('club.exams.candidates.result.edit', [$offer, $candidate]))->assertOk();
        $this->actingAs($this->admin)->get(route('club.members.grading', $member))->assertOk()->assertSee('Gürtelprüfung');

        $this->actingAs($this->admin)->post(route('club.exams.store'), [
            'title' => 'Prüfung Herbst', 'club_grading_system_id' => $this->judo->sqid, 'target_grade_ids' => [$this->yellow->sqid], 'visibility' => 'groups',
            'club_group_ids' => [$this->group->sqid], 'started_at' => '2026-11-10T10:00', 'ended_at' => '2026-11-10T12:00', 'timezone' => 'Europe/Berlin',
        ])->assertRedirect();
        $this->assertSame(2, ClubExamOffer::query()->count());
        $this->assertSame('Prüfung Herbst', Event::query()->latest('id')->firstOrFail()->title);

        // Prüferrecht: gelisteter Prüfer darf, andere Gruppenleitung nicht — Graduierungspflege immer.
        $this->actingAs($otherLead)->get(route('club.exams.index'))->assertForbidden();
        $this->actingAs($otherLead)->post(route('club.exams.candidates.result', [$offer, $candidate]), ['result' => 'passed'])->assertForbidden();
        $this->actingAs($leadWithRight)->get(route('club.exams.show', $offer))->assertOk();
        $this->actingAs($examiner)->get(route('club.exams.show', $offer))->assertOk();
        $this->actingAs($examiner)->post(route('club.exams.candidates.result', [$offer, $candidate]), ['result' => 'passed', 'note' => 'Souverän'])->assertRedirect(route('club.exams.show', $offer));
        $this->assertSame(ClubExamCandidateStatus::Passed, $candidate->refresh()->status);
        $awarded = ClubMemberGrade::query()->where('club_member_id', $member->id)->where('club_grade_id', $this->yellow->id)->firstOrFail();

        $pdf = $this->actingAs($this->admin)->get(route('club.members.grades.certificate', [$member, $awarded]));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->actingAs($self)->get(route('club.my.certificate', $awarded))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->orgUser())->get(route('club.my.certificate', $awarded))->assertForbidden();
        $this->actingAs($self)->get(route('club.exams.index'))->assertForbidden();
    }
}
