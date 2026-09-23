<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDemoTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Enums\Club\{ClubAttendanceSheetStatus, ClubEntryStatus, ClubEventRoleKind, ClubFeeRunStatus, ClubLineupStatus};
use App\Enums\Demo\DemoIndustry;
use App\Models\Club\{ClubAttendanceRequirement, ClubAttendanceSheet, ClubCompetitionDetails, ClubCompetitionEntry, ClubDepartment, ClubEventRole, ClubExamCandidate, ClubFeeAccount, ClubFeeClaim, ClubFeeClaimItem, ClubFeeDunning, ClubFeePayment, ClubFeeRun, ClubGroup, ClubGroupMembership, ClubGuardian, ClubHorse, ClubHorseAssignment, ClubHorseUse, ClubMatchDetails, ClubMember, ClubPerformance, ClubResourceBooking, ClubResourceClosure, ClubSportProfile, ClubSquadMember};
use App\Models\Finance\PaymentRun;
use App\Models\Platform\{Organization, User};
use App\Services\Club\{ClubCompetitionService, ClubGradingService, ClubStarterPackService};
use App\Services\Demo\DemoSeederService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Musterbranche Sportverein und Startpakete (Feature 159, MVP-848):
 * Vereinsbasis mit Beitragslauf, jeder Sportschnitt, Mehrspartenverein ohne
 * doppelte Personen, Jugend mit Spielgemeinschaft und Fahrdienst,
 * Nachweisliste ohne gesetzliche Schwellen, Profil-Umfang ohne Finanzpaket,
 * Reset ohne Leichen, Startpakete idempotent und ohne Demo nutzbar.
 */
