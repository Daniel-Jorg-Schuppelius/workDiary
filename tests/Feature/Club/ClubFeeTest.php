<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubFeePositionKind, ClubMembershipKind};
use App\Enums\User\UserRole;
use App\Models\Club\{ClubDepartment, ClubFeeAccount, ClubFeeAssignment, ClubFeeTariff, ClubGroup, ClubMember};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Club\{ClubFeeCalculator, ClubFeeService, ClubGroupService, ClubMemberService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Beitragstarife und Beitragskonten (Feature 159, MVP-849): Familiengrundbeitrag
 * einmal, Eintritt zur Monatsmitte taggenau, Pause allein erlässt keinen
 * Beitrag; dazu Sätze je Gültigkeit, Zuschläge, Aufnahmegebühr, Nachlass,
 * Befreiung, Altersvorschlag, Rechte und Seiten.
 */
class ClubFeeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    private function fees(): ClubFeeService {
        return app(ClubFeeService::class);
    }

    private function calculator(): ClubFeeCalculator {
        return app(ClubFeeCalculator::class);
    }

    /** @param array<string, mixed> $rate */
    private function tariff(string $name, array $rate, array $attributes = []): ClubFeeTariff {
        $tariff = $this->fees()->createTariff($this->organization, ['name' => $name] + $attributes);
        $this->fees()->saveRate($tariff, $rate + ['valid_from' => '2026-01-01', 'interval' => 'monthly', 'amount' => '30,00', 'anchor_month' => 1, 'due_days' => 14, 'proration' => 'full']);

        return $tariff->refresh();
    }

    private function account(string $name = 'Familie Muster'): ClubFeeAccount {
        return $this->fees()->createAccount($this->organization, ['name' => $name, 'email' => 'muster@example.test'], $this->admin);
    }

    private function member(string $joined = '2026-01-01', ?string $birth = '2010-05-10'): ClubMember {
        return ClubMember::factory()->create(['joined_on' => $joined, 'birth_date' => $birth]);
    }

    /** @return array<string, string> Quellschlüssel → Betrag */
    private function amounts(int $year, int $month): array {
        return $this->calculator()->calculateMonth($this->organization, $year, $month)['positions']
            ->mapWithKeys(fn($p): array => [$p->sourceKey => $p->amount->getAmount()])->all();
    }

    // ── Familiengrundbeitrag einmal ──────────────────────────────────────

    public function test_family_base_fee_is_charged_once_per_account_and_period(): void {
        $family = $this->tariff('Familie', ['amount' => '45,00', 'interval' => 'quarterly', 'anchor_month' => 1], ['kind' => 'family']);
        $account = $this->account();
        foreach (['2026-01-01', '2026-01-01', '2026-02-10'] as $joined) {
            $this->fees()->assign($account, $this->member($joined), $family, ['valid_from' => $joined]);
        }

        $positions = $this->calculator()->calculateMonth($this->organization, 2026, 1)['positions'];
        $this->assertCount(1, $positions, 'Drei Familienmitglieder → eine Grundbeitragsposition je Konto und Quartal.');
        $this->assertSame(ClubFeePositionKind::Family, $positions->first()->kind);
        $this->assertSame('45.00', $positions->first()->amount->getAmount());
        $this->assertNull($positions->first()->memberId);
        $this->assertSame('2026-03-31', $positions->first()->periodEnd->toDateString());
        $this->assertSame([], $this->amounts(2026, 2), 'Quartalsrhythmus: im Februar beginnt keine Periode.');

        $single = $this->tariff('Erwachsene', ['amount' => '30,00']);
        try {
            $this->fees()->assign($account, $this->member(), $single, ['valid_from' => '2026-01-01', 'discount_percent' => '10']);
            $this->addToAssertionCount(1);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('discount_reason', $e->errors(), 'Nachlass nur mit Grund.');
        }
    }

    // ── Eintritt zur Monatsmitte, taggenau ───────────────────────────────

    public function test_mid_month_join_is_prorated_by_active_calendar_days_when_the_rate_says_daily(): void {
        $daily = $this->tariff('Erwachsene taggenau', ['amount' => '30,00', 'proration' => 'daily']);
        $full = $this->tariff('Erwachsene voll', ['amount' => '30,00', 'proration' => 'full']);
        $account = $this->account();
        $midDaily = $this->member('2026-04-16');
        $midFull = $this->member('2026-04-16');
        $a = $this->fees()->assign($account, $midDaily, $daily, ['valid_from' => '2026-04-16']);
        $b = $this->fees()->assign($account, $midFull, $full, ['valid_from' => '2026-04-16']);

        $april = $this->amounts(2026, 4);
        $this->assertSame('15.00', $april['assignment:' . $a->id . ':2026-04-01'], '15 von 30 Tagen → 15,00.');
        $this->assertSame('30.00', $april['assignment:' . $b->id . ':2026-04-01'], 'Volle Periode zählt den Monat ganz.');
        $this->assertSame([], $this->amounts(2026, 3), 'Vor dem Eintritt nichts.');

        // Austritt zum 10.05. taggenau: 10 von 31 Tagen → 9,68 (HalfUp auf Cent).
        app(ClubMemberService::class)->leave($midDaily, CarbonImmutable::parse('2026-05-10'), $this->admin);
        $this->assertSame('9.68', $this->amounts(2026, 5)['assignment:' . $a->id . ':2026-05-01']);
        $this->assertArrayNotHasKey('assignment:' . $a->id . ':2026-06-01', $this->amounts(2026, 6), 'Austritt beendet künftige Beiträge.');
    }

    // ── Pause allein erlässt keinen Beitrag ──────────────────────────────

    public function test_membership_pause_alone_does_not_waive_but_an_explicit_exemption_does(): void {
        $tariff = $this->tariff('Erwachsene', ['amount' => '30,00', 'proration' => 'daily']);
        $account = $this->account();
        $member = $this->member();
        $assignment = $this->fees()->assign($account, $member, $tariff, ['valid_from' => '2026-01-01']);
        app(ClubMemberService::class)->changeKind($member, ClubMembershipKind::Paused, CarbonImmutable::parse('2026-06-01'), $this->admin);

        $this->assertSame('30.00', $this->amounts(2026, 6)['assignment:' . $assignment->id . ':2026-06-01'], 'Pause der Mitgliedschaft ändert den Beitrag nicht.');

        $this->fees()->saveExemption($member, ['kind' => 'exemption', 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-30', 'reason' => 'Verletzung'], $this->admin);
        $this->assertArrayNotHasKey('assignment:' . $assignment->id . ':2026-06-01', $this->amounts(2026, 6), 'Ausdrückliche Befreiung über den ganzen Monat → keine Position.');

        $this->fees()->saveExemption($member, ['kind' => 'reduction', 'percent' => '50', 'starts_on' => '2026-07-16', 'ends_on' => null, 'reason' => 'Halbe Kraft'], $this->admin);
        // Juli: 15 volle Tage + 16 halbe Tage = 23 von 31 → 30 × 23/31 = 22,26.
        $this->assertSame('22.26', $this->amounts(2026, 7)['assignment:' . $assignment->id . ':2026-07-01']);
        // August komplett ermäßigt: 15,00.
        $this->assertSame('15.00', $this->amounts(2026, 8)['assignment:' . $assignment->id . ':2026-08-01']);

        try {
            $this->fees()->saveExemption($member, ['kind' => 'exemption', 'starts_on' => '2026-09-01', 'reason' => ''], $this->admin);
            $this->fail('Befreiung braucht einen Grund.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
    }

    // ── Sätze, Zuschlag, Aufnahmegebühr, Nachlass, Überlappung ───────────

    public function test_rate_versions_surcharges_admission_fee_and_discount_apply_per_period(): void {
        $tariff = $this->tariff('Kinder', ['amount' => '20,00', 'admission_fee' => '25,00'], ['min_age' => 0, 'max_age' => 17]);
        $this->fees()->saveRate($tariff, ['valid_from' => '2026-07-01', 'interval' => 'monthly', 'amount' => '22,00', 'anchor_month' => 1, 'due_days' => 10, 'proration' => 'full']);
        $department = ClubDepartment::factory()->create(['name' => 'Judo']);
        $group = ClubGroup::factory()->create(['club_department_id' => $department->id]);
        $this->fees()->saveSurcharge($this->organization, ['club_department_id' => $department->id, 'name' => 'Mattengeld', 'interval' => 'monthly', 'amount' => '5,00', 'anchor_month' => 1, 'valid_from' => '2026-01-01']);
        $account = $this->account();
        $member = $this->member('2026-03-05');
        app(ClubGroupService::class)->admit($group, $member, CarbonImmutable::parse('2026-03-05'), $this->admin);
        $assignment = $this->fees()->assign($account, $member, $tariff, ['valid_from' => '2026-03-05', 'discount_percent' => '25', 'discount_reason' => 'Geschwister']);

        $march = $this->amounts(2026, 3);
        $this->assertSame('15.00', $march['assignment:' . $assignment->id . ':2026-03-01'], '20,00 − 25 % Nachlass.');
        $this->assertSame('25.00', $march['admission:' . $member->id], 'Aufnahmegebühr in der Eintrittsperiode.');
        $surchargeKeys = array_values(array_filter(array_keys($march), static fn(string $key): bool => str_starts_with($key, 'surcharge:')));
        $this->assertCount(1, $surchargeKeys, 'Ein Abteilungszuschlag für die aktive Gruppenzuordnung.');
        $this->assertSame('5.00', $march[$surchargeKeys[0]], 'Zuschlag ohne Nachlass, volle Periode.');
        $this->assertArrayNotHasKey('admission:' . $member->id, $this->amounts(2026, 4), 'Aufnahmegebühr nur einmal.');
        $this->assertSame('16.50', $this->amounts(2026, 7)['assignment:' . $assignment->id . ':2026-07-01'], 'Ab Juli gilt der neue Satz 22,00 − 25 %.');
        $july = $this->calculator()->calculateMonth($this->organization, 2026, 7)['positions']->first(fn($p) => $p->kind === ClubFeePositionKind::Base);
        $this->assertSame('2026-07-11', $july->dueOn->toDateString(), 'Fälligkeit aus dem geltenden Satz.');

        try {
            $this->fees()->assign($account, $member, $tariff, ['valid_from' => '2026-05-01']);
            $this->fail('Überlappende Zuordnung ist unzulässig.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('valid_from', $e->errors());
        }
    }

    public function test_age_mismatch_creates_a_review_and_the_change_is_effective_on_the_confirmed_date(): void {
        $kids = $this->tariff('Kinder', ['amount' => '20,00'], ['min_age' => 0, 'max_age' => 17]);
        $adults = $this->tariff('Erwachsene', ['amount' => '30,00'], ['min_age' => 18]);
        $account = $this->account();
        $member = ClubMember::factory()->birthdayToday(18)->create(['joined_on' => '2026-01-01']);
        $assignment = $this->fees()->assign($account, $member, $kids, ['valid_from' => '2026-01-01']);

        $this->assertSame(1, $this->fees()->flagAgeMismatches($this->organization));
        $this->assertSame(0, $this->fees()->flagAgeMismatches($this->organization), 'Zweiter Lauf markiert nicht doppelt.');
        $this->assertTrue($assignment->refresh()->needsReview());
        $this->assertSame($adults->id, $this->fees()->suggestTariff($assignment, CarbonImmutable::today())?->id);

        $effective = CarbonImmutable::today()->addMonthNoOverflow()->startOfMonth();
        $new = $this->fees()->changeTariff($assignment, $adults, $effective, $this->admin);
        $this->assertSame($effective->subDay()->toDateString(), $assignment->refresh()->valid_to?->toDateString(), 'Alte Zuordnung endet am Vortag.');
        $this->assertFalse($assignment->needsReview());
        $this->assertSame($effective->toDateString(), $new->valid_from->toDateString());
        $this->assertSame($account->id, $new->club_fee_account_id);
        $this->assertSame(2, ClubFeeAssignment::query()->where('club_member_id', $member->id)->count());
    }

    public function test_accounts_use_the_customer_master_and_pages_and_rights_work(): void {
        $treasurer = $this->userWithRole(UserRole::Buchhaltung->value);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $existing = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Bestandskunde']);
        $tariff = $this->tariff('Erwachsene', ['amount' => '30,00']);
        $member = $this->member();

        $this->actingAs($treasurer)->post(route('club.fees.accounts.store'), ['customer_id' => $existing->sqid, 'notes' => 'zahlt für Sohn'])->assertRedirect();
        $account = ClubFeeAccount::query()->firstOrFail();
        $this->assertSame($existing->id, $account->customer_id);
        $this->assertSame('Bestandskunde', $account->name);
        $this->actingAs($treasurer)->post(route('club.fees.accounts.store'), ['customer_id' => $existing->sqid])->assertSessionHasErrors('customer_id');
        $this->actingAs($treasurer)->post(route('club.fees.accounts.store'), ['name' => 'Neue Zahlerin', 'email' => 'neu@example.test'])->assertRedirect();
        $this->assertSame(2, Customer::query()->count(), 'Neuer Debitor angelegt.');

        $this->actingAs($treasurer)->post(route('club.fees.assignments.store', $account), ['club_member_id' => $member->sqid, 'club_fee_tariff_id' => $tariff->sqid, 'valid_from' => '2026-01-01'])
            ->assertRedirect(route('club.fees.accounts.show', $account));
        $assignment = ClubFeeAssignment::query()->firstOrFail();
        $this->actingAs($treasurer)->post(route('club.fees.assignments.store', $account), ['club_member_id' => $member->sqid, 'club_fee_tariff_id' => $tariff->sqid, 'valid_from' => '2026-02-01'])
            ->assertSessionHasErrors('valid_from');

        $this->actingAs($treasurer)->get(route('club.fees.tariffs.index'))->assertOk()->assertSee('Erwachsene')->assertSee('30,00');
        $this->actingAs($treasurer)->get(route('club.fees.tariffs.create'))->assertOk();
        $this->actingAs($treasurer)->get(route('club.fees.rates.create', $tariff))->assertOk()->assertSee('name="proration"', false);
        $this->actingAs($treasurer)->get(route('club.fees.surcharges.create'))->assertOk();
        $this->actingAs($treasurer)->get(route('club.fees.accounts.index'))->assertOk()->assertSee('Bestandskunde');
        $this->actingAs($treasurer)->get(route('club.fees.accounts.show', $account))->assertOk()->assertSee($member->fullName());
        $this->actingAs($treasurer)->get(route('club.fees.accounts.create'))->assertOk();
        $this->actingAs($treasurer)->get(route('club.fees.assignments.create', $account))->assertOk();
        $this->actingAs($treasurer)->get(route('club.fees.assignments.edit', [$account, $assignment]))->assertOk();
        $this->actingAs($treasurer)->get(route('club.fees.exemptions.create', [$account, $member]))->assertOk();
        $this->actingAs($treasurer)->get(route('club.fees.preview', ['month' => '2026-03']))->assertOk()->assertSee('30,00')->assertSee($member->fullName());
        $this->actingAs($this->admin)->get(route('club.members.show', $member))->assertOk()->assertSee('Erwachsene');

        $this->actingAs($lead)->get(route('club.fees.accounts.index'))->assertForbidden();
        $this->actingAs($lead)->get(route('club.fees.preview'))->assertForbidden();
        $this->actingAs($this->orgUser())->get(route('club.fees.tariffs.index'))->assertForbidden();
    }
}
