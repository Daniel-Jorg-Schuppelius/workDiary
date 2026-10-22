<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FleetLogPermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Fleet;

use App\Enums\User\{Permission, UserRole};
use App\Models\Asset\EnergyLog;
use App\Models\Fleet\Vehicle;
use App\Models\Travel\TravelLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Fahrten- und Tankbuch prüfen ihre Rechte (lk-06): jedes interne Profil
 * führt seine eigenen Einträge, Support liest nur, Konten ohne Recht nichts.
 */
class FleetLogPermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_travel_and_energy_logs_follow_their_rights(): void {
        foreach ([UserRole::Buchhaltung, UserRole::Callcenter, UserRole::Aussendienst] as $role) {
            $user = $this->userWithRole($role->value);
            $this->assertTrue(Gate::forUser($user)->allows('create', TravelLog::class), $role->value);
            $this->assertTrue(Gate::forUser($user)->allows('create', EnergyLog::class), $role->value);
            $this->assertTrue(Gate::forUser($user)->allows('viewAny', TravelLog::class), $role->value);
        }

        $support = $this->userWithRole(UserRole::Support->value);
        $this->assertTrue(Gate::forUser($support)->allows('viewAny', TravelLog::class));
        $this->assertFalse(Gate::forUser($support)->allows('create', TravelLog::class));
        $this->assertFalse(Gate::forUser($support)->allows('create', EnergyLog::class));

        $bare = $this->actorIn($this->organization);
        $this->actingAs($bare)->get(route('travel-logs.index'))->assertForbidden();
        $own = TravelLog::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $bare->id]);
        $this->assertFalse(Gate::forUser($bare)->allows('update', $own));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('update', $own));

        $reader = $this->actorIn($this->organization, [Permission::TravelLogViewAny]);
        $this->actingAs($reader)->get(route('travel-logs.index'))->assertOk();

        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $energy = EnergyLog::factory()->create(['organization_id' => $this->organization->id, 'vehicle_id' => $vehicle->id, 'user_id' => $bare->id]);
        $this->actAsTeam($this->organization);
        $this->assertFalse(Gate::forUser($bare)->allows('update', $energy));
    }
}
