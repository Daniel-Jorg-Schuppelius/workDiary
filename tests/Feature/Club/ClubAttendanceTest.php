<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubAttendanceStatus, ClubEventVisibility, ClubParticipationSource};
use App\Enums\User\UserRole;
use App\Models\{Attendance, Event, TimeEntry, User};
use App\Models\Club\{ClubAttendanceConfirmation, ClubAttendanceRecord, ClubAttendanceRevision, ClubAttendanceSheet, ClubGroup, ClubMember};
use App\Services\Club\{ClubAttendanceService, ClubEventService, ClubGroupService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Bestätigte Vereinsanwesenheit (Feature 159, MVP-844): Anmeldung zählt null,
 * Teilanwesenheit in ganzen Minuten innerhalb der durchgeführten Dauer,
 * wiederholtes Speichern zählt einmal, keine Arbeitszeitbuchung, erkannte
 * Überschneidung, Sperrzähler, begründete Korrekturen mit unveränderlichem
 * Schnappschuss, Rechte der Gruppenleitung und der Export.
 */
class ClubAttendanceTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    private function attendance(): ClubAttendanceService {
        return app(ClubAttendanceService::class);
    }

    private function clubEvents(): ClubEventService {
        return app(ClubEventService::class);
    }

    /**
     * @param  list<int>  $groupIds
     * @param  array<string, mixed>  $overrides
     */
    private function makeEvent(array $groupIds, CarbonImmutable $start, int $hours = 2, array $overrides = []): Event {
        return $this->clubEvents()->create($this->organization, $this->admin, array_merge([
            'title' => 'Training',
            'kind' => 'training',
            'visibility' => ClubEventVisibility::Groups->value,
            'club_group_ids' => $groupIds,
            'started_at' => $start->utc()->format('Y-m-d H:i:s'),
            'ended_at' => $start->addHours($hours)->utc()->format('Y-m-d H:i:s'),
            'timezone' => 'Europe/Berlin',
        ], $overrides));
    }

    private function memberIn(ClubGroup $group): ClubMember {
        $member = ClubMember::factory()->aged(14)->create();
        app(ClubGroupService::class)->admit($group, $member, CarbonImmutable::today()->subMonth(), $this->admin);

        return $member;
    }

    private function start(): CarbonImmutable {
        return CarbonImmutable::parse('2026-09-15 18:00', 'Europe/Berlin');
    }

    // ── Anmeldung zählt null / Bestätigung ───────────────────────────────

    public function test_registration_is_never_a_record_and_credit_arises_only_with_confirmation(): void {
        $group = ClubGroup::factory()->create();
        $start = CarbonImmutable::now('Europe/Berlin')->addDays(3)->setTime(18, 0);
        $event = $this->makeEvent([$group->id], $start);
        $member = $this->memberIn($group);
        $this->clubEvents()->register($event, $member, $this->admin, ClubParticipationSource::Self);

        $sheet = $this->attendance()->sheetFor($event);
        $this->assertSame(120, $sheet->conducted_minutes, 'Eintägig: Kalenderdauer als Vorgabe.');
        $this->assertSame(0, $sheet->records()->count(), 'Die Anmeldung erzeugt keinen Nachweis.');
        $this->assertSame(0, $this->attendance()->creditableMinutes($member, $start, $start));

        $sheet = $this->attendance()->saveRows($sheet, [$member->id => ['status' => 'present']], $this->admin, 0);
        $this->assertSame(1, $sheet->version);
        $this->assertSame(0, $this->attendance()->creditableMinutes($member, $start, $start), 'Ohne Bestätigung keine Gutschrift.');

        $sheet = $this->attendance()->confirm($sheet, $this->admin, 1);
        $this->assertTrue($sheet->isConfirmed());
        $this->assertSame(120, $this->attendance()->creditableMinutes($member, $start, $start));
        $this->assertSame(1, ClubAttendanceConfirmation::query()->count());
    }

    // ── Teilanwesenheit ──────────────────────────────────────────────────

    public function test_partial_attendance_is_whole_minutes_inside_the_conducted_duration(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id], $this->start());
        $late = $this->memberIn($group);
        $short = $this->memberIn($group);
        $early = $this->memberIn($group);
        $sheet = $this->attendance()->sheetFor($event);

        $sheet = $this->attendance()->saveRows($sheet, [
            $late->id => ['status' => 'partial', 'arrived_at' => '18:30', 'left_at' => '19:45'],
            $short->id => ['status' => 'partial', 'minutes' => '45'],
            $early->id => ['status' => 'present', 'minutes' => 90],
        ], $this->admin, 0, 100);

        $minutes = $sheet->records()->get()->keyBy('club_member_id')->map(fn(ClubAttendanceRecord $r): ?int => $r->minutes);
        $this->assertSame(75, $minutes[$late->id], 'Ankunft/Abgang innerhalb des Fensters.');
        $this->assertSame(45, $minutes[$short->id]);
        $this->assertSame(90, $minutes[$early->id], 'Anwesend, aber gekürzt.');
        $this->assertSame(100, $sheet->conducted_minutes);

        try {
            $this->attendance()->saveRows($sheet, [$short->id => ['status' => 'partial', 'minutes' => 101]], $this->admin, $sheet->version);
            $this->fail('Mehr als die durchgeführte Dauer ist unzulässig.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('minutes', $e->errors());
        }
        try {
            $this->attendance()->saveRows($sheet, [], $this->admin, $sheet->version, 121);
            $this->fail('Die durchgeführte Dauer darf die Kalenderdauer nicht überschreiten.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('conducted_minutes', $e->errors());
        }

        $sheet = $this->attendance()->confirm($sheet->refresh(), $this->admin, $sheet->version);
        $this->assertSame(75, $this->attendance()->creditableMinutes($late, $this->start(), $this->start()));
        $this->assertSame(45, $this->attendance()->creditableMinutes($short, $this->start(), $this->start()));
    }

    public function test_excused_and_absent_and_untouched_rows_yield_zero(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id], $this->start());
        $excused = $this->memberIn($group);
        $absent = $this->memberIn($group);
        $untouched = $this->memberIn($group);
        $sheet = $this->attendance()->sheetFor($event);

        $sheet = $this->attendance()->saveRows($sheet, [
            $excused->id => ['status' => 'excused', 'minutes' => 120],
            $absent->id => ['status' => 'absent'],
            $untouched->id => ['status' => ''],
        ], $this->admin, 0);
        $sheet = $this->attendance()->confirm($sheet, $this->admin, $sheet->version);

        $this->assertSame(2, $sheet->records()->count(), 'Zeile ohne Stand bleibt offen.');
        $this->assertNull($sheet->records()->where('club_member_id', $excused->id)->value('minutes'));
        foreach ([$excused, $absent, $untouched] as $member) {
            $this->assertSame(0, $this->attendance()->creditableMinutes($member, $this->start(), $this->start()));
        }
        $this->assertSame(3, $this->attendance()->rosterFor($sheet, $event)->count());
    }

    // ── Wiederholtes Speichern zählt einmal ──────────────────────────────

    public function test_repeated_saving_and_confirming_counts_once(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id], $this->start());
        $member = $this->memberIn($group);
        $sheet = $this->attendance()->sheetFor($event);

        $sheet = $this->attendance()->saveRows($sheet, [$member->id => ['status' => 'present']], $this->admin, 0);
        $sheet = $this->attendance()->saveRows($sheet, [$member->id => ['status' => 'present']], $this->admin, 1);
        $this->assertSame(1, $sheet->version, 'Unveränderte Zeile erzeugt keinen Schreibvorgang.');
        $sheet = $this->attendance()->confirm($sheet, $this->admin, 1);
        $sheet = $this->attendance()->confirm($sheet, $this->admin, $sheet->version);

        $this->assertSame(1, ClubAttendanceRecord::query()->where('club_member_id', $member->id)->count());
        $this->assertSame(1, ClubAttendanceConfirmation::query()->count(), 'Erneutes Bestätigen ohne Änderung erzeugt keine zweite Version.');
        $this->assertSame(120, $this->attendance()->creditableMinutes($member, $this->start(), $this->start()));
    }

    public function test_stale_form_version_is_rejected_instead_of_overwriting(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id], $this->start());
        $member = $this->memberIn($group);
        $sheet = $this->attendance()->sheetFor($event);
        $this->attendance()->saveRows($sheet, [$member->id => ['status' => 'present']], $this->admin, 0);

        try {
            $this->attendance()->saveRows($sheet, [$member->id => ['status' => 'absent']], $this->admin, 0);
            $this->fail('Veralteter Stand muss abgewiesen werden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('version', $e->errors());
        }
        $this->assertSame(ClubAttendanceStatus::Present, ClubAttendanceRecord::query()->firstOrFail()->status);
    }

    // ── Keine Arbeitszeitbuchung ─────────────────────────────────────────

    public function test_confirmed_attendance_never_books_working_time(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id], $this->start());
        $member = $this->memberIn($group);
        $sheet = $this->attendance()->sheetFor($event);
        $sheet = $this->attendance()->saveRows($sheet, [$member->id => ['status' => 'present']], $this->admin, 0);
        $this->attendance()->confirm($sheet, $this->admin, $sheet->version);

        $this->assertSame(0, Attendance::query()->count());
        $this->assertSame(0, TimeEntry::query()->count());
    }

    // ── Überschneidung ───────────────────────────────────────────────────

    public function test_overlapping_confirmed_records_are_flagged_and_not_credited_until_resolved(): void {
        $group = ClubGroup::factory()->create();
        $member = $this->memberIn($group);
        $first = $this->makeEvent([$group->id], $this->start());
        $second = $this->makeEvent([$group->id], $this->start()->addHour(), 2, ['title' => 'Lehrgang']);

        $a = $this->attendance()->sheetFor($first);
        $a = $this->attendance()->saveRows($a, [$member->id => ['status' => 'present']], $this->admin, 0);
        $this->attendance()->confirm($a, $this->admin, $a->version);
        $this->assertSame(120, $this->attendance()->creditableMinutes($member, $this->start(), $this->start()));

        $b = $this->attendance()->sheetFor($second);
        $b = $this->attendance()->saveRows($b, [$member->id => ['status' => 'present']], $this->admin, 0);
        $this->attendance()->confirm($b, $this->admin, $b->version);

        /** @var ClubAttendanceRecord $record */
        $record = ClubAttendanceRecord::query()->where('event_id', $second->id)->firstOrFail();
        $this->assertSame($first->id, $record->overlap_event_id);
        $this->assertTrue($record->hasUnresolvedOverlap());
        $this->assertSame(120, $this->attendance()->creditableMinutes($member, $this->start(), $this->start()), 'Überschneidung bringt keine zweite Gutschrift.');

        $this->attendance()->clearOverlap($record, $this->admin, 'Zwei Hallen, Lehrgang begann später');
        $this->assertSame(240, $this->attendance()->creditableMinutes($member, $this->start(), $this->start()));
        $this->assertSame(1, ClubAttendanceRevision::query()->count());
    }

    // ── Korrektur mit Grund, Schnappschuss bleibt ────────────────────────

    public function test_corrections_after_confirmation_need_a_reason_and_keep_the_snapshot(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id], $this->start());
        $member = $this->memberIn($group);
        $sheet = $this->attendance()->sheetFor($event);
        $sheet = $this->attendance()->saveRows($sheet, [$member->id => ['status' => 'present']], $this->admin, 0);
        $sheet = $this->attendance()->confirm($sheet, $this->admin, $sheet->version);
        /** @var ClubAttendanceRecord $record */
        $record = $sheet->records()->firstOrFail();

        try {
            $this->attendance()->saveRows($sheet, [$member->id => ['status' => 'absent']], $this->admin, $sheet->version);
            $this->fail('Bestätigte Liste nimmt keine Sammelerfassung an.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('version', $e->errors());
        }
        try {
            $this->attendance()->correct($record, ['status' => 'partial', 'minutes' => 60], $this->admin, null, $sheet->version);
            $this->fail('Nach der Bestätigung braucht die Korrektur einen Grund.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $record = $this->attendance()->correct($record, ['status' => 'partial', 'minutes' => 60], $this->admin, 'Früher gegangen', $sheet->version);
        $sheet->refresh();
        $this->assertSame(60, $record->minutes);
        $this->assertTrue($sheet->changed_since_confirmation);
        $revision = ClubAttendanceRevision::query()->firstOrFail();
        $this->assertSame(ClubAttendanceStatus::Present, $revision->previous_status);
        $this->assertSame(120, $revision->previous_minutes);
        $this->assertSame('Früher gegangen', $revision->reason);
        $this->assertSame($this->admin->id, $revision->actor_user_id);
        $this->assertSame([['member_id' => $member->id, 'status' => 'present', 'minutes' => 120]], ClubAttendanceConfirmation::query()->firstOrFail()->snapshot, 'Der bestätigte Schnappschuss bleibt.');

        $sheet = $this->attendance()->confirm($sheet, $this->admin, $sheet->version);
        $this->assertSame(2, ClubAttendanceConfirmation::query()->count());
        $this->assertSame(60, $this->attendance()->creditableMinutes($member, $this->start(), $this->start()));

        $sheet = $this->attendance()->reopen($sheet, $this->admin, 'Nachtrag', $sheet->version);
        $this->assertFalse($sheet->isConfirmed());
        $this->assertSame(0, $this->attendance()->creditableMinutes($member, $this->start(), $this->start()), 'Offene Liste zählt nicht.');
    }

    public function test_multi_day_course_has_no_default_duration_and_spontaneous_members_join_the_roster(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id], $this->start(), 40, ['kind' => 'course', 'title' => 'Wochenendlehrgang']);
        $outsider = ClubMember::factory()->aged(30)->create();
        $sheet = $this->attendance()->sheetFor($event);
        $this->assertNull($sheet->conducted_minutes, 'Mehrtägig: Blöcke ausdrücklich eintragen.');

        try {
            $this->attendance()->confirm($sheet, $this->admin, 0);
            $this->fail('Ohne durchgeführte Dauer keine Bestätigung.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('conducted_minutes', $e->errors());
        }

        $sheet = $this->attendance()->saveRows($sheet, [], $this->admin, 0, 600);
        $record = $this->attendance()->addSpontaneous($sheet, $outsider, $this->admin, $sheet->version);
        $this->assertTrue($record->spontaneous);
        $this->assertSame(600, $record->minutes);
        $this->assertTrue($this->attendance()->rosterFor($sheet->refresh(), $event)->contains('id', $outsider->id));
    }

    // ── Rechte und Seiten ────────────────────────────────────────────────

    public function test_group_lead_records_only_own_groups_and_pages_render(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $own = ClubGroup::factory()->create(['leader_user_id' => $lead->id]);
        $other = ClubGroup::factory()->create();
        $ownEvent = $this->makeEvent([$own->id], $this->start());
        $otherEvent = $this->makeEvent([$other->id], $this->start());
        $member = $this->memberIn($own);

        $this->actingAs($lead)->get(route('club.events.attendance.show', $otherEvent))->assertForbidden();
        $this->actingAs($lead)->get(route('club.events.attendance.show', $ownEvent))->assertOk()->assertSee($member->fullName());
        $sheet = ClubAttendanceSheet::query()->where('event_id', $ownEvent->id)->firstOrFail();

        $this->actingAs($lead)->post(route('club.events.attendance.save', $ownEvent), [
            'version' => 0,
            'conducted_minutes' => 110,
            'records' => [$member->sqid => ['status' => 'partial', 'arrived_at' => '18:15', 'left_at' => '19:30']],
        ])->assertRedirect(route('club.events.attendance.show', $ownEvent));
        $record = ClubAttendanceRecord::query()->firstOrFail();
        $this->assertSame(75, $record->minutes);
        $this->assertSame(1, $sheet->refresh()->version);

        $this->actingAs($lead)->post(route('club.events.attendance.save', $ownEvent), ['version' => 0, 'records' => [$member->sqid => ['status' => 'absent']]])
            ->assertSessionHasErrors('version');
        $this->actingAs($lead)->post(route('club.events.attendance.confirm', $ownEvent), ['version' => 1])->assertRedirect();
        $this->assertTrue($sheet->refresh()->isConfirmed());

        $this->actingAs($lead)->get(route('club.events.attendance.records.edit', [$ownEvent, $record]))->assertOk()->assertSee('name="reason"', false);
        $this->actingAs($lead)->put(route('club.events.attendance.records.update', [$ownEvent, $record]), ['version' => $sheet->version, 'status' => 'present', 'reason' => 'Doch bis zum Ende'])
            ->assertRedirect(route('club.events.attendance.show', $ownEvent));
        $this->assertSame(110, $record->refresh()->minutes);
        $this->actingAs($lead)->get(route('club.events.attendance.reopen.edit', $ownEvent))->assertOk();
        $this->actingAs($lead)->get(route('club.events.attendance.spontaneous.create', $ownEvent))->assertOk();

        $session = $this->dateRangeSession('2026-09-01', '2026-09-30');
        $this->actingAs($lead)->withSession($session)->get(route('club.attendance.index'))->assertOk()->assertSee($member->fullName());
        $this->actingAs($this->admin)->withSession($session)->get(route('club.attendance.index', ['only_credited' => 1]))->assertOk()->assertSee($member->fullName());
        $this->actingAs($this->admin)->withSession($this->dateRangeSession('2026-10-01', '2026-10-31'))->get(route('club.attendance.index'))->assertOk()->assertDontSee($member->fullName());

        $export = $this->actingAs($this->admin)->withSession($session)->get(route('club.attendance.export'));
        $export->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($member->fullName(), $export->streamedContent());
        $this->assertStringContainsString(';110;110;', $export->streamedContent());

        $this->actingAs($this->orgUser())->get(route('club.attendance.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('club.events.show', $ownEvent))->assertOk()->assertSee(route('club.events.attendance.show', $ownEvent));
    }
}
