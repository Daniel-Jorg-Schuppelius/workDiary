<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationEntitlementComponentsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Absence;

use App\Enums\User\Permission;
use App\Models\{User, VacationEntitlement};
use App\Services\Absence\VacationBalanceService;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-535 (Feature 103, Q1-Drittabgleich): getrennte Anspruchskomponenten —
 * SGB-IX-Zusatzurlaub und sonstige Ansprüche fließen in den Gesamtanspruch,
 * bleiben aber getrennt ausgewiesen.
 */
class VacationEntitlementComponentsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_components_add_to_total_days(): void {
        $user = $this->orgUser();
        VacationEntitlement::create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'year' => 2026,
            'entitled_days' => 30,
            'severely_disabled_days' => 5,
            'other_days' => 2,
            'carryover_days' => 3,
        ]);

        $this->actingAs($user);
        $balance = app(VacationBalanceService::class)->balanceFor((int) $user->id, 2026);

        $this->assertSame(30.0, $balance->entitledDays);
        $this->assertSame(5.0, $balance->severelyDisabledDays);
        $this->assertSame(2.0, $balance->otherDays);
        $this->assertSame(40.0, $balance->totalDays());
        $this->assertSame(40.0, $balance->remainingDays());
    }

    public function test_manager_can_store_components_via_form(): void {
        $manager = $this->orgUser();
        $manager->givePermissionTo(Permission::VacationEntitlementsManage->value);
        $worker = $this->orgUser();

        $this->actingAs($manager)
            ->post(route('vacation-entitlements.store'), [
                'user_id' => Sqid::encode(User::class, (int) $worker->id),
                'year' => 2026,
                'entitled_days' => 28,
                'severely_disabled_days' => 5,
                'other_days' => 1.5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vacation_entitlements', [
            'user_id' => $worker->id,
            'year' => 2026,
            'severely_disabled_days' => 5.0,
            'other_days' => 1.5,
        ]);
    }
}
