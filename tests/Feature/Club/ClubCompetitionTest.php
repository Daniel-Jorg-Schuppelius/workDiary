<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubCompetitionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubEntryStatus, ClubEventRoleKind, ClubEventVisibility, ClubFeePositionKind};
use App\Enums\User\UserRole;
use App\Models\Calendar\Event;
use App\Models\Club\{ClubAttendanceRequirement, ClubCompetitionEntry, ClubGroup, ClubMember, ClubPerformance, ClubSportProfile};
use App\Models\Platform\User;
use App\Services\Club\{ClubAttendanceService, ClubCompetitionService, ClubEventService, ClubFeeCalculator, ClubFeeService, ClubGroupService, ClubMatchService, ClubTeamService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Individual-, Wettkampf- und Schießsport (Feature 159, MVP-855): Bestleistung
 * nur aus bestätigten Werten; kleinere Zeit und größere Weite gewinnen je nach
 * Disziplin; Nachweisliste zählt nur bestätigte Anwesenheiten ohne fest
 * kodierte gesetzliche Schwellen; Meldung ohne Startrecht wird zur Klärung markiert.
 */
class ClubCompetitionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private ClubSportProfile $athletics;

    private ClubGroup $group;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->athletics = app(ClubTeamService::class)->createProfile($this->organization, [
            'name' => 'Leichtathletik', 'family' => 'individual', 'result_format' => 'none',
            'disciplines' => "100m=100 m Lauf;s;ja\nweit=Weitsprung;m;nein\nkugel=Kugelstoßen;m;nein",
        ]);
        $this->group = ClubGroup::factory()->create(['name' => 'LA Jugend', 'leader_user_id' => $this->admin->id]);
    }

    private function competitions(): ClubCompetitionService {
        return app(ClubCompetitionService::class);
    }

    private function member(): ClubMember {
        $member = ClubMember::factory()->aged(15)->create();
        app(ClubGroupService::class)->admit($this->group, $member, CarbonImmutable::today()->subMonth(), $this->admin);

        return $member;
    }

    /** @param  array<string, mixed>  $overrides */
    private function competition(string $start = '2026-10-20 10:00', array $overrides = []): Event {
        $begin = CarbonImmutable::parse($start, 'Europe/Berlin');

        return $this->competitions()->create($this->organization, $this->admin, array_merge([
            'title' => 'Kreismeisterschaft', 'club_sport_profile_id' => $this->athletics->id, 'disciplines' => ['100m', 'weit'],
            'started_at' => $begin->utc()->format('Y-m-d H:i:s'), 'ended_at' => $begin->addHours(6)->utc()->format('Y-m-d H:i:s'), 'timezone' => 'Europe/Berlin',
            'club_group_ids' => [$this->group->id], 'visibility' => ClubEventVisibility::Groups->value, 'leader_user_id' => $this->admin->id,
        ], $overrides));
    }

    public function test_best_performance_uses_only_confirmed_values_and_direction_per_discipline(): void {
        $athlete = $this->member();
        $record = fn(string $code, string $value, bool $confirm, string $on = '2026-09-01') => $this->competitions()->recordPerformance($athlete, ['club_sport_profile_id' => $this->athletics->id, 'discipline_code' => $code, 'value' => $value, 'performed_on' => $on, 'confirm' => $confirm], $this->admin);

        $record('100m', '12,80', true, '2026-08-01');
        $fast = $record('100m', '12,10', false, '2026-09-01');
        $record('100m', '12,55', true, '2026-09-10');
        $record('weit', '5,20', true, '2026-08-01');
        $record('weit', '5,61', true, '2026-09-05');
        $record('weit', '5,90', false, '2026-09-12');

        $bests = $this->competitions()->bests($athlete)->keyBy('discipline_code');
        $this->assertSame('12.550', $bests->get('100m')?->value, 'Kleinere Zeit gewinnt — unbestätigte 12,10 zählt nicht.');
        $this->assertSame('5.610', $bests->get('weit')?->value, 'Größere Weite gewinnt — unbestätigte 5,90 zählt nicht.');
        $this->assertTrue($bests->get('100m')->lower_is_better);
        $this->assertFalse($bests->get('weit')->lower_is_better);
        $this->assertSame('12,55 s', $bests->get('100m')->formattedValue());

        $this->competitions()->confirmPerformance($fast, $this->admin);
        $this->assertSame('12.100', $this->competitions()->bests($athlete)->keyBy('discipline_code')->get('100m')?->value);
        $this->assertSame('12.100', $this->competitions()->bests($athlete, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'))->keyBy('discipline_code')->get('100m')?->value, 'Saisonfilter.');

        $corrected = $this->competitions()->correctPerformance($fast, ['value' => '12,30'], $this->admin);
        $this->assertFalse($corrected->isConfirmed(), 'Korrektur löscht die Bestätigung.');
        $this->assertSame('12.550', $this->competitions()->bests($athlete)->keyBy('discipline_code')->get('100m')?->value);
        try {
            $record('marathon', '3,00', true);
            $this->fail('Unbekannte Disziplin.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('discipline_code', $e->errors());
        }
    }

    public function test_entry_without_start_right_is_flagged_for_review_not_rejected_and_clears_with_a_right(): void {
        $event = $this->competition();
        $athlete = $this->member();
        $entries = $this->competitions()->enter($event, $athlete, ['100m', 'weit'], $this->admin);
        $this->assertCount(2, $entries);
        $this->assertTrue($entries->every(fn(ClubCompetitionEntry $e): bool => $e->status === ClubEntryStatus::NeedsReview), 'Ohne Startrecht zur Klärung, nicht abgelehnt.');
        $this->assertSame(1, $event->clubParticipations()->count(), 'Meldung macht das Mitglied zum Teilnehmer — einmal.');

        $this->competitions()->grantStartRight($athlete, ['club_sport_profile_id' => $this->athletics->id, 'reference' => 'LV-12345', 'valid_to' => '2026-12-31'], $this->admin);
        $this->assertSame(0, ClubCompetitionEntry::query()->where('club_member_id', $athlete->id)->where('status', ClubEntryStatus::NeedsReview->value)->count(), 'Neues Startrecht klärt offene Meldungen.');

        $later = $this->competition('2027-03-01 10:00');
        $entry = $this->competitions()->enter($later, $athlete, ['100m'], $this->admin)->first();
        $this->assertSame(ClubEntryStatus::NeedsReview, $entry->status, 'Abgelaufenes Startrecht → Klärung.');
        $cleared = $this->competitions()->clearEntry($entry, $this->admin, 'Startpass liegt vor');
        $this->assertSame(ClubEntryStatus::Registered, $cleared->status);
        $withdrawn = $this->competitions()->withdraw($cleared, $this->admin);
        $this->assertSame(ClubEntryStatus::Withdrawn, $withdrawn->status);

        $open = $this->competition('2026-11-01 10:00', ['requires_start_right' => false]);
        $this->assertSame(ClubEntryStatus::Registered, $this->competitions()->enter($open, $this->member(), ['weit'], $this->admin)->first()->status, 'Ohne Startrechtspflicht direkt gemeldet.');
        try {
            $this->competitions()->enter($open, $this->member(), ['kugel'], $this->admin);
            $this->fail('Nicht angebotene Disziplin.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('disciplines', $e->errors());
        }
    }

    public function test_entry_fee_becomes_a_fee_position_and_competition_results_feed_performances(): void {
        $athlete = $this->member();
        $fees = app(ClubFeeService::class);
        $tariff = $fees->createTariff($this->organization, ['name' => 'Jugend']);
        $fees->saveRate($tariff, ['valid_from' => '2026-01-01', 'interval' => 'monthly', 'amount' => '10,00', 'anchor_month' => 1, 'due_days' => 14, 'proration' => 'full']);
        $account = $fees->createAccount($this->organization, ['name' => 'Familie Lauf'], $this->admin);
        $fees->assign($account, $athlete, $tariff, ['valid_from' => '2026-01-01']);

        $event = $this->competition('2026-10-20 10:00', ['entry_fee' => '7,50', 'requires_start_right' => false]);
        $this->competitions()->enter($event, $athlete, ['100m', 'weit'], $this->admin);
        $positions = app(ClubFeeCalculator::class)->calculateMonth($this->organization, 2026, 10)['positions'];
        $entryPositions = $positions->filter(fn($p): bool => $p->kind === ClubFeePositionKind::Entry);
        $this->assertCount(2, $entryPositions, 'Meldegebühr je Disziplin als eigene Beitragsposition.');
        $this->assertSame('7.50', $entryPositions->first()->amount->getAmount());
        $this->assertSame($account->id, $entryPositions->first()->accountId);

        $entry = ClubCompetitionEntry::query()->where('event_id', $event->id)->where('discipline_code', '100m')->firstOrFail();
        $this->competitions()->withdraw($entry, $this->admin);
        $this->assertCount(1, app(ClubFeeCalculator::class)->calculateMonth($this->organization, 2026, 10)['positions']->filter(fn($p): bool => $p->kind === ClubFeePositionKind::Entry), 'Zurückgezogen = keine Gebühr.');

        $result = $this->competitions()->recordPerformance($athlete, ['club_sport_profile_id' => $this->athletics->id, 'discipline_code' => 'weit', 'value' => '5,44', 'placement' => 2, 'event_id' => $event->id, 'performed_on' => '2026-10-20', 'confirm' => true], $this->admin);
        $this->assertSame($event->id, $result->event_id);
        $this->assertSame(2, $result->placement);
        $this->assertSame('5.440', $this->competitions()->bests($athlete)->first()?->value);
    }

    public function test_attendance_requirement_counts_only_confirmed_attendance_without_hard_coded_thresholds(): void {
        $shooting = app(ClubTeamService::class)->createProfile($this->organization, ['name' => 'Schießsport', 'family' => 'shooting', 'result_format' => 'none', 'disciplines' => 'lg=Luftgewehr;Ringe;nein']);
        $range = ClubGroup::factory()->create(['name' => 'Schützen', 'club_sport_profile_id' => $shooting->id]);
        $shooter = ClubMember::factory()->aged(30)->create();
        $other = ClubMember::factory()->aged(30)->create();
        app(ClubGroupService::class)->admit($range, $shooter, CarbonImmutable::today()->subYear(), $this->admin);
        app(ClubGroupService::class)->admit($range, $other, CarbonImmutable::today()->subYear(), $this->admin);
        $requirement = $this->competitions()->createRequirement($this->organization, ['name' => 'Schießnachweis', 'club_group_id' => $range->id, 'required_count' => 3, 'period_months' => 12, 'event_kind' => 'training']);
        $this->assertSame(3, $requirement->required_count, 'Anzahl kommt aus der Konfiguration, nicht aus dem Code.');

        $attendance = app(ClubAttendanceService::class);
        $events = app(ClubEventService::class);
        for ($i = 1; $i <= 4; $i++) {
            $start = CarbonImmutable::today()->subDays($i * 20)->setTime(18, 0);
            $event = $events->create($this->organization, $this->admin, ['title' => 'Schießtraining ' . $i, 'kind' => 'training', 'visibility' => ClubEventVisibility::Groups->value, 'club_group_ids' => [$range->id], 'started_at' => $start->utc()->format('Y-m-d H:i:s'), 'ended_at' => $start->addHours(2)->utc()->format('Y-m-d H:i:s'), 'timezone' => 'Europe/Berlin']);
            $sheet = $attendance->sheetFor($event);
            $rows = [$shooter->id => ['status' => $i === 4 ? 'excused' : 'present'], $other->id => ['status' => 'present']];
            $sheet = $attendance->saveRows($sheet, $rows, $this->admin, $sheet->version);
            if ($i !== 3) {
                $attendance->confirm($sheet->refresh(), $this->admin, $sheet->version);
            }
        }
        $report = $this->competitions()->complianceReport($requirement, CarbonImmutable::today())->keyBy(fn(array $r): int => $r['member']->id);
        $this->assertSame(2, $report->get($shooter->id)['count'], 'Nur bestätigte Listen mit anwesend zählen: Liste 3 unbestätigt, Liste 4 entschuldigt.');
        $this->assertFalse($report->get($shooter->id)['met']);
        $this->assertSame(3, $report->get($other->id)['count']);
        $this->assertTrue($report->get($other->id)['met']);
        $old = $this->competitions()->complianceReport($requirement, CarbonImmutable::today()->subMonths(13))->keyBy(fn(array $r): int => $r['member']->id);
        $this->assertSame(0, $old->get($other->id)['count'] ?? 0, 'Zeitraum vor den Terminen.');

        // Standaufsicht als Pflichtrolle bei Schießsport-Terminen.
        $start = CarbonImmutable::today()->addDays(3)->setTime(18, 0);
        $training = $events->create($this->organization, $this->admin, ['title' => 'Schießtraining', 'kind' => 'training', 'visibility' => ClubEventVisibility::Groups->value, 'club_group_ids' => [$range->id], 'started_at' => $start->utc()->format('Y-m-d H:i:s'), 'ended_at' => $start->addHours(2)->utc()->format('Y-m-d H:i:s'), 'timezone' => 'Europe/Berlin']);
        $this->assertTrue($this->competitions()->missingRangeOfficer($training));
        app(ClubMatchService::class)->assignRole($training, ClubEventRoleKind::RangeOfficer, ['club_member_id' => $other->id], $this->admin);
        $this->assertFalse($this->competitions()->missingRangeOfficer($training));
        $this->assertFalse($this->competitions()->missingRangeOfficer($this->competition()), 'Leichtathletik braucht keine Standaufsicht.');

        $this->actingAs($this->admin)->get(route('club.requirements.index', ['requirement' => $requirement->sqid]))->assertOk()->assertSee($shooter->fullName());
        $csv = $this->actingAs($this->admin)->get(route('club.requirements.export', $requirement))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($other->fullName(), $csv->streamedContent());
    }

    public function test_pages_dialogs_rights_and_portal_entry(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $event = $this->competition('2026-10-25 10:00', ['requires_start_right' => false]);
        $athlete = $this->member();

        $this->actingAs($this->admin)->get(route('club.competitions.index'))->assertOk()->assertSee('Kreismeisterschaft');
        $this->actingAs($this->admin)->get(route('club.competitions.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('club.competitions.show', $event))->assertOk()->assertSee(__('club.competitions.card.entries'));
        $this->actingAs($this->admin)->get(route('club.competitions.edit', $event))->assertOk();
        $this->actingAs($this->admin)->get(route('club.competitions.entries.create', $event))->assertOk();
        $this->actingAs($this->admin)->post(route('club.competitions.entries.store', $event), ['club_member_id' => $athlete->sqid, 'disciplines' => ['100m']])->assertRedirect(route('club.competitions.show', $event));
        $entry = ClubCompetitionEntry::query()->firstOrFail();
        $this->actingAs($this->admin)->get(route('club.competitions.entries.result.edit', [$event, $entry]))->assertOk();
        $this->actingAs($this->admin)->post(route('club.competitions.entries.result', [$event, $entry]), ['value' => '12,90', 'placement' => 1, 'confirm' => 1])->assertRedirect();
        $this->assertSame(1, ClubPerformance::query()->count());
        $this->actingAs($this->admin)->get(route('club.members.show', $athlete))->assertOk()->assertSee(__('club.competitions.card.performances'))->assertSee('12,9 s');
        $this->actingAs($this->admin)->get(route('club.members.performances.create', $athlete))->assertOk();
        $this->actingAs($this->admin)->post(route('club.members.performances.store', $athlete), ['club_sport_profile_id' => $this->athletics->sqid, 'discipline_code' => 'weit', 'value' => '5,10', 'performed_on' => '2026-09-01'])->assertRedirect();
        $this->actingAs($this->admin)->get(route('club.members.startrights.create', $athlete))->assertOk();
        $this->actingAs($this->admin)->post(route('club.members.startrights.store', $athlete), ['reference' => 'LV-1', 'valid_from' => '2026-01-01'])->assertRedirect();
        $this->actingAs($this->admin)->get(route('club.requirements.create'))->assertOk();
        $this->actingAs($this->admin)->post(route('club.requirements.store'), ['name' => 'Nachweis', 'required_count' => 5, 'period_months' => 12])->assertRedirect();
        $this->assertSame(1, ClubAttendanceRequirement::query()->count());

        // Gruppenleitung: Leistungen ja, Startrecht und Anforderungen nein.
        $this->actingAs($lead)->get(route('club.members.performances.create', $athlete))->assertForbidden();
        $this->group->update(['leader_user_id' => $lead->id]);
        $this->actingAs($lead)->get(route('club.members.performances.create', $athlete))->assertOk();
        $this->actingAs($lead)->get(route('club.members.startrights.create', $athlete))->assertForbidden();
        $this->actingAs($lead)->get(route('club.requirements.create'))->assertForbidden();
        $this->actingAs($lead)->get(route('club.requirements.index'))->assertOk();

        // Portal: Meldung durch ein Mitglied ohne Startrecht → Klärung, nicht Ablehnung.
        $novice = $this->member();
        $login = $this->orgUser();
        $novice->update(['user_id' => $login->id]);
        $strict = $this->competition('2026-11-15 10:00');
        $this->actingAs($login)->get(route('club.my.index'))->assertOk()->assertSee(__('club.competitions.action.enter'));
        $this->actingAs($login)->post(route('club.my.compete', $strict), ['disciplines' => ['weit']])->assertRedirect(route('club.my.index'));
        $this->assertSame(ClubEntryStatus::NeedsReview, ClubCompetitionEntry::query()->where('event_id', $strict->id)->where('club_member_id', $novice->id)->firstOrFail()->status);
        $this->actingAs($this->orgUser())->get(route('club.competitions.index'))->assertForbidden();
    }

    /** Löschen ist Sache der Vereinsverwaltung; die Gruppenleitung korrigiert nur. */
    public function test_performance_can_be_deleted_from_the_member_page_by_the_administration_only(): void {
        $athlete = $this->member();
        $record = fn(string $value, ClubMember $member) => $this->competitions()->recordPerformance($member, ['club_sport_profile_id' => $this->athletics->id, 'discipline_code' => 'weit', 'value' => $value, 'performed_on' => '2026-09-01', 'confirm' => true], $this->admin);
        $keep = $record('5,10', $athlete);
        $wrong = $record('7,90', $athlete);
        $deleteRoute = route('club.members.performances.destroy', [$athlete, $wrong]);
        // Die Adresse ist Anfang der Korrektur-Adresse: nur das Formularziel belegt den Löschknopf.
        $deleteForm = 'action="' . $deleteRoute . '"';

        $this->actingAs($this->admin)->get(route('club.members.show', $athlete))
            ->assertOk()
            ->assertSee($deleteForm, false)
            ->assertSee('action="' . route('club.members.performances.destroy', [$athlete, $keep]) . '"', false);

        // Gruppenleitung der eigenen Gruppe: darf korrigieren, sieht und erreicht das Löschen nicht.
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $this->group->update(['leader_user_id' => $lead->id]);
        $this->actingAs($lead)->get(route('club.members.show', $athlete))
            ->assertOk()
            ->assertSee(route('club.members.performances.edit', [$athlete, $wrong]), false)
            ->assertDontSee($deleteForm, false);
        $this->actingAs($lead)->delete($deleteRoute)->assertForbidden();

        // Die Leistung gehört zum Mitglied in der Adresse — sonst 404.
        $other = $this->member();
        $this->actingAs($this->admin)->delete(route('club.members.performances.destroy', [$other, $wrong]))->assertNotFound();
        $this->assertNotNull($wrong->fresh());

        $this->actingAs($this->admin)->delete($deleteRoute)
            ->assertRedirect(route('club.members.show', $athlete))
            ->assertSessionHas('success', __('club.competitions.flash.performance_deleted'));

        $this->assertNull($wrong->fresh());
        $this->assertSame('5.100', $this->competitions()->bests($athlete)->keyBy('discipline_code')->get('weit')?->value);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'club.competition.performanceDeleted',
            'auditable_id' => $wrong->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_performance_of_another_organization_cannot_be_deleted(): void {
        $athlete = $this->member();
        $performance = $this->competitions()->recordPerformance($athlete, ['club_sport_profile_id' => $this->athletics->id, 'discipline_code' => 'weit', 'value' => '5,10', 'performed_on' => '2026-09-01', 'confirm' => true], $this->admin);
        $route = route('club.members.performances.destroy', [$athlete, $performance]);

        $foreignAdmin = $this->orgAdmin(['organization_id' => \App\Models\Platform\Organization::factory()->create()->id]);
        $this->actingAs($foreignAdmin)->delete($route)->assertNotFound();

        $this->assertNotNull($performance->fresh());
    }

    /**
     * Sicherheitsaudit 2026-10-04, authz-a-3 und authz-a-4: die Gruppenleitung
     * sieht in der Nachweisliste und bearbeitet bei den Leistungen nur
     * Mitglieder ihrer Gruppen.
     */
    public function test_group_lead_is_limited_to_members_of_own_groups(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $ownGroup = ClubGroup::factory()->create(['name' => 'Eigene Gruppe', 'leader_user_id' => $lead->id]);
        $own = ClubMember::factory()->aged(15)->create(['last_name' => 'Eigenmann']);
        app(ClubGroupService::class)->admit($ownGroup, $own, CarbonImmutable::today()->subMonth(), $this->admin);
        $foreign = $this->member();
        $foreign->update(['last_name' => 'Fremdberg']);

        $record = fn(ClubMember $member) => $this->competitions()->recordPerformance($member, ['club_sport_profile_id' => $this->athletics->id, 'discipline_code' => 'weit', 'value' => '5,10', 'performed_on' => '2026-09-01', 'confirm' => false], $this->admin);
        $foreignPerformance = $record($foreign);
        $ownPerformance = $record($own);

        $this->actingAs($lead)->post(route('club.members.performances.confirm', [$foreign, $foreignPerformance]))->assertForbidden();
        $this->actingAs($lead)->put(route('club.members.performances.update', [$foreign, $foreignPerformance]), ['value' => '9,99'])->assertForbidden();
        $this->assertNull($foreignPerformance->refresh()->confirmed_by_user_id);
        $this->actingAs($lead)->post(route('club.members.performances.confirm', [$own, $ownPerformance]))->assertRedirect();
        $this->assertSame($lead->id, $ownPerformance->refresh()->confirmed_by_user_id);

        // Anforderung ohne Gruppe gilt für den ganzen Verein — die Leitung sieht trotzdem nur ihre Mitglieder.
        $requirement = ClubAttendanceRequirement::query()->create(['organization_id' => $this->organization->id, 'name' => 'Nachweis', 'required_count' => 5, 'period_months' => 12]);
        $this->actingAs($lead)->get(route('club.requirements.index', ['requirement' => $requirement->sqid]))
            ->assertOk()->assertSee('Eigenmann')->assertDontSee('Fremdberg');
        $csv = $this->actingAs($lead)->get(route('club.requirements.export', $requirement))->assertOk()->streamedContent();
        $this->assertStringContainsString('Eigenmann', $csv);
        $this->assertStringNotContainsString('Fremdberg', $csv);
        $this->actingAs($this->admin)->get(route('club.requirements.index', ['requirement' => $requirement->sqid]))->assertSee('Fremdberg');
    }

    /** Die Nachweisliste blättert über die Mitgliederabfrage; der Export bleibt vollständig. */
    public function test_requirement_report_pages_through_members_and_exports_all_of_them(): void {
        foreach (range(1, 52) as $i) {
            ClubMember::factory()->aged(30)->create(['last_name' => sprintf('Nachweis%02d', $i), 'first_name' => 'Anna']);
        }
        $requirement = ClubAttendanceRequirement::query()->create(['organization_id' => $this->organization->id, 'name' => 'Nachweis', 'required_count' => 1, 'period_months' => 12]);
        $names = fn($response): array => collect($response->viewData('report')->items())->map(fn(array $row): string => $row['member']->last_name)->all();

        $first = $this->actingAs($this->admin)->get(route('club.requirements.index', ['requirement' => $requirement->sqid]))->assertOk();
        $this->assertSame(52, $first->viewData('report')->total());
        $this->assertSame('Nachweis01', $names($first)[0]);
        $this->assertCount(50, $names($first));
        $first->assertSee('requirement=' . $requirement->sqid . '&amp;page=2', false);

        $second = $this->actingAs($this->admin)->get(route('club.requirements.index', ['requirement' => $requirement->sqid, 'page' => 2]))->assertOk();
        $this->assertSame(['Nachweis51', 'Nachweis52'], $names($second));
        $this->assertSame(0, $second->viewData('report')->items()[0]['count']);

        $csv = $this->actingAs($this->admin)->get(route('club.requirements.export', $requirement))->assertOk()->streamedContent();
        $this->assertStringContainsString('Nachweis01', $csv);
        $this->assertStringContainsString('Nachweis52', $csv);
    }
}
