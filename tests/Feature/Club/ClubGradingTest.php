<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGradingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubCriteriaResult, ClubEventVisibility, ClubParticipationSource, ClubProposalStatus};
use App\Enums\User\UserRole;
use App\Models\Club\{ClubGrade, ClubGradeRequirement, ClubGradingSystem, ClubGradingVersion, ClubGroup, ClubGroupChangeProposal, ClubMember};
use App\Models\{Event, User};
use App\Services\Club\{ClubAttendanceService, ClubEligibilityService, ClubEventService, ClubGradingService, ClubGroupService, ClubMemberService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Graduierungsregeln und Zulassung (Feature 159, MVP-846): 20-Stunden-Beispiel,
 * Disziplinen getrennt, Alter/Wartefrist am Stichtag ohne Monatsüberlauf,
 * Header-Filter ohne Einfluss, neuer Grad erzeugt Gruppenwechselvorschlag,
 * unbekannter Grad gewährt keinen Zugang, externe Nachweise nur per Regelversion.
 */
class ClubGradingTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    private ClubGradingSystem $judo;

    private ClubGrade $white;

    private ClubGrade $yellow;

    private ClubGradingVersion $version;

    private ClubGradeRequirement $yellowRule;

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
        // Fiktives Abnahmebeispiel: 20 Stunden und drei Monate seit Vorgrad.
        $this->yellowRule = $this->grading()->saveRequirement($this->version, $this->yellow, [
            'previous_grade_id' => $this->white->id,
            'min_minutes' => 1200,
            'wait_months' => 3,
            'counting_basis' => 'since_previous_grade',
        ]);
        $this->group = ClubGroup::factory()->create(['name' => 'Judo Kinder', 'discipline' => 'Judo']);
    }

    private function grading(): ClubGradingService {
        return app(ClubGradingService::class);
    }

    private function eligibility(): ClubEligibilityService {
        return app(ClubEligibilityService::class);
    }

    private function member(string $joined = '2026-01-01', ?string $birth = '2012-05-10'): ClubMember {
        $member = ClubMember::factory()->create(['joined_on' => $joined, 'birth_date' => $birth]);
        app(ClubGroupService::class)->admit($this->group, $member, CarbonImmutable::parse($joined), $this->admin);

        return $member;
    }

    /** Bestätigtes Training der Gruppe mit voller Anwesenheit des Mitglieds. */
    private function confirmedTraining(ClubMember $member, string $start, int $minutes = 75, ?string $discipline = null, string $status = 'present'): Event {
        $begin = CarbonImmutable::parse($start, 'Europe/Berlin');
        $event = app(ClubEventService::class)->create($this->organization, $this->admin, [
            'title' => 'Training ' . $begin->format('d.m.'),
            'kind' => 'training',
            'visibility' => ClubEventVisibility::Groups->value,
            'club_group_ids' => [$this->group->id],
            'discipline' => $discipline,
            'started_at' => $begin->utc()->format('Y-m-d H:i:s'),
            'ended_at' => $begin->addMinutes($minutes)->utc()->format('Y-m-d H:i:s'),
            'timezone' => 'Europe/Berlin',
        ]);
        $attendance = app(ClubAttendanceService::class);
        $sheet = $attendance->sheetFor($event);
        $sheet = $attendance->saveRows($sheet, [$member->id => ['status' => $status, 'minutes' => $status === 'excused' ? null : $minutes]], $this->admin, $sheet->version);
        $attendance->confirm($sheet, $this->admin, $sheet->version);

        return $event;
    }

    // ── 20-Stunden-Beispiel ──────────────────────────────────────────────

    public function test_twenty_hour_example_counts_only_confirmed_attendance_after_the_previous_grade(): void {
        $member = $this->member();
        $this->grading()->recognizeGrade($member, $this->white, CarbonImmutable::parse('2026-03-01'), 'Urkunde', $this->admin);
        $this->confirmedTraining($member, '2026-02-20 18:00', 75); // vor dem Vorgrad — zählt nicht
        for ($i = 0; $i < 15; $i++) {
            $this->confirmedTraining($member, CarbonImmutable::parse('2026-04-01 18:00')->addWeeks($i)->format('Y-m-d H:i'), 75);
        }
        $this->confirmedTraining($member, '2026-08-20 18:00', 75, null, 'excused');
        app(ClubEventService::class)->register(
            app(ClubEventService::class)->create($this->organization, $this->admin, ['title' => 'Nur angemeldet', 'kind' => 'training', 'visibility' => 'groups', 'club_group_ids' => [$this->group->id], 'started_at' => '2026-09-01 16:00:00', 'ended_at' => '2026-09-01 17:15:00', 'timezone' => 'Europe/Berlin']),
            $member, $this->admin, ClubParticipationSource::Admin, null, true,
        );

        $report = $this->eligibility()->evaluate($member, $this->yellowRule, CarbonImmutable::parse('2026-09-23'));
        $this->assertSame(1125, $report->minutes, '15 × 75 Minuten; Anmeldung und Entschuldigung zählen null.');
        $this->assertFalse($report->met);
        $this->assertSame(75, $report->remainingMinutes());
        $this->assertStringContainsString('18 Stunden 45 Minuten von 20 Stunden', (string) $report->progressText());
        $this->assertStringContainsString('noch 1 Stunde 15 Minuten', (string) $report->progressText());
        $this->assertSame(['minutes'], array_column($report->missing(), 'key'));

        $this->confirmedTraining($member, '2026-09-10 18:00', 75);
        $report = $this->eligibility()->evaluate($member, $this->yellowRule, CarbonImmutable::parse('2026-09-23'));
        $this->assertSame(1200, $report->minutes);
        $this->assertTrue($report->met, 'Zeit und Wartefrist (seit 01.03.) erfüllt — zulassungsfähig, aber noch kein neuer Grad.');
        $this->assertSame($this->white->id, $this->grading()->currentGrade($member, $this->judo, CarbonImmutable::parse('2026-09-23'))?->club_grade_id);
    }

    public function test_disciplines_are_kept_apart_and_external_credits_count_only_when_the_version_allows(): void {
        $member = $this->member();
        $this->grading()->recognizeGrade($member, $this->white, CarbonImmutable::parse('2026-03-01'), null, $this->admin);
        $this->confirmedTraining($member, '2026-05-05 18:00', 90);
        $this->confirmedTraining($member, '2026-05-06 18:00', 90, 'Karate');

        $exam = CarbonImmutable::parse('2026-09-23');
        $this->assertSame(90, $this->eligibility()->evaluate($member, $this->yellowRule, $exam)->minutes, 'Karate zählt nicht für Judo.');

        $this->grading()->addProof($member, ['kind' => 'external_training', 'label' => 'Landeslehrgang', 'discipline' => 'Judo', 'minutes' => 120, 'obtained_on' => '2026-06-01', 'origin' => 'Landesverband'], $this->admin);
        $this->assertSame(90, $this->eligibility()->evaluate($member, $this->yellowRule, $exam)->minutes, 'Externer Nachweis ohne Freigabe der Regelversion zählt nicht.');
        $this->grading()->updateVersion($this->version, ['accepts_external_credits' => true]);
        $this->assertSame(210, $this->eligibility()->evaluate($member, $this->yellowRule->refresh(), $exam)->minutes);
    }

    public function test_age_and_waiting_time_refer_to_the_exam_day_without_month_overflow(): void {
        $member = $this->member('2026-01-01', '2014-10-01');
        $this->grading()->recognizeGrade($member, $this->white, CarbonImmutable::parse('2026-01-31'), null, $this->admin);
        $rule = $this->grading()->saveRequirement($this->version, $this->yellow, ['previous_grade_id' => $this->white->id, 'wait_months' => 1, 'min_age' => 12, 'counting_basis' => 'since_previous_grade']);

        $items = fn(string $day): array => collect($this->eligibility()->evaluate($member, $rule, CarbonImmutable::parse($day))->items)->keyBy('key')->map(fn(array $i): bool => $i['met'])->all();
        $this->assertSame(['previous_grade' => true, 'wait' => false, 'age' => false], $items('2026-02-27'), 'Einen Tag vor Fristende; 11 Jahre alt.');
        $this->assertSame(['previous_grade' => true, 'wait' => true, 'age' => false], $items('2026-02-28'), '31.01. + 1 Monat = 28.02. (kein Überlauf in den März).');
        $this->assertSame(['previous_grade' => true, 'wait' => true, 'age' => true], $items('2026-10-01'), 'Am 12. Geburtstag erfüllt.');
        $this->assertNull($this->eligibility()->evaluate($member, $rule, CarbonImmutable::parse('2026-10-01'))->progressText(), 'Ohne Mindestminuten kein Fortschrittstext.');
    }

    public function test_header_date_range_does_not_change_eligibility(): void {
        $member = $this->member();
        $this->grading()->recognizeGrade($member, $this->white, CarbonImmutable::parse('2026-03-01'), null, $this->admin);
        for ($i = 0; $i < 15; $i++) {
            $this->confirmedTraining($member, CarbonImmutable::parse('2026-04-01 18:00')->addWeeks($i)->format('Y-m-d H:i'), 75);
        }

        foreach ([['2026-09-01', '2026-09-30'], ['2026-01-01', '2026-01-31']] as [$from, $to]) {
            $this->actingAs($this->admin)->withSession($this->dateRangeSession($from, $to))
                ->get(route('club.members.grading', $member))
                ->assertOk()
                ->assertSee('18 Stunden 45 Minuten von 20 Stunden');
        }
    }

    // ── Gruppen nach Grad ────────────────────────────────────────────────

    public function test_new_grade_creates_a_group_change_proposal_and_unknown_grade_grants_no_access(): void {
        $beginners = ClubGroup::factory()->create(['name' => 'Anfänger', 'discipline' => 'Judo', 'club_grading_system_id' => $this->judo->id, 'max_grade_id' => $this->white->id, 'club_department_id' => null]);
        $advanced = ClubGroup::factory()->create(['name' => 'Fortgeschrittene', 'discipline' => 'Judo', 'club_grading_system_id' => $this->judo->id, 'min_grade_id' => $this->yellow->id, 'club_department_id' => null]);
        $member = ClubMember::factory()->create(['joined_on' => '2026-01-01']);
        $groups = app(ClubGroupService::class);

        $this->assertSame(ClubCriteriaResult::GradeUnknown, $groups->evaluate($beginners, $member, CarbonImmutable::today()), 'Ohne Grad kein Zugang, auch nicht zur Anfängergruppe mit Höchstgrad.');
        try {
            $groups->admit($advanced, $member, CarbonImmutable::today(), $this->admin);
            $this->fail('Unbekannter Grad gewährt keinen Zugang.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->grading()->recognizeGrade($member, $this->white, CarbonImmutable::parse('2026-01-15'), null, $this->admin);
        $this->assertSame(ClubCriteriaResult::Met, $groups->evaluate($beginners, $member, CarbonImmutable::today()));
        $this->assertSame(ClubCriteriaResult::GradeBelow, $groups->evaluate($advanced, $member, CarbonImmutable::today()));
        $groups->admit($beginners, $member, CarbonImmutable::parse('2026-02-01'), $this->admin);
        $this->assertSame(0, ClubGroupChangeProposal::query()->count());

        $this->grading()->recognizeGrade($member, $this->yellow, CarbonImmutable::parse('2026-09-20'), 'Prüfung anderswo', $this->admin);
        /** @var ClubGroupChangeProposal $proposal */
        $proposal = ClubGroupChangeProposal::query()->where('club_member_id', $member->id)->firstOrFail();
        $this->assertSame(ClubCriteriaResult::GradeAbove, $proposal->reason);
        $this->assertSame(ClubProposalStatus::Open, $proposal->status);
        $this->assertSame($advanced->id, $proposal->suggested_group_id, 'Vorschlag statt automatischer Entfernung.');
        $this->assertTrue($beginners->activeMemberships()->where('club_member_id', $member->id)->exists());
    }

    public function test_recognition_is_idempotent_and_revocation_keeps_the_entry(): void {
        $member = $this->member();
        $first = $this->grading()->recognizeGrade($member, $this->yellow, CarbonImmutable::parse('2026-05-01'), null, $this->admin);
        $this->assertSame($first->id, $this->grading()->recognizeGrade($member, $this->yellow, CarbonImmutable::parse('2026-06-01'), null, $this->admin)->id);
        $this->assertSame($this->yellow->id, $this->grading()->currentGrade($member, $this->judo, CarbonImmutable::today())?->club_grade_id);

        $this->grading()->revokeGrade($first, 'Urkunde gefälscht', $this->admin);
        $this->assertNull($this->grading()->currentGrade($member, $this->judo, CarbonImmutable::today()));
        $this->assertTrue($first->refresh()->isRevoked());
        $this->assertNull($this->grading()->currentGrade($member, $this->judo, CarbonImmutable::parse('2026-04-30')), 'Vor dem Erwerb kein Grad.');
    }

    // ── Seiten und Rechte ────────────────────────────────────────────────

    public function test_pages_render_and_grading_rights_are_separate(): void {
        $member = $this->member();
        $self = $this->orgUser();
        app(ClubMemberService::class)->update($member, ['user_id' => $self->id]);
        $this->grading()->recognizeGrade($member, $this->white, CarbonImmutable::parse('2026-03-01'), null, $this->admin);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);

        $this->actingAs($this->admin)->get(route('club.grading.index'))->assertOk()->assertSee('Judo-Ordnung');
        $this->actingAs($this->admin)->get(route('club.grading.show', $this->judo))->assertOk()->assertSee('Gelb')->assertSee('20 Stunden');
        $this->actingAs($this->admin)->get(route('club.grading.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('club.grading.grades.create', $this->judo))->assertOk();
        $this->actingAs($this->admin)->get(route('club.grading.versions.edit', $this->version))->assertOk();
        $this->actingAs($this->admin)->get(route('club.grading.requirements.edit', [$this->version, $this->yellow]))->assertOk()->assertSee('name="min_minutes"', false);
        $this->actingAs($this->admin)->get(route('club.members.grading', $member))->assertOk()->assertSee('Weiß')->assertSee('0 Stunden von 20 Stunden');
        $this->actingAs($this->admin)->get(route('club.members.grades.create', $member))->assertOk();
        $this->actingAs($this->admin)->get(route('club.members.proofs.create', $member))->assertOk();
        $this->actingAs($this->admin)->get(route('club.members.show', $member))->assertOk()->assertSee(route('club.members.grading', $member));
        $this->actingAs($this->admin)->get(route('club.settings.edit'))->assertOk()->assertSee('name="graduation_enabled"', false);
        $this->actingAs($this->admin)->get(route('club.groups.create'))->assertOk()->assertSee('name="min_grade_id"', false);

        $this->actingAs($this->admin)->post(route('club.grading.store'), ['name' => 'Karate-Ordnung', 'discipline' => 'Karate'])->assertRedirect();
        $this->assertSame(2, ClubGradingSystem::query()->count());
        $this->actingAs($this->admin)->post(route('club.members.grades.store', $member), ['club_grade_id' => $this->yellow->sqid, 'obtained_on' => '2026-09-01', 'evidence' => 'Urkunde'])
            ->assertRedirect(route('club.members.grading', $member));
        $this->assertSame($this->yellow->id, $this->grading()->currentGrade($member, $this->judo, CarbonImmutable::today())?->club_grade_id);

        $this->actingAs($self)->get(route('club.my.index'))->assertOk()->assertSee('Gelb');
        $this->actingAs($self)->get(route('club.grading.index'))->assertForbidden();
        $this->actingAs($lead)->get(route('club.grading.index'))->assertOk();
        $this->actingAs($lead)->post(route('club.grading.store'), ['name' => 'x', 'discipline' => 'y'])->assertForbidden();
        $this->actingAs($lead)->get(route('club.members.grades.create', $member))->assertForbidden();

        $this->grading()->setEnabled($this->organization, false);
        $this->actingAs($self)->get(route('club.my.index'))->assertOk()->assertDontSee('Gelb');
    }
}
