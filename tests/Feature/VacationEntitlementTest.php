<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationEntitlementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\{Organization, User, Vacation, VacationEntitlement};
use App\Services\Absence\VacationBalanceService;
use App\Support\Sqid;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-413 Urlaubskonto: Saldoberechnung (Anspruch/Übertrag/Verfall),
 * Rechte- und Mandantengrenzen der Pflegeseite, Bulk-Anlage und
 * Überbuchungswarnung beim Genehmigen.
 */
class VacationEntitlementTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function makeEntitlement(User $user, int $year = 2030, float $days = 20.0, float $carryover = 0.0, ?string $expires = null): VacationEntitlement {
        return VacationEntitlement::create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'year' => $year,
            'entitled_days' => $days,
            'carryover_days' => $carryover,
            'carryover_expires_on' => $expires,
        ]);
    }

    private function makeVacation(User $user, string $start, string $end, VacationStatus $status = VacationStatus::Approved, VacationType $type = VacationType::Vacation): Vacation {
        return Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'start_date' => $start,
            'end_date' => $end,
            'type' => $type->value,
            'status' => $status->value,
        ]);
    }

    // ── Saldoberechnung ─────────────────────────────────────────────────────

    public function test_balance_counts_working_days_and_pending_separately(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->makeEntitlement($user, 2030, 20.0);
        // Mo 03.06.–Fr 07.06.2030 = 5 Werktage genehmigt; Mo 17.06.–Di 18.06. = 2 Tage offen
        // (10./11.06. wäre Pfingstmontag — der HolidayService zählt ihn korrekt nicht mit).
        $this->makeVacation($user, '2030-06-03', '2030-06-07');
        $this->makeVacation($user, '2030-06-17', '2030-06-18', VacationStatus::Pending);

        $balance = app(VacationBalanceService::class)->balanceFor((int) $user->id, 2030, Carbon::parse('2030-06-01'));

        $this->assertTrue($balance->hasEntitlement);
        $this->assertSame(5.0, $balance->takenDays);
        $this->assertSame(2.0, $balance->pendingDays);
        $this->assertSame(15.0, $balance->remainingDays());
        $this->assertSame(13.0, $balance->remainingAfterPendingDays());
    }

    public function test_special_and_unpaid_leave_do_not_consume_entitlement(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->makeEntitlement($user, 2030, 20.0);
        $this->makeVacation($user, '2030-06-03', '2030-06-07', VacationStatus::Approved, VacationType::Special);
        $this->makeVacation($user, '2030-07-01', '2030-07-02', VacationStatus::Approved, VacationType::Unpaid);

        $balance = app(VacationBalanceService::class)->balanceFor((int) $user->id, 2030);

        $this->assertSame(0.0, $balance->takenDays);
        $this->assertSame(20.0, $balance->remainingDays());
    }

    public function test_expired_carryover_only_counts_days_taken_before_expiry(): void {
        $service = app(VacationBalanceService::class);

        // Nutzer A: 5 Übertragstage, 5 Tage im Januar genommen → Übertrag voll verbraucht.
        $userA = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->makeEntitlement($userA, 2030, 20.0, 5.0, '2030-03-31');
        // Mo 07.01.–Fr 11.01.2030 = 5 Werktage.
        $this->makeVacation($userA, '2030-01-07', '2030-01-11');

        $balanceA = $service->balanceFor((int) $userA->id, 2030, Carbon::parse('2030-04-15'));
        $this->assertSame(5.0, $balanceA->usableCarryoverDays);
        $this->assertSame(25.0, $balanceA->totalDays());
        $this->assertSame(20.0, $balanceA->remainingDays());

        // Nutzer B: 5 Übertragstage, nichts vor dem 31.03. genommen → Übertrag verfallen.
        $userB = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->makeEntitlement($userB, 2030, 20.0, 5.0, '2030-03-31');
        $this->makeVacation($userB, '2030-06-03', '2030-06-07');

        $balanceB = $service->balanceFor((int) $userB->id, 2030, Carbon::parse('2030-04-15'));
        $this->assertSame(0.0, $balanceB->usableCarryoverDays);
        $this->assertSame(20.0, $balanceB->totalDays());
        $this->assertSame(15.0, $balanceB->remainingDays());

        // Vor dem Verfallsdatum zählt der volle Übertrag.
        $balanceBEarly = $service->balanceFor((int) $userB->id, 2030, Carbon::parse('2030-02-01'));
        $this->assertSame(5.0, $balanceBEarly->usableCarryoverDays);
    }

    public function test_balance_without_entitlement_reports_has_entitlement_false(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $balance = app(VacationBalanceService::class)->balanceFor((int) $user->id, 2030);

        $this->assertFalse($balance->hasEntitlement);
        $this->assertSame(0.0, $balance->totalDays());
    }

    // ── Rechte und Mandantengrenzen ─────────────────────────────────────────

    public function test_regular_user_cannot_open_entitlement_page(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('vacation-entitlements.index'))
            ->assertForbidden();
    }

    public function test_admin_can_manage_entitlements(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('vacation-entitlements.index', ['year' => 2030]))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('vacation-entitlements.store'), [
                'user_id' => Sqid::encode(User::class, (int) $member->id),
                'year' => 2030,
                'entitled_days' => 28,
                'carryover_days' => 2,
                'carryover_expires_on' => '2030-03-31',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vacation_entitlements', [
            'organization_id' => $this->organization->id,
            'user_id' => $member->id,
            'year' => 2030,
        ]);
    }

    public function test_entitlement_of_other_organization_is_unreachable(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $foreignOrg = Organization::factory()->create();
        $foreignUser = User::factory()->user()->create(['organization_id' => $foreignOrg->id]);
        $foreign = VacationEntitlement::query()->withoutGlobalScopes()->create([
            'organization_id' => $foreignOrg->id,
            'user_id' => $foreignUser->id,
            'year' => 2030,
            'entitled_days' => 20,
        ]);

        $this->actingAs($admin)
            ->put(route('vacation-entitlements.update', ['vacation_entitlement' => Sqid::encode(VacationEntitlement::class, (int) $foreign->id)]), [
                'entitled_days' => 99,
            ])
            ->assertNotFound();
    }

    // ── Bulk-Anlage ─────────────────────────────────────────────────────────

    public function test_bulk_creates_missing_entitlements_and_stores_org_default(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $memberWith = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->makeEntitlement($memberWith, 2030, 25.0);

        $this->actingAs($admin)
            ->post(route('vacation-entitlements.bulk'), ['year' => 2030, 'default_days' => 28])
            ->assertRedirect();

        // Alle Nutzer der Org haben jetzt genau einen Anspruch für 2030; Bestand bleibt unangetastet.
        $orgUserCount = User::query()->where('organization_id', $this->organization->id)->count();
        $this->assertSame($orgUserCount, VacationEntitlement::query()->where('year', 2030)->count());
        $this->assertSame(25.0, (float) VacationEntitlement::query()->where('user_id', $memberWith->id)->where('year', 2030)->value('entitled_days'));

        $this->organization->refresh();
        $this->assertSame(28.0, $this->organization->vacationDefaultDays());

        // Idempotent: zweiter Lauf legt nichts doppelt an.
        $this->actingAs($admin)
            ->post(route('vacation-entitlements.bulk'), ['year' => 2030, 'default_days' => 28])
            ->assertRedirect();
        $this->assertSame($orgUserCount, VacationEntitlement::query()->where('year', 2030)->count());
    }

    // ── Überbuchungswarnung ─────────────────────────────────────────────────

    public function test_approving_beyond_entitlement_flashes_warning_but_approves(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->makeEntitlement($member, 2030, 3.0);
        // Mo 03.06.–Fr 07.06.2030 = 5 Werktage > 3 Tage Anspruch.
        $vacation = $this->makeVacation($member, '2030-06-03', '2030-06-07', VacationStatus::Pending);

        $response = $this->actingAs($admin)
            ->patch(route('vacations.approve', $vacation))
            ->assertRedirect();

        $response->assertSessionHas('warning');
        $this->assertSame(VacationStatus::Approved, $vacation->fresh()->status);
    }

    public function test_approving_within_entitlement_flashes_success(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->makeEntitlement($member, 2030, 20.0);
        $vacation = $this->makeVacation($member, '2030-06-03', '2030-06-07', VacationStatus::Pending);

        $response = $this->actingAs($admin)
            ->patch(route('vacations.approve', $vacation))
            ->assertRedirect();

        $response->assertSessionHas('success');
        $response->assertSessionMissing('warning');
    }
}