final class ClubDemoTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array{0: Organization, 1: User, 2: array<string, int|string>} */
    private function seedClub(): array {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);
        $counts = app(DemoSeederService::class)->seed($organization, $admin, DemoIndustry::Verein);

        return [$organization->refresh(), $admin, $counts];
    }

    public function test_verein_demo_covers_every_sport_slice(): void {
        [$organization, , $counts] = $this->seedClub();
        $orgId = $organization->id;

        $this->assertSame('verein', $counts['branch_profile']);
        $this->assertGreaterThan(60, (int) $counts['club_members']);
        $this->assertGreaterThan(80, (int) $counts['club_events']);

        // Elf Sportarten als Konfiguration, je eine Abteilung.
        $this->assertSame(11, ClubSportProfile::query()->withoutGlobalScopes()->where('organization_id', $orgId)->count());
        $this->assertSame(11, ClubDepartment::query()->withoutGlobalScopes()->where('organization_id', $orgId)->whereNotNull('club_sport_profile_id')->count());

        // Training mit bestätigter Anwesenheit.
        $this->assertGreaterThan(40, ClubAttendanceSheet::query()->withoutGlobalScopes()->where('organization_id', $orgId)->where('status', ClubAttendanceSheetStatus::Confirmed->value)->count());

        // Mannschaften: freigegebene Aufstellungen und erfasste Ergebnisse in allen Formaten.
        $matches = ClubMatchDetails::query()->withoutGlobalScopes()->where('organization_id', $orgId)->get();
        $this->assertSame(24, $matches->count());
        $this->assertSame(24, $matches->where('lineup_status', ClubLineupStatus::Released)->count());
        $this->assertSame(16, $matches->whereNotNull('result_summary')->count());
        $this->assertTrue($matches->contains(fn(ClubMatchDetails $m): bool => $m->result_summary === '3:1'));
        $this->assertTrue($matches->contains(fn(ClubMatchDetails $m): bool => str_starts_with((string) $m->result_summary, '3:1 (')));
        $this->assertTrue($matches->contains(fn(ClubMatchDetails $m): bool => str_starts_with((string) $m->result_summary, '76:70 (')));

        // Wettkampf: Meldung ohne Startrecht bleibt zur Klärung, bestätigte Leistungen.
        $this->assertSame(2, ClubCompetitionDetails::query()->withoutGlobalScopes()->where('organization_id', $orgId)->count());
        $entries = ClubCompetitionEntry::query()->withoutGlobalScopes()->where('organization_id', $orgId)->get();
        $this->assertSame(1, $entries->where('status', ClubEntryStatus::NeedsReview)->count());
        $this->assertSame(6, $entries->where('status', ClubEntryStatus::Registered)->count());
        $this->assertSame(6, ClubPerformance::query()->withoutGlobalScopes()->where('organization_id', $orgId)->whereNotNull('confirmed_at')->count());

        // Graduierung: anerkannte Grade und Prüfungsangebot mit Kandidaten.
        $this->assertTrue(app(ClubGradingService::class)->isEnabled($organization));
        $this->assertSame(3, ClubExamCandidate::query()->withoutGlobalScopes()->where('organization_id', $orgId)->count());

        // Reitbetrieb: Schulpferde plus Privatpferd, Zuteilungen, Einsatzminuten, Sperrzeit.
        $this->assertSame(3, ClubHorse::query()->withoutGlobalScopes()->where('organization_id', $orgId)->count());
        $this->assertGreaterThan(10, ClubHorseAssignment::query()->withoutGlobalScopes()->where('organization_id', $orgId)->count());
        $this->assertGreaterThan(5, ClubHorseUse::query()->withoutGlobalScopes()->where('organization_id', $orgId)->count());
        $this->assertSame(1, ClubResourceClosure::query()->withoutGlobalScopes()->where('organization_id', $orgId)->count());
        $this->assertGreaterThan(50, ClubResourceBooking::query()->withoutGlobalScopes()->where('organization_id', $orgId)->count());

        // Beiträge: freigegebener Lauf, Zahlungen, ein Rückstand.
        $run = ClubFeeRun::query()->withoutGlobalScopes()->where('organization_id', $orgId)->firstOrFail();
        $this->assertSame(ClubFeeRunStatus::Released, $run->status);
        $this->assertGreaterThan(30, (int) $counts['club_claims']);
        $this->assertGreaterThan(20, ClubFeePayment::query()->withoutGlobalScopes()->where('organization_id', $orgId)->count());
        // Gemahnt wird nur der vollständig offene Rückstand, Teilzahlungen bleiben unbehelligt.
        $unpaidOverdue = ClubFeeClaim::query()->withoutGlobalScopes()->where('organization_id', $orgId)->get()
            ->filter(fn(ClubFeeClaim $c): bool => $c->isOverdue() && ! $c->paid_amount->isPositive());
        $this->assertSame($unpaidOverdue->count(), ClubFeeDunning::query()->withoutGlobalScopes()->where('organization_id', $orgId)->count());
    }

    public function test_multi_department_member_is_one_person_with_one_base_fee(): void {
        [$organization] = $this->seedClub();
        $members = ClubMember::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('first_name', 'Murat')->where('last_name', 'Yilmaz')->get();
        $this->assertCount(1, $members);
        $member = $members->firstOrFail();

        $departments = ClubGroupMembership::query()->withoutGlobalScopes()->where('club_member_id', $member->id)
            ->join('club_groups', 'club_groups.id', '=', 'club_group_memberships.club_group_id')
            ->pluck('club_groups.club_department_id')->unique();
        $this->assertCount(2, $departments);

        $items = ClubFeeClaimItem::query()->withoutGlobalScopes()->where('club_member_id', $member->id)->get();
        $this->assertSame(1, $items->where('kind', 'base')->count(), 'Der Grundbeitrag darf je Person nur einmal anfallen.');
    }

    public function test_family_account_and_guardian_login_are_linked(): void {
        [$organization] = $this->seedClub();
        $account = ClubFeeAccount::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('name', 'Familie Brandt')->firstOrFail();
        $this->assertSame(4, $account->assignments()->count());
        $this->assertSame(1, ClubFeeClaimItem::query()->withoutGlobalScopes()->whereIn('club_fee_claim_id', ClubFeeClaim::query()->withoutGlobalScopes()->where('club_fee_account_id', $account->id)->pluck('id'))->where('kind', 'family')->count());

        $thomas = ClubMember::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('first_name', 'Thomas')->where('last_name', 'Brandt')->firstOrFail();
        $this->assertSame('demo+02@workdiary.test', $thomas->user?->email);
        $lena = ClubMember::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('first_name', 'Lena')->where('last_name', 'Brandt')->firstOrFail();
        $guardian = ClubGuardian::query()->withoutGlobalScopes()->where('club_member_id', $lena->id)->firstOrFail();
        $this->assertSame('demo+03@workdiary.test', $guardian->user?->email);
        $this->assertContains('register', (array) $guardian->permissions);
    }

    public function test_youth_football_has_a_guest_player_and_a_driver_role(): void {
        [$organization] = $this->seedClub();
        $team = ClubGroup::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('name', 'Fußball E-Jugend')->firstOrFail();
        $guests = ClubSquadMember::query()->withoutGlobalScopes()->whereIn('club_squad_id', $team->squads()->pluck('id'))->whereNotNull('guest_origin')->get();
        $this->assertCount(1, $guests);
        $this->assertStringContainsString('Spielgemeinschaft', (string) $guests->first()?->guest_origin);

        $matchEventIds = ClubMatchDetails::query()->withoutGlobalScopes()->where('club_group_id', $team->id)->pluck('event_id');
        $this->assertCount(3, $matchEventIds);
        $this->assertSame(3, ClubEventRole::query()->withoutGlobalScopes()->whereIn('event_id', $matchEventIds)->where('role', ClubEventRoleKind::Driver->value)->count());
    }

    public function test_shooting_requirement_is_club_configured_without_legal_thresholds(): void {
        [$organization] = $this->seedClub();
        $requirement = ClubAttendanceRequirement::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame(12, $requirement->required_count);
        $this->assertSame(12, $requirement->period_months);

        $service = app(ClubCompetitionService::class);
        $report = $service->complianceReport($requirement, CarbonImmutable::today());
        $this->assertSame(4, $report->count(), 'Nachweisliste zeigt die Mitglieder der Gruppe „Schützen Aktive“.');

        // Der Verein ändert die Zahlen frei — kein fester Wert im Code.
        $service->updateRequirement($requirement, ['name' => $requirement->name, 'club_group_id' => $requirement->club_group_id, 'required_count' => 6, 'period_months' => 12, 'event_kind' => 'training']);
        $this->assertSame(6, $requirement->refresh()->required_count);

        $shooting = ClubCompetitionDetails::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereJsonContains('disciplines', 'lg')->firstOrFail();
        $this->assertSame(1, ClubEventRole::query()->withoutGlobalScopes()->where('event_id', $shooting->event_id)->where('role', ClubEventRoleKind::RangeOfficer->value)->count());
        $this->assertFalse($service->missingRangeOfficer($shooting->event()->firstOrFail()));
    }

    public function test_profile_scope_bills_internally_without_finance_module(): void {
        [$organization, , $counts] = $this->seedClub();
        $this->assertSame('profile', $counts['showcase']);
        $this->assertGreaterThan(0, (int) $counts['club_claims']);
        $this->assertSame(0, PaymentRun::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->count());
    }

    public function test_reset_purges_club_data_and_reseeds_deterministically(): void {
        [$organization, $admin, $first] = $this->seedClub();
        $membersBefore = ClubMember::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->count();

        $second = app(DemoSeederService::class)->reset($organization, $admin);

        foreach (['club_members', 'club_events', 'club_claims'] as $key) {
            $this->assertSame($first[$key], $second[$key], 'Nicht deterministisch: ' . $key);
        }
        $this->assertSame($membersBefore, ClubMember::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->count());
        $this->assertSame(11, ClubSportProfile::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->count());
        $this->assertSame(1, ClubFeeRun::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->count());
    }

    public function test_starter_pack_is_idempotent_and_base_works_without_grading(): void {
        $this->setUpOrganization();
        $admin = $this->orgAdmin();
        $packs = app(ClubStarterPackService::class);

        $this->assertCount(11, $packs->available());

        $first = $packs->install($this->organization, 'tischtennis', $admin);
        $second = $packs->install($this->organization, 'tischtennis', $admin);
        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame(3, $first['groups']);
        $this->assertSame(0, $second['groups']);
        $this->assertSame(1, ClubSportProfile::query()->where('name', 'Tischtennis')->count());
        $this->assertSame(3, ClubGroup::query()->where('club_department_id', ClubDepartment::query()->where('name', 'Tischtennis')->value('id'))->count());
        $this->assertFalse(app(ClubGradingService::class)->isEnabled($this->organization->refresh()), 'Die Vereinsbasis braucht keine Graduierung.');

        $martial = $packs->install($this->organization, 'kampfsport', $admin);
        $this->assertTrue($martial['grading']);
        $this->assertTrue(app(ClubGradingService::class)->isEnabled($this->organization->refresh()));
        $this->assertSame(6, ClubGroup::query()->count(), 'Kampfsport ergänzt drei Gruppen zu den drei des Tischtennis.');
    }

    public function test_pack_can_be_installed_from_the_profiles_page(): void {
        $this->setUpOrganization();
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->get(route('club.profiles.index'))->assertOk()->assertSee('Schießsport');

        $this->actingAs($admin)->post(route('club.profiles.packs.store'), ['pack' => 'schiesssport'])
            ->assertRedirect(route('club.profiles.index'))
            ->assertSessionHas('success');
        $this->assertTrue(ClubSportProfile::query()->where('name', 'Schießsport')->exists());
        $this->assertSame(1, ClubAttendanceRequirement::query()->count());

        $this->actingAs($admin)->post(route('club.profiles.packs.store'), ['pack' => 'gibt-es-nicht'])->assertSessionHasErrors('pack');
    }
}
